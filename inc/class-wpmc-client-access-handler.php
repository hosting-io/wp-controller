<?php
class Wpmc_Client_Access_Handler {

	protected $keys_length = 40;
	protected $tokens_length = 40;
	protected $refresh_token_expires = 900;	// 15 Minutes.
	protected $authorization_code_expires = 30;

	private $tokens = array(
		'access' => null,
		'refresh' => null,
		'refresh_expires' => null,
	);

	private $authorization = array(
		'code'	=> null,
		'expires' => null
	);

	private $client = array(
		'id'	=> null,
		'secret' => null
	);

	protected function generate_key(){
		$key = "";
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		for ($i=0; $i<$this->keys_length; $i++) {
			$key .= $chars[ rand( 0, strlen( $chars ) - 1 ) ];
		}
		return $key;
	}

	protected function generate_token(){
		return strtolower( wp_generate_password( $this->tokens_length, false, false ) );
	}

	protected function create_access_token( $user_id ){
		$this->tokens['access'] = $this->generate_token();
		update_option( 'wpmc_access_token[' . $user_id . ']', $this->tokens['access'] );
	}

	protected function create_refresh_token( $user_id ){
		$this->tokens['refresh'] = $this->generate_token();
		update_option( 'wpmc_refresh_token[' . $user_id . ']', $this->tokens['refresh'] );
	}

	protected function create_refresh_expire_token( $user_id ){
		$this->tokens['refresh_expires'] = time() + $this->refresh_token_expires;
		update_option( 'wpmc_refresh_token_expires[' . $user_id . ']', $this->tokens['refresh_expires'] );
	}

	protected function throw_request_error($message = ''){
		echo esc_html( $message );
		wp_die();
	}

	protected function set_invalid_request_error($status = 500){
		return new WP_Error( 'invalid-request', __( 'Invalid request', 'wp-management-controller' ), array( 'status' => $status ) );
	}

	protected function unpackage_request($args){

		$package = Wpmc_Rsa_Handler::decrypt( base64_decode( $args['package'] ) );

		$signature = base64_decode( $args['signature'] );
		$verified_signature =  Wpmc_Rsa_Handler::verify( $package, $signature );

		if( ! $verified_signature ){
			$error = $this->set_invalid_request_error( 403 );
			$this->throw_request_error( $error->get_error_message() );
		}

		$package = json_decode( $package, true );
		
		return $package;
	}

	protected function prepare_response($args){
		$plaintext = json_encode( $args );
	    $signature = base64_encode( Wpmc_Rsa_Handler::sign( $plaintext ) );
	    $package = base64_encode( Wpmc_Rsa_Handler::encrypt( $plaintext ) );
	    return  array( 'signature' => $signature, 'package' => $package );
	}

	protected function create_tokens($user){
		$this->create_access_token($user->ID);
		$this->create_refresh_token($user->ID);
		$this->create_refresh_expire_token($user->ID);
		return $this->get_tokens();
	}

	protected function refresh_tokens($user){
		$this->tokens['access'] = get_option( 'wpmc_access_token[' . $user->ID . ']' );
		$this->create_refresh_token($user->ID);
		$this->create_refresh_expire_token($user->ID);
		return $this->get_tokens();
	}

	protected function create_authorization_code( $user ){
		
		$this->authorization = array(
			'code'	=> $this->generate_token(),
			'expires' => time() + $this->authorization_code_expires
		);

		update_option( 'wpmc_authorization_code[' . $user->ID . ']', $this->authorization['code'] );
		update_option( 'wpmc_authorization_expires[' . $user->ID . ']', $this->authorization['expires'] );
		
		return $this->get_authorization_code();
	}

	protected function create_client( $user ){

		$this->client = array(
			'id'	=> $this->generate_key(),
			'secret' => $this->generate_key()
		);

		update_option( 'wpmc_client_id[' . $user->ID . ']', $this->client['id'] );
		update_option( 'wpmc_client_secret[' . $user->ID . ']', $this->client['secret'] );

		return $this->get_client();
	}

	protected function get_tokens(){
		return $this->tokens;
	}

	protected function get_authorization_code(){
		return $this->authorization;
	}

	protected function get_authorization_code_expire($user_id){
		return (int) get_option( 'wpmc_authorization_expires[' . $user_id . ']' );
	}

