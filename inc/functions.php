<?php
/**/
function wpmc_updates_info($type) {

	$valid_types = array('update_core', 'update_themes', 'update_plugins');

	if ( ! in_array( $type, $valid_types, true ) ) {
        return false;
    }

    return apply_filters( 'site_transient_' . $type, get_option( '_site_transient_' . $type ) );
}

function wpmc_core_upgrade(){

    $core_info = get_transient( 'wpmc_core_update_info' );
    $core_info = $core_info && is_array( $core_info ) && ! empty( $core_info ) ? $core_info : array();
    
    if( empty( $core_info ) ){

    	global $wp_version;

    	$core = wpmc_updates_info('update_core');

        if( isset( $core->updates ) && ! empty( $core->updates ) ) {

            $core_info = (array) $core->updates[0];

            if ( "development" === $core->updates[0]->response || version_compare( $wp_version, $core->updates[0]->current, '<' ) ) {

            	/* Set data in return value. */
                $core_info['current_version'] = $wp_version;
                
                /* Exclude information from return value. */
                unset( $core_info['php_version'] );
                unset( $core_info['mysql_version'] );

                set_transient( 'wpmc_core_update_info', $core_info, DAY_IN_SECONDS );
            }
            else{
                $core_info = array();
            }
        }
    }

    return $core_info;
}

function wpmc_themes_updates(){

    $themes_updates = get_transient( 'wpmc_themes_updates_info' );
    $themes_updates = $themes_updates && is_array( $themes_updates ) && ! empty( $themes_updates ) ? $themes_updates : array();
    
    if( empty( $themes_updates ) ){

        $themes_info = wpmc_updates_info('update_themes');

        if ( isset( $themes_info->response ) && ! empty( $themes_info->response ) ) {

        	$all_themes = wp_get_themes();

            foreach ( $all_themes as $theme_template => $theme_data ) {

            	/* Exclude child themes. */
                if( isset( $theme_data->{'Parent Theme'} ) && ! empty( $theme_data->{'Parent Theme'} ) ){
                    continue;
                }

                foreach ( $themes_info->response as $theme_slug => $thm ) {

                	if ( $theme_slug !== $theme_data->Template ) {
                		continue;
                	}

                    if ( 0 < strlen( $theme_data->Name ) && 0 < strlen( $theme_data->Version ) ) {
                        
                        /* Set data in return value. */
                        $themes_info->response[$theme_slug]['name'] = $theme_data->Name;
                        $themes_info->response[$theme_slug]['current_version'] = $theme_data->Version;

                        $themes_updates[] = $themes_info->response[$theme_slug];
                    }
                }
            }
            
            set_transient( 'wpmc_themes_updates_info', $themes_updates, DAY_IN_SECONDS );
        }
    }

    return $themes_updates;
}

function wpmc_plugins_updates(){

    $plugins_updates = get_transient( 'wpmc_plugins_updates_info' );

    $plugins_updates = $plugins_updates && is_array( $plugins_updates )&& ! empty( $plugins_updates ) ? $plugins_updates : array();
	
    if( empty( $plugins_updates ) ){
    	
    	$plugins_info = wpmc_updates_info('update_plugins');

        if ( ! empty( $plugins_info->response )) {
            
            if ( ! function_exists('get_plugin_data') ){
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            foreach ( $plugins_info->response as $plugin_path => $plugin_version ) {
                
            	/* Exclude plugin "Campaigns.io - WordPress Multisite Controller". */
                if ( 'wp-management-controller/wp-management-controller.php' === $plugin_path ){
                    continue;
                }

                $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
                
                if( ! isset( $plugin_data['Name'] ) ){
                    continue;
                }

                $return_plugin = array();

                if ( strlen( $plugin_data['Name'] ) > 0 && strlen( $plugin_data['Version'] ) > 0 ) {

                	/* Set data in return value. */
                    $plugins_info->response[$plugin_path]->name = $plugin_data['Name'];
                    $plugins_info->response[$plugin_path]->old_version = $plugin_data['Version'];
                    $plugins_info->response[$plugin_path]->file = $plugin_path;

                    /* Exclude data from return value. */
                    unset( $plugins_info->response[$plugin_path]->upgrade_notice );
                    
                    $plugins_updates[] = (array) $plugins_info->response[$plugin_path];
                }
            }

            set_transient( 'wpmc_plugins_updates_info', $plugins_updates, DAY_IN_SECONDS );
        }
    }
	
	return $plugins_updates;
}

function wpmc_delete_all_options(){
    global $wpdb;
    $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->options . ' WHERE option_name LIKE %s', '%wpmc_%' ) );
}

function wpmc_on_uninstall(){
    // wpmc_delete_all_options();    
    delete_option('wp_management_controller_version');
    Wpmc_Transients::clean_all();
}

