<?php

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

$request = array( 'uri'	=> $_SERVER['REQUEST_URI'],
	'method' => $_SERVER['REQUEST_METHOD'],
	'action' => null
);

if( ! in_array( $request['method'], $allowed_methods, true ) ){ return; }

foreach ($endpoints_url as $key => $val) {
	if( 0 < strpos($request['uri'], $val) ){
		$request['action'] = $key;
		break;
	}
}

if( ! in_array( $request['action'], $allowed_actions, true ) ){ return; }

if( ! class_exists('Wpmc_Client_Access_Handler') ){ require WPMC_INCLUDES_PATH . '/class-wpmc-client-access-handler.php'; }
if( ! class_exists('Wpmc_Authorize_Access_Handler') ){ require WPMC_INCLUDES_PATH . '/class-wpmc-authorize-access-handler.php'; }

switch( $request['method'] ){
	case 'GET': $params = $_GET; break;
	case 'POST': $params = $_POST; break;
}

switch( $request['action'] ){
	case 'access':
		$o = new Wpmc_Client_Access_Handler();
		$o->available_access_endpoint( $params );
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