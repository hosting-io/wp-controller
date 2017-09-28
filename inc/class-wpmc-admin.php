<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package 	WordPress_Multisite_Controller
 * @subpackage 	WordPress_Multisite_Controller/inc
 */
class Wpmc_Admin {

	/**
	 * [[Description]]
	 *
	 * @var      boolean
	 */
	private $wp_nonce = false;

	/**
	 * The ID of this plugin.
	 *
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Nonce key for AJAX request authorization.
	 *
	 * @access   private
	 * @var      string   $nonce_key    Nonce key for AJAX request authorization.
	 */
	private $nonce_key = 'wp_rest';

	/**
	 * Slug of the plugin screen.
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * [[Description]]
	 *
	 * @return [[Type]] [[Description]]
	 */
	private function get_wp_nonce() {
		if ( ! $this->wp_nonce ) {
			$this->wp_nonce = wp_create_nonce( $this->nonce_key );
		}
		return $this->wp_nonce;
	}
}