function wpmc_handle_income_requests(){
    
    if( Wpmc::enabled_rest_api() ){ return; }

    $endpoints_url_prefix = '/wp-json/' . WPMC_SLUG;
    $endpoints_url = array(
        'ping' => $endpoints_url_prefix . '/ping',
        'entry' => $endpoints_url_prefix . '/entry',
        'authorize' => $endpoints_url_prefix . '/authorize',
        'tokens' => $endpoints_url_prefix . '/tokens',
        'refresh_tokens' => $endpoints_url_prefix . '/refresh_tokens',
        'login' => $endpoints_url_prefix . '/login',
        'access' => $endpoints_url_prefix . '/access',
        'updates' => $endpoints_url_prefix . '/updates',
        'update_now' => $endpoints_url_prefix . '/update_now',
    );

    $allowed_methods = array('GET', 'POST');
    $allowed_actions = array_keys( $endpoints_url );

    $request = array(
        'uri' => $_SERVER['REQUEST_URI'],
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
        case 'GET':
            $params = $_GET;
            if( 'access' !== $request['action'] ){  /* @note: Only 'access' endpoint uses $_GET request. */
                wp_redirect( home_url() );
                exit;
            }
            break;
        case 'POST':
            $params = $_POST;
            break;
    }

    switch( $request['action'] ){
        case 'ping':
            $o = new Wpmc_Client_Access_Handler();
            wp_send_json( $o->ping_endpoint( $params ) );
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
            wp_send_json( $o->access_endpoint( $params ) );
            exit;
        case 'login':
            $o = new Wpmc_Client_Access_Handler();
            return $o->login_endpoint( $params );
        case 'updates':
            $o = new Wpmc_Client_Access_Handler();
            wp_send_json( $o->available_updates_endpoint( $params ) );
            break;
        case 'update_now':
            $o = new Wpmc_Client_Access_Handler();
            return $o->available_update_now_endpoint( $params );
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
        
        $updraded = array();
        foreach ($themes_slugs as $key => $val) {
            $updraded[$val] = 1;
        }

        delete_transient( 'wpmc_themes_updates_info' );
        
        return array(
            'error' => 0,
            'message' => 'Seems that all themes are up to date.',
            'upgraded' => $updraded,
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

        $updraded = array();
        foreach ($themes_slugs as $key => $val) {
            $updraded[$val] = 1;
        }

        delete_transient( 'wpmc_themes_updates_info' );

        return array(
            'error' => 0,
            'message' => 'Seems that all themes are up to date.',
            'upgraded' => $updraded,
        );
    }

    if ( ! class_exists('Core_Upgrader') ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    if( ! function_exists('request_filesystem_credentials') ){
        include_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if( ! function_exists('is_plugin_active') ){
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! function_exists('wp_update_themes') ){
        include_once ABSPATH . 'wp-includes/update.php';
    }

    if (!class_exists('Automatic_Upgrader_Skin')) {
        include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
    }
    
    /* The Automatic_Upgrader_Skin skin shouldn't output anything. */
    $upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );    
    
    $upgrader->init();
    
    defined('DOING_CRON') or define('DOING_CRON', true);

    $result = $upgrader->bulk_upgrade( array_keys($themes) );

    if( empty($result) ) {
        return array(
            'error' => 1,
            'message' => 'Themes upgrade failed.',
        );
    }

    $return = array();

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

    delete_transient( 'wpmc_themes_updates_info' );

    return array(
        'error' => 0,
        'message' => 'All themes was upgraded successfully.',
        'upgraded' => $return,
    );
}

function wpmc_update_plugins( $plugins_slugs = array() ){

    $update_plugins = get_site_transient( 'update_plugins' );

    if( empty( $update_plugins ) || ! is_array( $update_plugins->response ) || empty( $update_plugins->response ) ){

        $updraded = array();
        foreach ($plugins_slugs as $key => $val) {
            $updraded[$val] = 1;
        }

        delete_transient( 'wpmc_plugins_updates_info' );

        return array(
            'error' => 0,
            'message' => 'Seems that all plugins are up to date.',
            'upgraded' => $updraded,
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

        $updraded = array();
        foreach ($plugins_slugs as $key => $val) {
            $updraded[$val] = 1;
        }

        delete_transient( 'wpmc_plugins_updates_info' );

        return array(
            'error' => 0,
            'message' => 'Seems that all plugins are up to date.',
            'upgraded' => $updraded,
        );
    }

    if ( ! class_exists('Core_Upgrader') ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    if( ! function_exists('request_filesystem_credentials') ){
        include_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if( ! function_exists('is_plugin_active') ){
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!function_exists('wp_update_plugins')) {
        include_once ABSPATH . 'wp-includes/update.php';
    }

    if (!class_exists('Automatic_Upgrader_Skin')) {
        include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
    }

    /* The Automatic_Upgrader_Skin skin shouldn't output anything. */
    $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
    
    $upgrader->init();
    
    /* Avoid the plugins to be deactivated. */
    defined('DOING_CRON') or define('DOING_CRON', true);
    
    $result = $upgrader->bulk_upgrade( array_keys( $plugins ) );    /* NOTE: Get upgrade process message: $upgrader->skin->get_upgrade_messages() . */

    wp_update_plugins();

    if( empty( $result ) ) {
        return array(
            'result' => $result,
            'message' => 'Plugins upgrade failed.',
        );
    }

    $return = array();

    $update_plugins = get_site_transient('update_plugins');

    foreach ($result as $plugin_slug => $plugin_info) {

        if ( ! $plugin_info || is_wp_error( $plugin_info ) ) {
            $return[ $plugins[$plugin_slug]->slug ] = $plugin_info;
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

    delete_transient( 'wpmc_plugins_updates_info' );

    return array(
        'error' => 0,
        'message' => 'Plugins upgrade process completed.',
        'upgraded' => $return,
    );
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

        delete_transient( 'wpmc_core_update_info' );

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

    if (!class_exists('Automatic_Upgrader_Skin')) {
        include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
    }

    /* The Automatic_Upgrader_Skin skin shouldn't output anything. */
    $upgrader = new Core_Upgrader( new Automatic_Upgrader_Skin() );    
    
    $upgrader->init();
    
    $result = $upgrader->upgrade($current_update);
    
    if( is_wp_error($result) ){
        return array(
            'error' => 1,
            'message' => 'WordPress core upgrade failed.'
        );
    }

    delete_transient( 'wpmc_core_update_info' );

    return array(
        'error' => 0,
        'message' => 'The WordPress core was upgraded successfully.'
    );
}

function wpmc_login_nonce_lifetime() {
    return 60;
}