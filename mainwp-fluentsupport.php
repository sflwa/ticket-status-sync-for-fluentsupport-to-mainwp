<?php
/*
  Plugin Name: MainWP FluentSupport Extension
  Plugin URI: https://mainwp.dev/
  Description: Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.
  Version: 1.1.9
  Author: Your Name
  Author URI: https://yourwebsite.com
  
  Requires at least: 4.0
  Tested up to: 6.5
  MainWP compatible: 4.5
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
	protected $software_version = '1.1.8';

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
        // Initialize core components
		MainWP_FluentSupport_Admin::get_instance();
        MainWP_FluentSupport_Overview::get_instance();
	}

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
	}

	public function deactivate() {
        // Calls the MainWP deactivation hook
		do_action( 'mainwp_deactivate_extention', $this->plugin_handle );
	}
    
    // Utility getter methods needed by other classes
	public function get_child_key() {
		return $this->childKey;
	}

	public function get_child_file() {
		return $this->childFile;
	}

    // Enqueue scripts
    public function enqueue_scripts( $hook ) {
        $plugin_slug = 'mainwp-fluentsupport';
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        // 🔑 FIX: Corrected the stylesheet handle from 'mainwp-style' to 'mainwp-css'
        if ( $page === 'Extensions-Fs-Mainwp-Main' || strpos( $page, $plugin_slug ) !== false ) {
            
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

