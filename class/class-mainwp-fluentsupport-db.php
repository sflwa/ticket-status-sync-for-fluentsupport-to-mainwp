<?php
/**
 * MainWP FluentSupport DB
 *
 * This class handles the DB process, primarily for structural integrity.
 */

namespace MainWP\Extensions\FluentSupport;

class MainWP_FluentSupport_DB {

	private static $instance = null;
    
    private $table_name = 'mainwp_fluentsupport_tickets';
    private $version = '1.0';
    
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
	}

	/**
	 * Install Extension (Create custom tables if necessary).
	 */
	public function install() {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->table_name;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Use a consistent, standard collation for string comparisons
        // CRITICAL FIX: Explicitly define the collation to prevent Illegal mix of collations error
        $collation_sql = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';

        // Check if the table already exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {

            // SQL for creating the new table
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                ticket_id bigint(20) NOT NULL,
                client_site_id bigint(20) DEFAULT 0,
                client_site_url TEXT $collation_sql NOT NULL,
                ticket_title longtext $collation_sql NOT NULL,
                ticket_status varchar(20) NOT NULL,
                ticket_priority varchar(20) NOT NULL,
                ticket_url text $collation_sql NOT NULL,
                last_update datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY ticket_id (ticket_id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
            
            // Store the table version
            update_option( $this->table_name . '_db_version', $this->version );
        }
	}
    
    /**
     * Get the full table name with prefix.
     */
    public function get_full_table_name() {
        global $wpdb;
        return $wpdb->prefix . $this->table_name;
    }
}
