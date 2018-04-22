<?php
/**
 * [Description]
 *
 * @package 	WordPress_Multisite_Controller
 * @subpackage 	WordPress_Multisite_Controller/inc
 */
class Wpmc_Transients {

	/**
	 * [Description]
	 *
	 * @var boolean
	 */
	public static $enabled = WPMC_ENABLE_TRANSIENTS;

	/**
	 * [Description]
	 */
	public static function init_all() {

	}

	/**
	 * [[Description]]
	 */
	public static function clean_all() {
		delete_transient( 'wpmc_themes_updates_info' );
		delete_transient( 'wpmc_plugins_updates_info' );
		delete_transient( 'wpmc_core_update_info' );
	}
}
