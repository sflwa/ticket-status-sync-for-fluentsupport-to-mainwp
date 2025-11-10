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

	public function __construct() {
		$this->childFile = __FILE__;

		// Critical for MainWP Autoloading of class files in the /class/ directory
		spl_autoload_register( array( $this, 'autoload' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Hook to register the extension with MainWP's extension manager
		add_filter( 'mainwp_getextensions', array( &$this, 'get_this_extension' ) );
        
		// Check if MainWP is active, then initialize the extension
		$this->mainwpMainActivated = apply_filters( 'mainwp_activated_check', false );
		if ( $this->mainwpMainActivated !== false ) {
			$this->activate_this_plugin();
		} else {
			add_action( 'mainwp_activated', array( &$this, 'activate_this_plugin' ) );
		}

		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
    
    // Autoload function, loads classes/class-mainwp-fluentsupport-XXXX.php
	public function autoload( $class_name ) {
		if ( 0 === strpos( $class_name, 'MainWP\Extensions\FluentSupport' ) ) {
			$class_name = str_replace( 'MainWP\Extensions\FluentSupport\\', '', $class_name );
		} else {
			return;
		}

		if ( 0 !== strpos( $class_name, 'MainWP_FluentSupport' ) ) {
			return;
		}
		$class_name = str_replace( '_', '-', strtolower( $class_name ) );
		$class_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace( basename( __FILE__ ), '', plugin_basename( __FILE__ ) ) . 'class' . DIRECTORY_SEPARATOR . 'class-' . $class_name . '.php';
		
		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}
    
    // Defines the extension for MainWP's extension manager
	public function get_this_extension( $pArray ) {
		$pArray[] = array(
			'plugin'     => __FILE__,
			'api'        => $this->plugin_handle,
			'mainwp'     => true,
            // Uses the Overview class to render the page content
			'callback'   => array( MainWP_FluentSupport_Overview::get_instance(), 'render_tabs' ), 
			'apiManager' => false, // Disables license checking for self-developed extension
            'cap'        => 'manage_options', // Ensures Admins have access
            'menu_title' => 'FluentSupport Tickets',
		);
		return $pArray;
	}

	public function activate_this_plugin() {
        // Ensures MainWP classes are initialized
		if ( function_exists( 'mainwp_current_user_can' ) && ! mainwp_current_user_can( 'extension', 'mainwp-fluentsupport' ) ) {
			return;
		}
        
        // NEW: Add metabox filter to register the widget
        add_filter( 'mainwp_getmetaboxes', array( &$this, 'hook_get_metaboxes' ) );
        
        // **REMOVED: Hook to trigger sync after MainWP site synchronization (to prevent slowdown)**
        
        // **NEW: Add WP-Cron hooks for background synchronization**
        add_filter( 'cron_schedules', array( $this, 'add_five_minute_cron_interval' ) );
        add_action( 'mainwp_fluentsupport_sync_tickets_cron', array( $this, 'mainwp_fluentsupport_sync_tickets_cron' ) );

        // Initialize core components
		MainWP_FluentSupport_Admin::get_instance();
        MainWP_FluentSupport_Overview::get_instance();
	}

    /**
     * Adds a 5-minute interval to the cron schedules.
     */
    public function add_five_minute_cron_interval( $schedules ) {
        $schedules['five_minutes'] = array(
            'interval' => 5 * 60, // 5 minutes in seconds
            'display'  => esc_html__( 'Every Five Minutes', 'mainwp-fluentsupport' ),
        );
        return $schedules;
    }
    
    /**
     * The scheduled cron event handler. Triggers the ticket synchronization.
     */
    public function mainwp_fluentsupport_sync_tickets_cron() {
        // Ensure credentials are set before attempting to sync
        $support_site_url = rtrim( get_option( 'mainwp_fluentsupport_site_url', '', false ), '/' );
        $api_username     = get_option( 'mainwp_fluentsupport_api_username', '', false );
        $api_password     = get_option( 'mainwp_fluentsupport_api_password', '', false );

        if ( ! empty( $support_site_url ) && ! empty( $api_username ) && ! empty( $api_password ) ) {
             // Perform the heavy sync lifting
             MainWP_FluentSupport_Utility::api_sync_tickets( 
                $support_site_url, 
                $api_username, 
                $api_password 
             );
        }
    }
    
    // **REMOVED: hook_mainwp_site_synced() method is no longer needed.**
    
	public function admin_notices() {
        // ... (standard admin notices logic)
	}

	public function activate() {
        // Calls the MainWP activation hook
		$options = array(
			'product_id'       => $this->product_id,
			'software_version' => $this->software_version,
		);
		do_action( 'mainwp_activate_extention', $this->plugin_handle, $options );
        
        // CRON: Schedule the recurring event on activation
        if ( ! wp_next_scheduled( 'mainwp_fluentsupport_sync_tickets_cron' ) ) {
            // Schedule the event to run every 5 minutes
            wp_schedule_event( time(), 'five_minutes', 'mainwp_fluentsupport_sync_tickets_cron' ); 
        }
	}

	public function deactivate() {
        // Calls the MainWP deactivation hook
		do_action( 'mainwp_deactivate_extention', $this->plugin_handle );
        
        // CRON: Clear the scheduled event on deactivation
        $timestamp = wp_next_scheduled( 'mainwp_fluentsupport_sync_tickets_cron' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'mainwp_fluentsupport_sync_tickets_cron' );
        }
	}
    
    // Utility getter methods needed by other classes
	public function get_child_key() {
		return $this->childKey;
	}

	public function get_child_file() {
		return $this->childFile;
	}
    
    /**
	 * Adds metabox (widget) on the MainWP Dashboard overview page via the 'mainwp_getmetaboxes' filter.
	 *
	 * @param array $metaboxes Array containing metaboxes data.
	 *
	 * @return array $metaboxes Updated array that contains metaboxes data.
	 */
	public function hook_get_metaboxes( $metaboxes ) {
        
		if ( ! is_array( $metaboxes ) ) {
			$metaboxes = array();
		}

		$metaboxes[] = array(
			'id'            => 'fluentsupport-tickets-widget',
			'plugin'        => $this->childFile,
			'key'           => $this->childFile, // Use childFile as the key workaround for self-developed extensions
			'metabox_title' => __( 'FluentSupport Tickets', 'mainwp-fluentsupport' ),
			'callback'      => array( MainWP_FluentSupport_Widget::get_instance(), 'render_metabox' ),
		);

		return $metaboxes;
	}

    // Enqueue scripts
    public function enqueue_scripts( $hook ) {
        $plugin_slug = 'mainwp-fluentsupport';
        // Enqueue the JS if on the main dashboard page OR the extension page
        $is_extension_page = strpos( $hook, 'Extensions-Fs-Mainwp-Main' ) !== false || strpos( $hook, 'mainwp-fluentsupport' ) !== false;
        $is_mainwp_dashboard = $hook === 'toplevel_page_mainwp_tab'; // The main Dashboard overview hook
        
        if ( $is_extension_page || $is_mainwp_dashboard ) {
            
            wp_enqueue_script( 
                $plugin_slug . '-js', 
                plugin_dir_url( __FILE__ ) . 'js/mainwp-fluentsupport.js', 
                array( 'jquery' ), 
                $this->software_version, 
                true 
            );
			
			  wp_enqueue_style( 
                $plugin_slug,
                plugin_dir_url( __FILE__ ) . 'css/mainwp-fluentsupport.css', 
                array(), 
                $this->software_version, 
                true 
            );
            
            wp_localize_script( $plugin_slug . '-js', 'mainwpFluentSupport', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mainwp-fluentsupport-nonce' ),
                'action'  => 'mainwp_fluentsupport_fetch_tickets'
            ) );

            // CORRECTED: Using 'mainwp-css' handle now.
            wp_add_inline_style( 'mainwp-css', '#mainwp-fluentsupport-extension {margin:20px;} #fluentsupport-ticket-data .loading-row { background: #f9f9f9; } #fluentsupport-ticket-data .error-row { background: #fee; color: red; }' );
        }
    }
}

// Global instantiation, required by MainWP framework
global $mainWPFluentSupportExtensionActivator;
$mainWPFluentSupportExtensionActivator = new MainWP_FluentSupport_Extension_Activator();
