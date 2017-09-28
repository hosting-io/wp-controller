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

	protected function is_authorized_request($args){
		$is_ok = true;
		if( $is_ok ){
			$ret = $is_ok;
		}
		else{
			$ret = $this->set_invalid_request_error( 403 );
		}
		return $ret;
	}

	protected function unpackage_request($args){
		
		$authorized = $this->is_authorized_request($args);

		if ( is_wp_error( $authorized ) ) {
			$this->throw_request_error( $authorized->get_error_message() );
		}

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

	public function create_tokens($user){
		$this->create_access_token($user->ID);
		$this->create_refresh_token($user->ID);
		$this->create_refresh_expire_token($user->ID);
		return $this->get_tokens();
	}

	public function refresh_tokens($user){
		$this->tokens['access'] = get_option( 'wpmc_access_token[' . $user->ID . ']' );
		$this->create_refresh_token($user->ID);
		$this->create_refresh_expire_token($user->ID);
		return $this->get_tokens();
	}

	public function create_authorization_code( $user ){
		
		$this->authorization = array(
			'code'	=> $this->generate_token(),
			'expires' => time() + $this->authorization_code_expires
		);

		update_option( 'wpmc_authorization_code[' . $user->ID . ']', $this->authorization['code'] );
		update_option( 'wpmc_authorization_expires[' . $user->ID . ']', $this->authorization['expires'] );
		
		return $this->get_authorization_code();
	}

	public function create_client( $user ){

		$this->client = array(
			'id'	=> $this->generate_key(),
			'secret' => $this->generate_key()
		);

		update_option( 'wpmc_client_id[' . $user->ID . ']', $this->client['id'] );
		update_option( 'wpmc_client_secret[' . $user->ID . ']', $this->client['secret'] );

		return $this->get_client();
	}

	public function get_tokens(){
		return $this->tokens;
	}

	public function get_authorization_code(){
		return $this->authorization;
	}

	public function get_authorization_code_expire($user_id){
		return (int) get_option( 'wpmc_authorization_expires[' . $user_id . ']' );
	}

	public function get_client($user){
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

	function available_access_endpoint( $args ){

		$valid_actions = array('login');

		$package = $this->unpackage_request($args);

		$action = isset( $package['action'] ) && $package['action'] ? $package['action'] : null;
		$access_token = isset( $package['access_token'] ) && $package['access_token'] ? $package['access_token'] : null;
		$username = isset( $package['username'] ) && $package['username'] ? $package['username'] : null;

		$action = null !== $package['action'] && '' !== trim( $package['action'] ) ? $package['action'] : $action;
		$access_token = null !== $package['access_token'] && '' !== trim( $package['access_token'] ) ? $package['access_token'] : $action;
		$username = null !== $package['username'] && '' !== trim( $package['username'] ) ? $package['username'] : $action;

		if( null === $action || null === $access_token ||  null === $username ){
			die("Error - access 1");
		}

		if( ! in_array($action, $valid_actions, true) ){
			die("Error - access 2");
		}

		if ( ! function_exists('get_user_by') ) { require_once (ABSPATH . WPINC . '/pluggable.php'); }
		$user = get_user_by( 'login', $username );

		if( ! $user ){
			die("Error - access 3");
		}

		$user_id_by_access_token = $this->user_id_by_token_or_key( 'access_token', $access_token );

		if( $user_id_by_access_token !== $user->ID ){
			die("Error - access 4");
		}

		switch( $action ){
			case 'login':
				$curr = wp_set_current_user( $user->ID, $user->user_login );
		        wp_set_auth_cookie( $user->ID );
		        do_action( 'wp_login', $user->user_login, $user, false );
		        wp_safe_redirect( admin_url() );
				break;
		}
	}

	function available_upgrades_endpoint( $args ){
		
		$authorized = $this->is_authorized_request($args);

		if ( is_wp_error( $authorized ) ) {
			$this->throw_request_error( $authorized->get_error_message() );
		}

		$upgrades_type = isset( $args['type'] ) ? trim($args['type']) : '';

		$valid_types = array('all', 'core', 'themes', 'plugins');
		
		if( ! in_array( $upgrades_type, $valid_types, true ) ){
			return $this->set_invalid_request_error();
		}

		// Return only number of available upgrades.
		$only_count = isset( $args['count'] ) ? true : false;

		if( 'all' === $upgrades_type ){

			if( $only_count ){
				$ret = array(
					'core' => wpmc_core_upgrade() ? 1 : 0,
					'themes' => count( wpmc_themes_upgrades() ),
					'plugins' => count( wpmc_plugins_upgrades() ),
				);
			}
			else{
				$ret = array(
					'core' => wpmc_core_upgrade(),
					'themes' => wpmc_themes_upgrades(),
					'plugins' => wpmc_plugins_upgrades(),
				);
			}
		}
		else{
			switch( $upgrades_type ){
				case 'core':
					$ret = $only_count ? ( wpmc_core_upgrade() ? 1 : 0 ) : wpmc_core_upgrade();
					break;
				case 'themes':
					$ret = $only_count ? count( wpmc_themes_upgrades() ) : wpmc_themes_upgrades();
					break;
				case 'plugins':
					$ret = $only_count ? count( wpmc_plugins_upgrades() ) : wpmc_plugins_upgrades();
					break;
			}
		}

		return $ret;
	}
}