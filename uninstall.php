<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    	WordPress_Multisite_Controller
 */

if( ! defined('ABSPATH') && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
require_once plugin_dir_path( __FILE__ ) . 'inc/class-wpmc-transients.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/functions.php';
wpmc_on_uninstall();
