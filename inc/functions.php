<?php
function wpmc_updates_info($type) {

	$valid_types = array('update_core', 'update_themes', 'update_plugins');

	if ( ! in_array( $type, $valid_types, true ) ) {
        return false;
    }

    return apply_filters( 'site_transient_' . $type, get_option( '_site_transient_' . $type ) );
}

function wpmc_core_upgrade(){

	global $wp_version;

	$core = wpmc_updates_info('update_core');

    if( isset( $core->updates ) && ! empty( $core->updates ) ) {

        $core_info = (array) $core->updates[0];

        if ( "development" === $core_info['response'] || version_compare( $wp_version, $core_info['current'], '<' ) ) {

        	// Set data in return value.
            $core_info['current_version'] = $wp_version;
            
            // Exclude information from return value.
            unset( $core_info['php_version'] );
            unset( $core_info['mysql_version'] );

            return $core_info;
        }
    }

    return array();
}

function wpmc_themes_updates(){

	$themes_updates = array();
        
    $themes_info = wpmc_updates_info('update_themes');

    if ( isset( $themes_info->response ) && ! empty( $themes_info->response ) ) {

    	$all_themes = wp_get_themes();

        foreach ( $all_themes as $theme_template => $theme_data ) {

        	// Exclude child themes.
            if( isset( $theme_data->{'Parent Theme'} ) && ! empty( $theme_data->{'Parent Theme'} ) ){
                continue;
            }

            foreach ( $themes_info->response as $theme_slug => $thm ) {

            	if ( $theme_slug !== $theme_data->Template ) {
            		continue;
            	}

                if ( 0 < strlen( $theme_data->Name ) && 0 < strlen( $theme_data->Version ) ) {
                    
                    // Set data in return value.
                    $themes_info->response[$theme_slug]['name'] = $theme_data->Name;
                    $themes_info->response[$theme_slug]['current_version'] = $theme_data->Version;

                    $themes_updates[] = $themes_info->response[$theme_slug];
                }
            }
        }
    }

    return $themes_updates;
}

function wpmc_plugins_updates(){
	
	$plugins_updates = array();
	$plugins_info = wpmc_updates_info('update_plugins');

    if ( ! empty( $plugins_info->response )) {
        
        if ( ! function_exists('get_plugin_data') ){
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        foreach ( $plugins_info->response as $plugin_path => $plugin_version ) {
            
        	// Exclude plugin "Campaigns.io - WordPress Multisite Controller".
            if ( 'wp-management-controller/wp-management-controller.php' === $plugin_path ){
                continue;
            }

            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
            
            if( ! isset( $plugin_data['Name'] ) ){
                continue;
            }

            $return_plugin = array();

            if ( strlen( $plugin_data['Name'] ) > 0 && strlen( $plugin_data['Version'] ) > 0 ) {

            	// Set data in return value.
                $plugins_info->response[$plugin_path]->name = $plugin_data['Name'];
                $plugins_info->response[$plugin_path]->old_version = $plugin_data['Version'];
                $plugins_info->response[$plugin_path]->file = $plugin_path;

                // Exclude data from return value.
                unset( $plugins_info->response[$plugin_path]->upgrade_notice );
                
                $plugins_updates[] = (array) $plugins_info->response[$plugin_path];
            }
            
        }
    }
	
	return $plugins_updates;
}

function wpmc_delete_all_options(){
    global $wpdb;
    $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->options . ' WHERE option_name LIKE %s', '%wpmc_%' ) );
}

function wpmc_on_uninstall(){
    wpmc_delete_all_options();    
    delete_option('wp_management_controller_version');
    Wpmc_Transients::clean_all();
}

