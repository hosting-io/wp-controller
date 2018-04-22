<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package 	WordPress_Multisite_Controller
 * @subpackage 	WordPress_Multisite_Controller/inc
 */
class Wpmc_Activator {

	/**
	 * [[Description]]
	 */
	public static function activate() {
		update_option( 'wp_management_controller_version', WPMC_VERSION );
		Wpmc_Transients::init_all();
	}

}
