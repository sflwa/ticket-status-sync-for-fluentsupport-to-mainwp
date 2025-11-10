<?php
/**
 * Plugin Name: MainWP FluentSupport Extension
 * Plugin URI:  https://github.com/sflwa/fs-mainwp
 * Description: Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.
 * Version:     1.2.1
 * Author:      South Florida Web Advisors
 * Author URI:  https://sflwa.net
 * License: GPLv2 or later
 * Requires at least: 6.7
 * Tested up to: 6.8
 * Stable tag: 1.2.1
 * Text Domain: mainwp-fluentsupport
 * MainWP compatible: 4.5
 */

// Use the required namespace structure for all custom classes
namespace MainWP\Extensions\FluentSupport;

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_FILE' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_DIR' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_DIR', plugin_dir_path( MAINWP_FLUENTSUPPORT_PLUGIN_FILE ) );
}

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_URL' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_URL', plugin_dir_url( MAINWP_FLUENTSUPPORT_PLUGIN_FILE ) );
}

// -------------------------------------------------------------
// The Main Activator Class (Required by MainWP Framework)
// -------------------------------------------------------------
class MainWP_FluentSupport_Extension_Activator {

	protected $mainwpMainActivated = false;
	protected $childEnabled        = false;
	protected $childKey            = false;
	protected $childFile;
	protected $plugin_handle    = 'mainwp-fluentsupport';
	protected $product_id       = 'MainWP FluentSupport Extension';
	protected $software_version = '1.2.1'; // Version incremented
// ... (rest of the file content remains the same)