function wpmc_handle_income_requests(){
    
    if( Wpmc::enabled_rest_api() ){ return; }

    $endpoints_url_prefix = '/wp-json/' . WPMC_SLUG;
    $endpoints_url = array(
        'entry' => $endpoints_url_prefix . '/entry',
        'authorize' => $endpoints_url_prefix . '/authorize',
        'tokens' => $endpoints_url_prefix . '/tokens',
        'refresh_tokens' => $endpoints_url_prefix . '/refresh_tokens',
        'access' => $endpoints_url_prefix . '/access',
        'updates' => $endpoints_url_prefix . '/updates',
    );

    $allowed_methods = array('GET', 'POST');
    $allowed_actions = array_keys( $endpoints_url );

    $request = array( 'uri' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD'],
        'action' => NULL
    );

    if( ! in_array( $request['method'], $allowed_methods, true ) ){ return; }

    foreach ($endpoints_url as $key => $val) {
        if( 0 < strpos($request['uri'], $val) ){
            $request['action'] = $key;
            break;
        }
    }

    if( ! in_array( $request['action'], $allowed_actions, true ) ){ return; }

    if( ! class_exists('Wpmc_Authorize_Access_Handler') ){ require WPMC_INCLUDES_PATH . '/class-wpmc-authorize-access-handler.php'; }

    switch( $request['method'] ){
        case 'GET': $params = $_GET; break;
        case 'POST': $params = $_POST; break;
    }

    switch( $request['action'] ){
        case 'entry':
            $o = new Wpmc_Authorize_Access_Handler();
            wp_send_json( $o->entry_endpoint( $params ) );
        case 'authorize':
            $o = new Wpmc_Authorize_Access_Handler();
            wp_send_json( $o->authorize_endpoint( $params ) );
        case 'tokens':
            $o = new Wpmc_Authorize_Access_Handler();
            wp_send_json( $o->tokens_endpoint( $params ) );
        case 'refresh_tokens':
            $o = new Wpmc_Authorize_Access_Handler();
            wp_send_json( $o->refresh_tokens_endpoint( $params ) );
            break;
        case 'access':
            $o = new Wpmc_Client_Access_Handler();
            $o->access_endpoint( $params );
            exit;
        case 'updates':
            $o = new Wpmc_Client_Access_Handler();
            wp_send_json( $o->available_updates_endpoint( $params ) );
            break;
    }
}

function wpmc_theme_data($slug = ''){
    $ret = $slug;

    if( is_string($slug) && '' !== $slug ){
        if( function_exists( 'wp_get_theme' ) ){
            $ret = wp_get_theme($slug);
        }
        else{                
            $all_themes = get_themes();
            foreach( $all_themes as $k => $v ){
                if( $v['Template'] === $slug ){
                    $ret = $v;
                }
            }
        }
    }

    return $ret;
}

