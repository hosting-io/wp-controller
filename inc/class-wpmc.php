<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @package 	WordPress_Multisite_Controller
 * @subpackage 	WordPress_Multisite_Controller/inc
 */
class Wpmc {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @access   protected
	 * @var      Wpmc_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Are enabled WPMC REST API endpoints.
	 *
	 * @var      boolean
	 */
	protected static $enabled_rest_api = null;

	/**
	 * Get boolean value that shows if REST API is enabled or not.
	 *
	 * @param  object $controller = 'n/a' WP REST API controller reference.
	 * @return boolean REST API is enabled or not.
	 */
	public static function enabled_rest_api( &$controller = 'n/a' ) {

		if ( null === self::$enabled_rest_api ) {

			self::$enabled_rest_api = false;

			if ( ! class_exists( 'WP_REST_Controller' ) ) {
				require_once WPMC_INCLUDES_PATH . '/lib/wp-api/lib/endpoints/class-wp-rest-controller.php';
			}
			if ( class_exists( 'WP_REST_Controller' ) ) {
				require_once WPMC_INCLUDES_PATH . '/class-wpmc-rest-controller.php';
				if ( 'n/a' !== $controller ) {
					$controller = new Wpmc_REST_Controller;
				}
				self::$enabled_rest_api = true;
			}
		} elseif ( true === self::$enabled_rest_api ) {
			if ( 'n/a' !== $controller ) {
				$controller = new Wpmc_REST_Controller;
			}
		}
		return self::$enabled_rest_api;
	}

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {

		$this->plugin_name = WPMC_SLUG;
		$this->version = WPMC_VERSION;

		$this->load_dependencies();
		$this->set_locale();

		if ( is_admin() ) {
			$this->define_admin_hooks();
		}

		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wpmc_Loader. Orchestrates the hooks of the plugin.
	 * - Wpmc_I18n. 	Defines internationalization functionality.
	 * - Wpmc_Admin. 	Defines all hooks for the admin area.
	 * - Wpmc_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once WPMC_INCLUDES_PATH . '/class-wpmc-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once WPMC_INCLUDES_PATH . '/class-wpmc-i18n.php';

		if ( is_admin() ) {
			/**
			 * The class responsible for defining all actions that occur in the admin area.
			 */
			require_once WPMC_INCLUDES_PATH . '/class-wpmc-admin.php';
		}

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once WPMC_INCLUDES_PATH . '/class-wpmc-public.php';

		$this->loader = new Wpmc_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wpmc_I18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new Wpmc_I18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Wpmc_Admin( $this->get_plugin(), $this->get_version() );
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );
		$this->loader->add_filter( 'pre_set_site_transient_update_core', $plugin_admin, 'pre_update_core' );
		$this->loader->add_filter( 'pre_set_site_transient_update_themes', $plugin_admin, 'pre_update_themes' );
		$this->loader->add_filter( 'pre_set_site_transient_update_plugins', $plugin_admin, 'pre_update_plugins' );
		$this->loader->add_action( 'upgrader_process_complete', $plugin_admin, 'upgrader_process_complete', 10, 2 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Wpmc_Public( $this->get_plugin(), $this->get_version() );

		if( self::enabled_rest_api() ){
			$this->loader->add_action( 'rest_api_init', $plugin_public, 'register_rest_routes' );
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    Wpmc_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
