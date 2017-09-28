<?php
/**
 * Plugin's definitions file
 *
 * @package 	WordPress_Multisite_Controller
 * @subpackage 	WordPress_Multisite_Controller/inc
 */

if ( ! defined( 'WPMC_VERSION' ) ) { define( 'WPMC_VERSION', '3.0.0' ); }
if ( ! defined( 'WPMC_SLUG' ) ) { define( 'WPMC_SLUG', 'wp-management-controller' ); }
if ( ! defined( 'WPMC_REST_API_BASE' ) ) { define( 'WPMC_REST_API_BASE', get_home_url( null, 'wp-json/' . WPMC_SLUG ) ); }
if ( ! defined( 'WPMC_ROOT' ) ) { define( 'WPMC_ROOT', dirname( dirname( __FILE__ ) ) ); }
if ( ! defined( 'WPMC_URL' ) ) { define( 'WPMC_URL',  plugin_dir_url( dirname( __FILE__ ) ) ); }
if ( ! defined( 'WPMC_INCLUDES_PATH' ) ) { define( 'WPMC_INCLUDES_PATH', WPMC_ROOT . '/inc' ); }