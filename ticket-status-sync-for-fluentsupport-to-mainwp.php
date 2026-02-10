<?php
/**
 * Plugin Name: Ticket Status Sync for FluentSupport to MainWP
 * Plugin URI:  https://github.com/sflwa/ticket-status-sync-for-fluentsupport-to-mainwp
 * Description: Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.
 * Version:     1.2.4
 * Author:      South Florida Web Advisors
 * Author URI:  https://sflwa.net
 * License:     GPLv2 or later
 * Requires at least: 6.7
 * Tested up to: 6.9
 * Stable tag: 1.2.4
 * Text Domain: ticket-status-sync-for-fluentsupport-to-mainwp
 * MainWP compatible: 4.5, 6.0-er.12
 *
 * @package MainWP\Extensions\FluentSupport
 */

namespace MainWP\Extensions\FluentSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_FILE' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_DIR' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_DIR', plugin_dir_path( MAINWP_FLUENTSUPPORT_PLUGIN_FILE ) );
}

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_URL' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_URL', plugin_dir_url( MAINWP_FLUENTSUPPORT_PLUGIN_FILE ) );
}

// Notice of Use Handler.
require_once MAINWP_FLUENTSUPPORT_PLUGIN_DIR . 'sflwa-notice-handler.php';

/**
 * Class MainWP_FluentSupport_Extension_Activator
 */
class MainWP_FluentSupport_Extension_Activator {

	protected $plugin_handle    = 'ticket-status-sync-for-fluentsupport-to-mainwp';
	protected $software_version = '1.2.4';

	public function __construct() {
		spl_autoload_register( array( $this, 'autoload' ) );
		
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		
		add_filter( 'mainwp_getextensions', array( $this, 'get_this_extension' ) );
		
		if ( apply_filters( 'mainwp_activated_check', false ) !== false ) {
			$this->activate_this_plugin();
		} else {
			add_action( 'mainwp_activated', array( $this, 'activate_this_plugin' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( wp_doing_ajax() ) {
			$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
			if ( strpos( $action, 'mainwp_fluentsupport_' ) === 0 ) {
				MainWP_FluentSupport_Admin::get_instance();
			}
		}
	}
    
	public function autoload( $class_name ) {
		if ( strpos( $class_name, __NAMESPACE__ ) !== 0 ) {
			return;
		}
		$relative_class = str_replace( __NAMESPACE__ . '\\', '', $class_name );
		$file_prefix    = 'ticket-status-sync-for-fluentsupport-to-mainwp';
		$suffix         = str_replace( 'MainWP_FluentSupport', '', $relative_class ); 
		$file_suffix    = str_replace( '_', '-', strtolower( $suffix ) );
		$class_file     = MAINWP_FLUENTSUPPORT_PLUGIN_DIR . 'class' . DIRECTORY_SEPARATOR . 'class-' . $file_prefix . $file_suffix . '.php';
		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}
    
	public function get_this_extension( $pArray ) {
		$pArray[] = array(
			'plugin'     => __FILE__,
			'api'        => $this->plugin_handle,
			'mainwp'     => true,
			'callback'   => array( MainWP_FluentSupport_Overview::get_instance(), 'render_tabs' ), 
			'apiManager' => false,
			'cap'        => 'manage_options',
			'menu_title' => 'FluentSupport Tickets',
		);
		return $pArray;
	}

	public function activate_this_plugin() {
		if ( function_exists( 'mainwp_current_user_can' ) && ! mainwp_current_user_can( 'extension', $this->plugin_handle ) ) {
			return;
		}
		add_filter( 'mainwp_getmetaboxes', array( $this, 'hook_get_metaboxes' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
		add_action( 'mainwp_fluentsupport_sync_tickets_cron', array( $this, 'mainwp_fluentsupport_sync_tickets_cron' ) );
		MainWP_FluentSupport_Admin::get_instance();
		MainWP_FluentSupport_Overview::get_instance();
	}

	public function add_cron_intervals( $schedules ) {
		$schedules['five_minutes'] = array(
			'interval' => 5 * 60,
			'display'  => esc_html__( 'Every Five Minutes', 'ticket-status-sync-for-fluentsupport-to-mainwp' ),
		);
		return $schedules;
	}
    
	public function mainwp_fluentsupport_sync_tickets_cron() {
		$url  = get_option( 'mainwp_fluentsupport_site_url', '' );
		$user = get_option( 'mainwp_fluentsupport_api_username', '' );
		$pass = get_option( 'mainwp_fluentsupport_api_password', '' );
		if ( ! empty( $url ) && ! empty( $user ) && ! empty( $pass ) ) {
			MainWP_FluentSupport_Utility::api_sync_tickets( $url, $user, $pass );
		}
	}
    
	public function activate() {
		do_action( 'mainwp_activate_extention', $this->plugin_handle, array( 'software_version' => $this->software_version ) );
		if ( ! wp_next_scheduled( 'mainwp_fluentsupport_sync_tickets_cron' ) ) {
			wp_schedule_event( time(), 'five_minutes', 'mainwp_fluentsupport_sync_tickets_cron' );
		}
	}

	public function deactivate() {
		do_action( 'mainwp_deactivate_extention', $this->plugin_handle );
		$timestamp = wp_next_scheduled( 'mainwp_fluentsupport_sync_tickets_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'mainwp_fluentsupport_sync_tickets_cron' );
		}
	}

	public function hook_get_metaboxes( $metaboxes ) {
		$metaboxes[] = array(
			'id'            => 'fluentsupport-tickets-widget',
			'plugin'        => __FILE__,
			'key'           => __FILE__,
			'metabox_title' => __( 'FluentSupport Tickets', 'ticket-status-sync-for-fluentsupport-to-mainwp' ),
			'callback'      => array( MainWP_FluentSupport_Widget::get_instance(), 'render_metabox' ),
		);
		return $metaboxes;
	}

	public function enqueue_scripts( $hook ) {
		$plugin_slug = 'ticket-status-sync-for-fluentsupport-to-mainwp';
		if ( strpos( $hook, $plugin_slug ) !== false || 'toplevel_page_mainwp_tab' === $hook ) {
			wp_enqueue_script( $plugin_slug . '-js', MAINWP_FLUENTSUPPORT_PLUGIN_URL . 'js/ticket-status-sync-for-fluentsupport-to-mainwp.js', array( 'jquery' ), $this->software_version, true );
			wp_enqueue_style( $plugin_slug, MAINWP_FLUENTSUPPORT_PLUGIN_URL . 'css/ticket-status-sync-for-fluentsupport-to-mainwp.css', array(), $this->software_version );
			wp_localize_script( $plugin_slug . '-js', 'ticketStatusSync', array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'security' => wp_create_nonce( 'ticket-status-sync-for-fluentsupport-to-mainwp-nonce' ),
			) );
		}
	}
}

global $mainWPFluentSupportExtensionActivator;
$mainWPFluentSupportExtensionActivator = new MainWP_FluentSupport_Extension_Activator();
