<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @package 	WordPress_Multisite_Controller
 * @subpackage 	WordPress_Multisite_Controller/inc
 */
class Wpmc_Deactivator {

	/**
	 * [[Description]]
	 */
	public static function deactivate() {
		wpmc_delete_all_options();
		Wpmc_Transients::clean_all();
	}
}
