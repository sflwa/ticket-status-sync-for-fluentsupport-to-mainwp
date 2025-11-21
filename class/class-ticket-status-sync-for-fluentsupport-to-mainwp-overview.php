<?php
/**
 * MainWP FluentSupport Overview
 * Handles the main extension page and tabs.
 */

namespace MainWP\Extensions\FluentSupport;

class MainWP_FluentSupport_Overview {

    private static $instance = null;
    private $plugin_slug = 'ticket-status-sync-for-fluentsupport-to-mainwp';

    // ... (get_instance and __construct remain the same) ...
    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // No hooks here
    }

    /**
     * Renders the tabbed extension page content (called by the Activator).
     */
    public function render_tabs() {
        $current_tab = 'overview';

        // 🔑 CRITICAL FIX: Get the dynamic page slug from the global variable
        // This is how MainWP generates the unique URL for the extension page
        global $plugin_page;
        $base_page_slug = ! empty( $plugin_page ) ? $plugin_page : 'Extensions-Mainwp-FluentSupport'; 
        
        // FIX: Nonce and Superglobal Input Warning. Sanitize input before processing.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switching via GET is for UI state, not data modification.
        $current_tab_input = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

        if ( 'overview' === $current_tab_input ) {
            $current_tab = 'overview';
        } elseif ( 'settings' === $current_tab_input ) {
            $current_tab = 'settings';
        }
        
        // Use the proper page header wrapper
        do_action( 'mainwp_pageheader_extensions', MAINWP_FLUENTSUPPORT_PLUGIN_FILE );

        ?>
		<div class="ui labeled icon inverted menu mainwp-sub-submenu" id="mainwp-fluentsupport-menu">
            <?php // FIX: Escape URLs using esc_url() ?>
			<a href="<?php echo esc_url( 'admin.php?page=' . $base_page_slug . '&tab=overview' ); ?>" class="item <?php echo ( $current_tab == 'overview' ) ? 'active' : ''; ?>"><i class="tasks icon"></i> <?php esc_html_e( 'Tickets Overview', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></a>
			<a href="<?php echo esc_url( 'admin.php?page=' . $base_page_slug . '&tab=settings' ); ?>" class="item <?php echo ( $current_tab == 'settings' || $current_tab == '' ) ? 'active' : ''; ?>"><i class="file alternate outline icon"></i> <?php esc_html_e( 'Settings', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?></a>
		</div>

        <div id="mainwp-fluentsupport-extension" class="wrap">
            <div id="mainwp-fluentsupport-message" style="display:none; margin: 10px 0;"></div>
            
            <?php 
            // Call the correct rendering method from the Admin class
            if ( $current_tab == 'settings' ) {
                MainWP_FluentSupport_Admin::get_instance()->render_settings_tab();
            } else {
                MainWP_FluentSupport_Admin::get_instance()->render_overview_tab();
            }
            ?>
        </div>
        <?php
        
        // Use the proper page footer wrapper
        do_action( 'mainwp_pagefooter_extensions', MAINWP_FLUENTSUPPORT_PLUGIN_FILE );
    }
}

