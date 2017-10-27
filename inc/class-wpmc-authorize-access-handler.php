<?php
class Wpmc_Authorize_Access_Handler extends Wpmc_Client_Access_Handler {

	public function entry_endpoint($args){

		$package = $this->unpackage_request($args);

		$username = isset( $package['username'] ) ? trim( $package['username'] ) : '';

		if( '' === $username ){
			$response_args = array( 'error' => 'missing-username' );
		}
		else{

			if ( ! function_exists('get_user_by') ) { require_once (ABSPATH . WPINC . '/pluggable.php'); }
			$user = get_user_by( 'login', $username );

			if( ! $user ){
				$response_args = array( 'error' => 'invalid-username' );
			}
			else{
				$client = $this->create_client( $user );
				$response_args = array(
					'site_id' => $package['site_id'],
					'security_arg' => $package['security_arg'],
					'client_id' => $client['id'],
					'client_secret' => $client['secret'],
				);
			}
		}

		return $this->prepare_response( $response_args );
	}

	public function authorize_endpoint($args){

		$package = $this->unpackage_request($args);

		$client_id = isset( $package['client_id'] ) ? trim( $package['client_id'] ) : '';
		if( empty( $client_id ) ){
			$response_args = array( 'error' => 3 );
		}
		else{
			
			$user_id = $this->user_id_by_token_or_key( 'client_id', $client_id );

			if( 0 >= $user_id ){
				$response_args = array( 'error' => 4 );
			}
			else{

				if ( ! function_exists('get_user_by') ) { require_once (ABSPATH . WPINC . '/pluggable.php'); }
				
				$user = get_user_by( 'ID', $user_id );
				
				if( ! $user ){ 
					$response_args = array( 'error' => 5 );
				}
				else{

					$user_client = $this->get_client( $user );
					if( $user_client['id'] !== $client_id ){
						$response_args = array( 'error' => 6 );
					}
					else{

						$authorization = $this->create_authorization_code( $user );

						$response_args = array(
							'site_id' => $package['site_id'],
							'security_arg' => $package['security_arg'],
							'code' => $authorization['code']
						);
					}
				}
			}
		}

		return $this->prepare_response( $response_args );
	}

	public function tokens_endpoint($args){

		$package = $this->unpackage_request($args);

		$code = isset( $package['code'] ) ? trim( $package['code'] ) : '';
		$client_id = isset( $package['client_id'] ) ? trim( $package['client_id'] ) : '';
		$client_secret = isset( $package['client_secret'] ) ? trim( $package['client_secret'] ) : '';

		if( '' === $code || '' === $client_id || '' === $client_secret ){
			$response_args = array( 'error' => 7 );
		}
		else{

			$user_id_by_client_id = $this->user_id_by_token_or_key( 'client_id', $client_id );
			$user_id_by_client_secret = $this->user_id_by_token_or_key( 'client_secret', $client_secret );
			$user_id_by_authorization_code = $this->user_id_by_token_or_key( 'authorization_code', $code );

			if( 0 !== $user_id_by_client_id && $user_id_by_client_id !== $user_id_by_client_secret || $user_id_by_client_id !== $user_id_by_authorization_code ){
				$response_args = array( 'error' => 8 );
			}
			else{
				$authorization_code_expires = $this->get_authorization_code_expire($user_id_by_client_id);

				if( ! $authorization_code_expires || time() > $authorization_code_expires ){	// Check if tokens are not expired.
					$response_args = array( 'error' => 9, 'message' => 'Authorization code expired' );
				}
				else{

					if ( ! function_exists('get_user_by') ) { require_once (ABSPATH . WPINC . '/pluggable.php'); }
					
					$user = get_user_by( 'ID', $user_id_by_client_id );

					if( ! $user ){ 
						$response_args = array( 'error' => 10 );
					}
					else{
						$tokens = $this->create_tokens($user);
						$response_args = $tokens;
						$response_args['site_id'] = $package['site_id'];
						$response_args['security_arg'] = $package['security_arg'];
					}
				}
			}
		}

		return $this->prepare_response( $response_args );
	}

	public function refresh_tokens_endpoint($args){

		$package = $this->unpackage_request($args);

		$client_id = isset( $package['client_id'] ) ? trim( $package['client_id'] ) : '';
		$client_secret = isset( $package['client_secret'] ) ? trim( $package['client_secret'] ) : '';
		$refresh_token = isset( $package['refresh_token'] ) ? trim( $package['refresh_token'] ) : '';

		if( '' === $client_id || '' === $client_secret || '' === $refresh_token ){
			$response_args = array( 'error' => 'empty-refresh-credentials' );
		}
		else{

			$user_id_by_client_id = $this->user_id_by_token_or_key( 'client_id', $client_id );
			$user_id_by_client_secret = $this->user_id_by_token_or_key( 'client_secret', $client_secret );
			$user_id_by_refresh_token = $this->user_id_by_token_or_key( 'refresh_token', $refresh_token );

			if( 0 !== $user_id_by_client_id && ( $user_id_by_client_id !== $user_id_by_client_secret || $user_id_by_client_id !== $user_id_by_refresh_token ) ){
				$response_args = array( 'error' => 'invalid-refresh-credentials' );
			}
			else{

				if ( ! function_exists('get_user_by') ) { require_once (ABSPATH . WPINC . '/pluggable.php'); }
				$user = get_user_by( 'ID', $user_id_by_client_id );

				if( ! $user ){ 
					$response_args = array( 'error' => 'invalid-client' );
				}
				else{
					$tokens = $this->create_tokens($user);
					$response_args = $tokens;
					$response_args['site_id'] = $package['site_id'];
					$response_args['security_arg'] = $package['security_arg'];
				}
			}
		}

		return $this->prepare_response( $response_args );
	}
}
