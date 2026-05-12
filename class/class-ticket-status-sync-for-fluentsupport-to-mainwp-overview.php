<?php
/**
 * MainWP FluentSupport Overview
 * Handles the main extension page, tabs, and navigation.
 */

namespace MainWP\Extensions\FluentSupport;

class MainWP_FluentSupport_Overview {

    private static $instance = null;

    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {}

    /**
     * Renders the tabbed extension page content.
     */
    public function render_tabs() {
        // Default tab
        $current_tab = 'overview';

        global $plugin_page;
        $base_page_slug = ! empty( $plugin_page ) ? $plugin_page : 'Extensions-Mainwp-FluentSupport'; 
        
        // Sanitize and determine current tab
        if ( isset( $_GET['tab'] ) ) {
            $current_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
        }
        
        // Page header wrapper
        do_action( 'mainwp_pageheader_extensions', MAINWP_FLUENTSUPPORT_PLUGIN_FILE );

        ?>
		<div class="ui labeled icon inverted menu mainwp-sub-submenu" id="mainwp-fluentsupport-menu">
			<a href="<?php echo esc_url( 'admin.php?page=' . $base_page_slug . '&tab=overview' ); ?>" class="item <?php echo ( $current_tab == 'overview' ) ? 'active' : ''; ?>">
                <i class="tasks icon"></i> <?php esc_html_e( 'Tickets Overview', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?>
            </a>
            <a href="<?php echo esc_url( 'admin.php?page=' . $base_page_slug . '&tab=log' ); ?>" class="item <?php echo ( $current_tab == 'log' ) ? 'active' : ''; ?>">
                <i class="history icon"></i> <?php esc_html_e( 'Sync Log', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?>
            </a>
			<a href="<?php echo esc_url( 'admin.php?page=' . $base_page_slug . '&tab=settings' ); ?>" class="item <?php echo ( $current_tab == 'settings' ) ? 'active' : ''; ?>">
                <i class="cog icon"></i> <?php esc_html_e( 'Settings', 'ticket-status-sync-for-fluentsupport-to-mainwp' ); ?>
            </a>
		</div>

        <div id="mainwp-fluentsupport-extension" class="wrap">
            <div id="mainwp-fluentsupport-message" style="display:none; margin: 10px 0;"></div>
            
            <?php 
            // Routing to specific tab renderers
            switch ( $current_tab ) {
                case 'settings':
                    MainWP_FluentSupport_Admin::get_instance()->render_settings_tab();
                    break;
                case 'log':
                    MainWP_FluentSupport_Admin::get_instance()->render_log_tab();
                    break;
                case 'overview':
                default:
                    MainWP_FluentSupport_Admin::get_instance()->render_overview_tab();
                    break;
            }
            ?>
        </div>
        <?php
        
        // Page footer wrapper
        do_action( 'mainwp_pagefooter_extensions', MAINWP_FLUENTSUPPORT_PLUGIN_FILE );
    }
}
