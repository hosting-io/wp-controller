<?php
/**
 * [[Description]]
 *
 * @package 	WordPress_Multisite_Controller
 * @subpackage 	WordPress_Multisite_Controller/inc
 */
class Wpmc_REST_Controller extends WP_REST_Controller {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @private
	 */
	public function __construct() {

		if( ! class_exists('Wpmc_Authorize_Access_Handler') ){ require WPMC_INCLUDES_PATH . '/class-wpmc-authorize-access-handler.php'; }
		
		$this->rest_base = WPMC_SLUG;
	}

	/**
	 * Register the routes for the objects of the controller
	 *
	 * @return [[Type]] [[Description]]
	 */
	public function register_routes() {

		register_rest_route( $this->rest_base, 'ping', array(
			array(
				'methods'   => WP_REST_Server::EDITABLE,
				'callback'  => array( $this, 'ping' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->rest_base, 'entry', array(
			array(
				'methods'   => WP_REST_Server::EDITABLE,
				'callback'  => array( $this, 'entry' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->rest_base, 'authorize', array(
			array(
				'methods'   => WP_REST_Server::EDITABLE,
				'callback'  => array( $this, 'authorize' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->rest_base, 'tokens', array(
			array(
				'methods'   => WP_REST_Server::EDITABLE,
				'callback'  => array( $this, 'tokens' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->rest_base, 'refresh_tokens', array(
			array(
				'methods'   => WP_REST_Server::EDITABLE,
				'callback'  => array( $this, 'refresh_tokens' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->rest_base, 'access', array(
			array(
				'methods'   => WP_REST_Server::READABLE,
				'callback'  => array( $this, 'access' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->rest_base, 'login', array(
			array(
				'methods'   => WP_REST_Server::EDITABLE,
				'callback'  => array( $this, 'login' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->rest_base, 'updates', array(
			array(
				'methods'   => WP_REST_Server::EDITABLE,
				'callback'  => array( $this, 'updates' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));

		register_rest_route( $this->rest_base, 'update_now', array(
			array(
				'methods'   => WP_REST_Server::EDITABLE,
				'callback'  => array( $this, 'update_now' ),
				'args'      => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));
	}

	public function ping($request){
		$o = new Wpmc_Authorize_Access_Handler();
		return $o->ping_endpoint( $request->get_params() );
	}

	public function entry($request){
		$o = new Wpmc_Authorize_Access_Handler();
		return $o->entry_endpoint( $request->get_params() );
	}

	public function authorize($request){
		$o = new Wpmc_Authorize_Access_Handler();
		return $o->authorize_endpoint( $request->get_params() );
	}

	public function tokens($request){
		$o = new Wpmc_Authorize_Access_Handler();
		return $o->tokens_endpoint( $request->get_params() );
	}

	public function refresh_tokens($request){
		$o = new Wpmc_Authorize_Access_Handler();
		return $o->refresh_tokens_endpoint( $request->get_params() );
	}

	public function updates($request){
		$o = new Wpmc_Client_Access_Handler();
		return $o->available_updates_endpoint( $request->get_params() );
	}

	public function update_now($request){
		$o = new Wpmc_Client_Access_Handler();
		return $o->available_update_now_endpoint( $request->get_params() );
	}

	public function access($request){
		$o = new Wpmc_Client_Access_Handler();
		$o->access_endpoint( $request->get_params() );
		exit;
	}

	public function login($request){
		$o = new Wpmc_Client_Access_Handler();
		return $o->login_endpoint( $request->get_params() );
	}
}