	protected function get_client($user){
		$this->client['id'] = null === $this->client['id'] ? get_option( 'wpmc_client_id[' . $user->ID . ']' ) : $this->client['id'];
		$this->client['secret'] = null === $this->client['secret'] ? get_option( 'wpmc_client_secret[' . $user->ID . ']' ) : $this->client['secret'];
		return $this->client;
	}

	protected function user_id_by_token_or_key($type, $value){
		global $wpdb;
		$db_table = $wpdb->prefix . 'options';
		$sql = $wpdb->prepare('SELECT option_name FROM ' . $db_table . ' WHERE option_value=%s LIMIT 1', $value);
		$result = $wpdb->get_var($sql);
		switch($type){
			case 'client_id':
				$regex = "/wpmc_client_id\[(.*?)\]/i";
				break;
			case 'client_secret':
				$regex = "/wpmc_client_secret\[(.*?)\]/i";
				break;
			case 'authorization_code':
				$regex = "/wpmc_authorization_code\[(.*?)\]/i";
				break;
			case 'access_token':
				$regex = "/wpmc_access_token\[(.*?)\]/i";
				break;
			case 'refresh_token':
				$regex = "/wpmc_refresh_token\[(.*?)\]/i";
				break;
		}

		if ( preg_match( $regex, $result, $match ) ){
			return (int) $match[1];
		}
		return 0;
	}

	protected function invalid_access_redirect($url, $error, $site_id, $request_url, $request_action){
		$redirect_args = $this->prepare_response( array( 'site_id' => $site_id, 'request_url' => $request_url, 'request_action' => $request_action, 'error' => $error ) );
		$redirect_args_len = count($redirect_args);
		$url .= ( parse_url( $url, PHP_URL_QUERY ) ? '&' : '?' );
		$cntr = 0;
        foreach ($redirect_args as $k => $v) {
        	$cntr++;
        	$url .= $k . '=' . $v . ( $cntr < $redirect_args_len ? '&' : '' ); 
        }
		wp_redirect($url);
		exit;
	}

	protected function invalid_access_response($url, $error, $site_id, $request_url, $request_action){
		return $this->prepare_response( array( 'site_id' => $site_id, 'request_url' => $request_url, 'request_action' => $request_action, 'error' => $error ) );
	}

	public function access_endpoint( $args ){

		$valid_actions = array('login');

		$package = $this->unpackage_request($args);

		$action = isset( $package['action'] ) && $package['action'] ? $package['action'] : null;
		$site_id = isset( $package['site_id'] ) && $package['site_id'] ? $package['site_id'] : null;
		$request_url = isset( $package['request_url'] ) && $package['request_url'] ? $package['request_url'] : null;
		$access_token = isset( $package['access_token'] ) && $package['access_token'] ? $package['access_token'] : null;
		$username = isset( $package['username'] ) && $package['username'] ? $package['username'] : null;
		$invalid_redirect = isset( $package['invalid_redirect'] ) && $package['invalid_redirect'] ? $package['invalid_redirect'] : null;

		$user_id_by_access_token = $this->user_id_by_token_or_key( 'access_token', $access_token );

		// Invalid request data.
		if( null === $action || null === $site_id || nul === $request_url || null === $access_token ||  null === $username || ! in_array( $action, $valid_actions, true ) ){
			
			if( null !== $invalid_redirect ){
				$error = 'invalid-request';
				$this->invalid_access_redirect( $invalid_redirect, $error, $site_id, $request_url, $action );
			}
			else{
				// Redirect in home page.
				wp_safe_redirect(site_url());
			}
		}

		if ( ! function_exists('get_user_by') ) { require_once (ABSPATH . WPINC . '/pluggable.php'); }

		$user = get_user_by( 'login', $username );

		// Invalid username.
		if( ! $user ){
			if( null !== $invalid_redirect ){
				$error = 'invalid-user';
				$this->invalid_access_redirect( $invalid_redirect, $error, $site_id, $request_url, $action );
			}
			else{
				// Redirect in home page.
				wp_safe_redirect(site_url());
			}
		}

		$user_id_by_access_token = $this->user_id_by_token_or_key( 'access_token', $access_token );

		// Invalid access token.
		if( $user_id_by_access_token !== $user->ID ){
			if( null !== $invalid_redirect ){
				$error = 'invalid-access';
				$this->invalid_access_redirect( $invalid_redirect, $error, $site_id, $request_url, $action );
			}
			else{
				// Redirect in login page.
				wp_safe_redirect(wp_login_url());
			}
		}

		switch( $action ){
			case 'login':
				$curr = wp_set_current_user( $user->ID, $user->user_login );
		        wp_set_auth_cookie($user->ID);
		        do_action( 'wp_login', $user->user_login, $user, false );
		        wp_safe_redirect(admin_url());
				break;
		}
	}