function wpmc_update_themes( $themes_slugs = array() ){

    $update_themes = get_site_transient('update_themes');

    if( empty( $update_themes ) || ! is_array( $update_themes->response ) || empty( $update_themes->response ) ){
        return array(
            'error' => 0,
            'message' => 'Seems that all themes are up to date.',
        );
    }

    $themes = array();
    $versions = array();

    foreach( $update_themes->response as $k => $v ){
        if( in_array( $k, $themes_slugs, true ) && isset( $update_themes->response[ $k ] ) ) {
            $themes[$k] = $v;

            if( isset( $update_themes->checked[$k] ) ){
                $versions[ $update_themes->checked[$k] ] = $k;
            }
        }
    }

    if( empty( $themes ) ){
        return array(
            'error' => 0,
            'message' => 'Seems that all themes are up to date.',
        );
    }

    if ( ! class_exists('Core_Upgrader') ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    if( ! function_exists('request_filesystem_credentials') ){
        include_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if( ! class_exists('Wpmc_Empty_Bulk_Upgrader_Skin') ){
        include_once( WPMC_INCLUDES_PATH . '/class-wpmc-empty-bulk-upgrader-skin.php' );
    }

    if( ! function_exists('is_plugin_active') ){
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $upgrader = new Theme_Upgrader( new Wpmc_Empty_Bulk_Upgrader_Skin( compact('title', 'nonce', 'url', 'theme') ) );

    // if( count( $themes ) > 1 ){    // Multiple plugins upgrades.

        $result = $upgrader->bulk_upgrade( array_keys($themes) );

        if( empty($result) ) {
            return array(
                'error' => 1,
                'message' => 'Themes upgrade failed.',
            );
        }

        $return = array();

        if ( ! function_exists('wp_update_themes') ){
            include_once(ABSPATH . 'wp-includes/update.php');
        }

        wp_update_themes();

        $update_themes = get_site_transient('update_themes');

        foreach ($result as $theme_tmp => $theme_info) {

            if ( !$theme_info || is_wp_error($theme_info) ) {
                $return[$theme_tmp] = 'Theme update returned an error.';
            }
            else {
                
                if( ! empty( $result[$theme_tmp] ) || ( isset( $update_themes->checked[$theme_tmp] ) && true === version_compare( array_search($theme_tmp, $versions), $update_themes->checked[$theme_tmp], '<' ) ) ){
                    $return[$theme_tmp] = 1;
                }
                else {
                    
                    $return[$theme_tmp] = 'An error occured. Please, try again.';
                }

            }

        }

        return array(
            'error' => 0,
            'message' => 'All themes was upgraded successfully.',
            'upgraded' => $return,
        );

    // }
    // else{

    //     $theme = array_keys($themes);
    //     $theme_slug = $theme[0];
    //     $themeData = wpmc_theme_data($theme_slug);

    //     if( true !== $upgrader->upgrade( $theme_slug ) ) {
    //         return array(
    //             'error' => 1,
    //             'message' => 'Theme "" upgrade failed.',
    //         );
    //     }

    //     if ( ! function_exists('wp_update_themes') ){
    //         include_once(ABSPATH . 'wp-includes/update.php');
    //     }

    //     wp_update_themes();

    //     return array(
    //         'error' => 0,
    //         'message' => 'Theme "' . $themeData['Name'] . '" was upgraded successfully.',
    //         'upgraded' => array( $theme[0] => 1 ),
    //     );

    // }

}

function wpmc_update_plugins( $plugins_slugs = array() ){

    $update_plugins = get_site_transient( 'update_plugins' );

    if( empty( $update_plugins ) || ! is_array( $update_plugins->response ) || empty( $update_plugins->response ) ){
        return array(
            'error' => 0,
            'message' => 'Seems that all plugins are up to date.',
        );
    }

    $plugins = array();
    $versions = array();

    foreach( $update_plugins->response as $k => $v ){

        if( in_array( $v->slug, $plugins_slugs, true ) ){
            
            if ( isset( $update_plugins->response[ $k ] ) ) {

                $plugins[$k] = $v;

                if( isset( $update_plugins->checked[$k] ) ){
                    $versions[ $update_plugins->checked[$k] ] = $k;
                }
            }

        }
    }

    if( empty( $plugins ) ){
        return array(
            'error' => 0,
            'message' => 'Seems that all plugins are up to date.',
        );
    }

    if ( ! class_exists('Core_Upgrader') ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    if( ! function_exists('request_filesystem_credentials') ){
        include_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if( ! class_exists('Wpmc_Empty_Bulk_Upgrader_Skin') ){
        include_once( WPMC_INCLUDES_PATH . '/class-wpmc-empty-bulk-upgrader-skin.php' );
    }

    if( ! function_exists('is_plugin_active') ){
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $upgrader = new Plugin_Upgrader( new Wpmc_Empty_Bulk_Upgrader_Skin( compact('title', 'nonce', 'url', 'theme') ) );

    // if( count( $plugins ) > 1 ){    // Multiple plugins upgrades.

        $result = $upgrader->bulk_upgrade( array_keys($plugins) );

        if( empty($result) ) {
            return array(
                'error' => 1,
                'message' => 'Plugins upgrade failed.',
            );
        }

        $return = array();

        if ( ! function_exists('wp_update_plugins') ){
            include_once(ABSPATH . 'wp-includes/update.php');
        }

        wp_update_plugins();

        $update_plugins = get_site_transient('update_plugins');

        foreach ($result as $plugin_slug => $plugin_info) {

            if ( ! $plugin_info || is_wp_error($plugin_info) ) {
                $return[ $plugins[$plugin_slug]->slug ] = 'Plugin update returned an error.';
            }
            else {
                
                if( ! empty( $result[$plugin_slug] ) || ( isset( $update_plugins->checked[$plugin_slug] ) && true === version_compare( array_search($plugin_slug, $versions), $update_plugins->checked[$plugin_slug], '<' ) ) ){
                    $return[ $plugins[$plugin_slug]->slug ] = 1;
                }
                else {
                    
                    $return[ $plugins[$plugin_slug]->slug ] = 'An error occured. Please, try again.';
                }

            }

        }

        return array(
            'error' => 0,
            'message' => 'All plugins was upgraded successfully.',
            'upgraded' => $return,
        );
    // }
    // else{   // Single plugin upgrade

    //     $pluginPath = array_keys($plugins);
    //     $pluginPath = $pluginPath[0];
    //     $pluginData = get_plugin_data( WP_PLUGIN_DIR . '/' . $pluginPath );

    //     $wasActivated = is_plugin_active( $pluginPath );
    //     if( $wasActivated ){
    //         deactivate_plugins( $pluginPath );
    //     }

    //     if( true !== $upgrader->upgrade( $pluginPath ) ) {
    //         return array(
    //             'error' => 1,
    //             'message' => 'Plugin "' . $pluginData['Name'] . '" upgrade failed.',
    //         );
    //     }

    //     if( $wasActivated ){
    //         activate_plugin( $pluginPath );
    //     }

    //     if ( ! function_exists('wp_update_plugins') ){
    //         include_once(ABSPATH . 'wp-includes/update.php');
    //     }
        
    //     wp_update_plugins();

    //     return array(
    //         'error' => 0,
    //         'message' => 'Plugin "' . $pluginData['Name'] . '" was upgraded successfully.',
    //         'upgraded' => array( $plugins[$plugin_slug]->slug $pluginPath => 1 ),
    //     );
    // }
}

function wpmc_update_core(){

    if( ! function_exists('get_core_checksums') ){
        include_once( ABSPATH . '/wp-admin/includes/update.php' );
    }
    
    wp_version_check();

    $core = get_site_transient( 'update_core' );

    if( ! isset( $core->updates ) || empty( $core->updates ) ){
        return array(
            'error' => 1,
            'message' => 'Refresh transient failed. Try again.'
        );
    }

    $current_update = $core->updates[0];

    if( ! isset( $current_update->response ) || 'latest' === $current_update->response ){
        return array(
            'error' => 0,
            'message' => 'Seems that WordPress core is up to date.',
        );
    }

    if( 'development' === $current_update->response ){
        return array(
            'error' => 1,
            'message' => 'Unexpected error. Please, upgrade manually.'
        );
    }

    if( 'upgrade' !== $current_update->response ){
        return array(
            'error' => 1,
            'message' => 'Transient mismatch. Try again.'
        );
    }

    if ( ! class_exists('Core_Upgrader') ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    if( ! function_exists('request_filesystem_credentials') ){
        include_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if( ! class_exists('Wpmc_Empty_Bulk_Upgrader_Skin') ){
        include_once( WPMC_INCLUDES_PATH . '/class-wpmc-empty-bulk-upgrader-skin.php' );
    }    

    $upgrader = new Core_Upgrader( new Wpmc_Empty_Bulk_Upgrader_Skin() );

    $result = $upgrader->upgrade($current_update);
    
    if( is_wp_error($result) ){
        return array(
            'error' => 1,
            'message' => 'WordPress core upgrade failed.'
        );
    }

    return array(
        'error' => 0,
        'message' => 'The WordPress core was upgraded successfully.'
    );
}