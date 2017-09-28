<?php
function wpmc_upgrades_info($type) {

	$valid_types = array('update_core', 'update_themes', 'update_plugins');

	if ( ! in_array( $type, $valid_types, true ) ) {
        return false;
    }

    return apply_filters( 'site_transient_' . $type, get_option( '_site_transient_' . $type ) );
}

function wpmc_core_upgrade(){

	global $wp_version;

	$core = wpmc_upgrades_info('update_core');

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

function wpmc_themes_upgrades(){

	$themes_upgrades = array();
        
    $themes_info = wpmc_upgrades_info('update_themes');

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

                    $themes_upgrades[] = $themes_info->response[$theme_slug];
                }
            }
        }
    }

    return $themes_upgrades;
}

function wpmc_plugins_upgrades(){
	
	$plugins_upgrades = array();
	$plugins_info = wpmc_upgrades_info('update_plugins');

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
                
                $plugins_upgrades[] = (array) $plugins_info->response[$plugin_path];
            }
            
        }
    }
	
	return $plugins_upgrades;
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
        case 'access':
            $o = new Wpmc_Client_Access_Handler();
            $o->access_endpoint( $params );
            exit;
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
    }
}