	public function available_updates_endpoint( $args ){

		$package = $this->unpackage_request($args);

		$valid_actions = array('updates', 'updates-2');

		$action = isset( $package['action'] ) && $package['action'] ? $package['action'] : null;
		$updates_type = isset( $package['type'] ) && $package['type'] ? $package['type'] : null;
		$only_count = isset( $package['count'] ) && $package['count'] ? $package['count'] : null;
		$site_id = isset( $package['site_id'] ) && $package['site_id'] ? $package['site_id'] : null;
		$request_url = isset( $package['request_url'] ) && $package['request_url'] ? $package['request_url'] : null;
		$access_token = isset( $package['access_token'] ) && $package['access_token'] ? $package['access_token'] : null;
		$username = isset( $package['username'] ) && $package['username'] ? $package['username'] : null;
		$invalid_redirect = isset( $package['invalid_redirect'] ) && $package['invalid_redirect'] ? $package['invalid_redirect'] : null;

		if( null === $action || null === $updates_type || null === $only_count || nulll === $site_id || nul === $request_url || null === $access_token ||  null === $username || ! in_array( $action, $valid_actions, true ) ){

			if( null !== $invalid_redirect ){
				$error = 'invalid-request';
				return $this->invalid_access_response( $invalid_redirect, $error, $site_id, $request_url, $action );
			}
			else{
				// Redirect in home page.
				wp_safe_redirect(site_url());
			}

		}

		$valid_types = array('all', 'core', 'themes', 'plugins');
		
		if( ! in_array( $updates_type, $valid_types, true ) ){
			return $this->prepare_response( $this->set_invalid_request_error() );
		}

		// Return only number of available updates.
		$only_count = 'false' === $only_count ? false : true;

		if ( ! function_exists('get_user_by') ) { require_once (ABSPATH . WPINC . '/pluggable.php'); }

		$user = get_user_by( 'login', $username );

		// Invalid username.
		if( ! $user ){

			if( null !== $invalid_redirect ){
				$error = 'invalid-user';
				return $this->invalid_access_response( $invalid_redirect, $error, $site_id, $request_url, $action );
			}
			else{
				// Redirect in home page.
				wp_safe_redirect(site_url());
			}
		}

		$user_id_by_access_token = $this->user_id_by_token_or_key( 'access_token', $access_token );
		
		// Invalid access token.
		if( $user_id_by_access_token !== $user->ID ){
			if( null !== $invalid_redirect ){
				$error = 'invalid-access';
				// return array( $invalid_redirect, $error, $site_id, $request_url, $action );
				return $this->invalid_access_response( $invalid_redirect, $error, $site_id, $request_url, $action );
			}
			else{
				// Redirect in login page.
				wp_safe_redirect(wp_login_url());
			}
		}

		if( 'all' === $updates_type ){

			if( $only_count ){
				$ret = array(
					'core' => wpmc_core_upgrade() ? 1 : 0,
					'themes' => count( wpmc_themes_updates() ),
					'plugins' => count( wpmc_plugins_updates() ),
				);
			}
			else{
				$ret = array(
					'core' => wpmc_core_upgrade(),
					'themes' => wpmc_themes_updates(),
					'plugins' => wpmc_plugins_updates(),
				);
			}
		}
		else{
			switch( $updates_type ){
				case 'core':
					$ret = $only_count ? ( wpmc_core_upgrade() ? 1 : 0 ) : wpmc_core_upgrade();
					break;
				case 'themes':
					$ret = $only_count ? count( wpmc_themes_updates() ) : wpmc_themes_updates();
					break;
				case 'plugins':
					$ret = $only_count ? count( wpmc_plugins_updates() ) : wpmc_plugins_updates();
					break;
			}
		}

		$ret['manage_options'] = user_can( $user, 'manage_options') ? 1 : 0;

		return $this->prepare_response( $ret );
	}
}