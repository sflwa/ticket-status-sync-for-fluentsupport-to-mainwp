<?php
/**
 * MainWP FluentSupport Utility
 * Includes REST API Call Logic and DB Sync.
 */

namespace MainWP\Extensions\FluentSupport;

// Note: No 'use' statements needed as MainWP_FluentSupport_DB is in the same namespace.

class MainWP_FluentSupport_Utility {

	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
    
    // Get the correct filename for MainWP hooks
	public static function get_file_name() {
		global $mainWPFluentSupportExtensionActivator;
		return $mainWPFluentSupportExtensionActivator->get_child_file();
	}
    
    /**
     * Get Websites
     * Gets all child sites by querying the mainwp_wp database table directly.
     * * @param int|null $site_id Optional. The ID of a single site to fetch.
     * @return array Array of associative arrays containing 'id', 'url', and 'name'.
     */
	public static function get_websites( $site_id = null ) {
		global $wpdb;
        
        // Use the WordPress global prefix for MainWP tables
        $table_name = $wpdb->prefix . 'mainwp_wp'; 
        $results = array();
        
        if ( null !== $site_id ) {
            // Fetch a single site
            $sql = $wpdb->prepare(
                "SELECT id, url, name FROM {$table_name} WHERE id = %d",
                $site_id
            );
            $result = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, PluginCheck.Security.DirectDBorUnescapedParameter -- Custom table query necessary for extension logic.
            if ( $result ) {
                $results[] = $result;
            }
        } else {
            // Fetch all sites
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is fixed and contains no user input.
            $sql = "SELECT id, url, name FROM {$table_name}";
            $results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, PluginCheck.Security.DirectDBorUnescapedParameter -- Custom table query necessary for extension logic.
        }

		return is_array($results) ? $results : array();
	}
    
    /**
     * Helper function to execute a WP Remote Get request with auth.
     */
    private static function execute_remote_get( $endpoint, $username, $password, $timeout = 30 ) {
         $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
            ),
            'timeout' => $timeout,
        );
        return wp_remote_get( $endpoint, $args );
    }

    /**
     * Maps MainWP child site URLs to their IDs.
     * Creates the map of [mainwp_wp.siteurl (slash-suffixed) => Site ID].
     * CRITICAL FIX: Explicitly forces a trailing slash and trims whitespace on the MainWP URL.
     * * * * @return array Map of slash-suffixed URL string to Site ID.
     */
    public static function map_client_sites_by_url() {
        $all_websites = MainWP_FluentSupport_Utility::get_websites();
		$site_count = is_array($all_websites) ? count($all_websites) : 0;
        $url_map = array();

        if ( is_array( $all_websites ) ) {
            foreach ( $all_websites as $site ) { 
                // $site['url'] corresponds to mainwp_wp.siteurl
                // Trim whitespace, force URL to end with a trailing slash to match stored format
                $normalized_url = rtrim( trim( $site['url'] ), '/' ) . '/'; 
                $url_map[ $normalized_url ] = $site['id'];
            }
        }

        return $url_map;
    }

    /**
     * Gets the MainWP Site ID for a given URL using the pre-computed map.
     *
     * @param string $website_url The URL from the ticket custom field (which becomes client_site_url).
     * @param array $site_map Pre-computed map of URL => ID.
     * @return int The MainWP client site ID or 0 if not found.
     */
    public static function get_site_id_by_url( $website_url, $site_map ) {
        if ( empty( $website_url ) || ! is_array( $site_map ) ) {
            return 0;
        }

        // 1. Clean (strip HTML) the incoming ticket URL
        $clean_url = wp_strip_all_tags( $website_url );
        
        // 2. Trim whitespace and add trailing slash to align with the MainWP stored format
        $normalized_url = rtrim( trim( $clean_url ), '/' ) . '/';
        
        return isset( $site_map[ $normalized_url ] ) ? $site_map[ $normalized_url ] : 0;
    }


    /**
     * Tests the FluentSupport REST API connection.
     */
    public static function api_test_connection( $site_url, $username, $password ) {
        $api_endpoint = rtrim($site_url, '/') . '/wp-json/fluent-support/v2/tickets';
        
        $response = self::execute_remote_get( $api_endpoint, $username, $password, 15 );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'Connection Test Error: ' . $response->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        
        if ( $response_code === 200 ) {
            return array( 'success' => true );
        } else if ( $response_code === 401 ) {
            return array( 'error' => 'Authentication failed. Check API Username and Application Password.' );
        } else if ( $response_code === 404 ) {
            return array( 'error' => 'FluentSupport REST API endpoint not found (Code 404). Ensure FluentSupport is active.' );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        $message = isset($data['message']) ? $data['message'] : 'Unknown error.';

        return array( 'error' => "API Test failed (Code: {$response_code}). Message: {$message}" );
    }

    /**
     * Synchronizes all tickets from the Support Site API to the local database.
     */
    public static function api_sync_tickets( $site_url, $username, $password ) {
        global $wpdb;
        // FIX: Removed redundant namespace prefix
        $db = MainWP_FluentSupport_DB::get_instance();
        $table_name = $db->get_full_table_name();

        $base_endpoint = rtrim($site_url, '/') . '/wp-json/fluent-support/v2';
        $current_page = 1;
        $total_pages = 1;
        $synced_count = 0;
        $start_time = microtime(true);
        
        // NEW: Pre-compute the URL to ID map once before the main loop
        $site_url_to_id_map = self::map_client_sites_by_url(); 
        
        // Define data formats for wpdb::insert/update (CRITICAL FIX)
        $format_array = array(
            '%d', // ticket_id (bigint)
            '%d', // client_site_id (bigint)
            '%s', // client_site_url (TEXT)
            '%s', // ticket_title (longtext)
            '%s', // ticket_status (varchar)
            '%s', // ticket_priority (varchar)
            '%s', // ticket_url (text)
            '%s', // last_update (datetime)
            '%s', // created_at (datetime)
        );

        do {
            // ==========================================================
            // STEP 1: Get list of tickets for current page (Bulk Call)
            // Pull all tickets, not just open ones
            // ==========================================================
            $bulk_endpoint = $base_endpoint . '/tickets?filters[status_type]=all&per_page=100&page=' . $current_page; 
            $response = self::execute_remote_get( $bulk_endpoint, $username, $password );
            
            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
                 return array( 'success' => false, 'synced' => $synced_count, 'error' => 'REST API Bulk Call Failed on page ' . $current_page );
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            
            if ( !isset($data['tickets']['data']) || !is_array($data['tickets']['data']) ) {
                 break; // Exit loop if data structure is wrong
            }

            $ticket_list = $data['tickets']['data'];
            
            if (isset($data['tickets']['last_page'])) {
                $total_pages = $data['tickets']['last_page'];
            }
            
            // ==========================================================
            // STEP 2: Loop through tickets and call singular endpoint for detail
            // ==========================================================
            
            foreach ( $ticket_list as $ticket_summary ) {
                
                if ( ! is_array($ticket_summary) || !isset($ticket_summary['id']) ) {
                    continue; 
                }
                
                $ticket_id = $ticket_summary['id'];
                
                // CRITICAL FIX: Use the PLURAL form /tickets/<ID> confirmed by API route list
                $singular_endpoint = $base_endpoint . '/tickets/' . $ticket_id; 
                
                // FIX: REMOVED the problematic '?with[]=custom_fields' parameter

                $single_response = self::execute_remote_get( $singular_endpoint, $username, $password );
                
                if ( is_wp_error( $single_response ) || wp_remote_retrieve_response_code( $single_response ) !== 200 ) {
                    continue; 
                }
                
                $single_data = json_decode( wp_remote_retrieve_body( $single_response ), true );
                
                // CRITICAL FIX: Access the ticket data via the 'ticket' key
                if ( !isset($single_data['ticket']) || !is_array($single_data['ticket']) ) {
                     continue; 
                }
                
                $ticket = $single_data['ticket']; // The correct full ticket object
                
                if ( ! is_array($ticket) || !isset($ticket['id']) ) {
                    // Double check if the internal ticket object is valid
                    continue; 
                }
                
                // --- Extract Data and Site URL ---
                $raw_website_url = '';
                
                // CRITICAL FIX: Directly access the custom field by key as confirmed by manual test
                if ( isset($ticket['custom_fields']) && isset($ticket['custom_fields']['cf_website_url']) ) {
                    $raw_website_url = $ticket['custom_fields']['cf_website_url'];
                }
                
                // Get the MainWP Site ID using the new robust lookup function
                $client_site_id = self::get_site_id_by_url( $raw_website_url, $site_url_to_id_map );
                
                // Clean the URL for storage (strip HTML, trim whitespace, and ADD trailing slash)
                $website_url = wp_strip_all_tags( $raw_website_url );
                $website_url = rtrim( trim( $website_url ), '/' ) . '/'; // Explicitly add trailing slash for storage

                
                // --- Prepare DB Data ---
                $last_update_str = isset($ticket['updated_at']) ? $ticket['updated_at'] : current_time('mysql');
                $created_at_str = isset($ticket['created_at']) ? $ticket['created_at'] : current_time('mysql');
                
                $last_update_mysql = gmdate('Y-m-d H:i:s', strtotime($last_update_str) ?: time());
                $created_at_mysql = gmdate('Y-m-d H:i:s', strtotime($created_at_str) ?: time());


                $data_to_save = array(
                    'ticket_id'      => (int)$ticket_id, 
                    'client_site_id' => (int)$client_site_id,
                    'client_site_url' => $website_url, // This should now contain the slash-suffixed URL
                    'ticket_title'   => isset($ticket['title']) ? $ticket['title'] : 'N/A',
                    'ticket_status'  => substr(isset($ticket['status']) ? $ticket['status'] : 'N/A', 0, 20),
                    'ticket_priority'=> substr(isset($ticket['priority']) ? $ticket['priority'] : 'N/A', 0, 20),
                    'ticket_url'     => rtrim($site_url, '/') . '/wp-admin/admin.php?page=fluent-support#/tickets/' . $ticket_id . '/view',
                    'last_update'    => $last_update_mysql,
                    'created_at'     => $created_at_mysql,
                );
                
                // --- INSERT or UPDATE Logic (UPSERT) ---
                
                $existing_ticket = $wpdb->get_row( $wpdb->prepare( 
                    "SELECT id FROM {$table_name} WHERE ticket_id = %d", 
                    $ticket_id 
                ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, PluginCheck.Security.DirectDBorUnescapedParameter -- Necessary for upsert logic; data caching is handled by the overall sync process.

                if ( $existing_ticket ) {
                    // Update existing record
                    // CRITICAL FIX: Added format array for security and reliability
                    $wpdb->update( $table_name, $data_to_save, array( 'ticket_id' => $ticket_id ), $format_array, array('%d') );
                } else {
                    // Insert new record
                    // CRITICAL FIX: Added format array for security and reliability
                    $wpdb->insert( $table_name, $data_to_save, $format_array );
                }

                $synced_count++;
            }

            $current_page++;

        } while ( $current_page <= $total_pages && (microtime(true) - $start_time) < 500 ); // Max 500s limit

        // **NEW CODE:** Save the last successful sync timestamp
        update_option( 'mainwp_fluentsupport_last_sync', current_time( 'timestamp' ) );

        return array( 'success' => true, 'synced' => $synced_count );
    }

    /**
     * Fetches ticket data from the local database for display.
     * * @param array $args {
     * @type array $status Array of status strings to filter by. Default: empty (all tickets).
     * @type int $limit Maximum number of results to return. Default: 10.
     * @type int $site_id Site ID to filter tickets for. Default: 0 (all sites).
     * }
     * @return array Ticket results array.
     */
    public static function api_get_tickets_from_db( $args = array() ) {
        global $wpdb;
        $db = MainWP_FluentSupport_DB::get_instance();
        $table_name = $db->get_full_table_name();
        
        $defaults = array(
            'status' => array(), // Empty array means all statuses
            'limit'  => 10,
            'site_id' => 0, 
        );
        $args = wp_parse_args( $args, $defaults );

        $where_clauses = array();
        $sql_params = array();
        
        // Site ID Filter
        if ( ! empty( $args['site_id'] ) ) {
            $where_clauses[] = "client_site_id = %d";
            $sql_params[] = $args['site_id'];
        }

        // Status Filter
        if ( ! empty( $args['status'] ) && is_array( $args['status'] ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $args['status'] ), '%s' ) );
            $where_clauses[] = "ticket_status IN ({$placeholders})";
            // NOTE: Status array is merged *after* site_id, maintaining correct order for prepare.
            $sql_params = array_merge( $sql_params, $args['status'] );
        }
        
        $where_sql = ! empty( $where_clauses ) ? ' WHERE ' . implode( ' AND ', $where_clauses ) : '';
        
        $limit = intval( $args['limit'] );
        $limit_sql = $limit > 0 ? " LIMIT {$limit}" : '';

        // Prepare and execute query
        $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY last_update DESC {$limit_sql}";
        
        if ( ! empty( $sql_params ) ) {
             $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$sql_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, PluginCheck.Security.DirectDBorUnescapedParameter -- Necessary for retrieving tickets, caching is handled higher up.
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql only contains $table_name and structural clauses, no user variables.
             $results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, PluginCheck.Security.DirectDBorUnescapedParameter -- Necessary for retrieving tickets, caching is handled higher up.
        }
        
        $parsed_tickets = array();
        
        // Fetch client sites to map client_site_id to name
        // Optimization: Fetch only the necessary site if site_id is provided.
        $mainwp_client_sites = self::get_websites( ! empty( $args['site_id'] ) ? $args['site_id'] : null ); 
        $site_name_map = array();
        if ( is_array( $mainwp_client_sites ) ) {
            foreach ( $mainwp_client_sites as $site ) {
                 // CRITICAL FIX: Cast key to string for reliable lookup against DB results
                 $site_name_map[ (string)$site['id'] ] = $site['name'];
            }
        }

        foreach ($results as $ticket) {
            
            // Build the MainWP dashboard link.
            $mainwp_dashboard_url = '';
            $ticket_client_id = (string)$ticket['client_site_id']; 
            
            if ( $ticket_client_id > 0 ) {
                 $mainwp_dashboard_url = admin_url( 'admin.php?page=managesites&dashboard=' . $ticket_client_id );
            }

            // Default unmapped status
            $client_site_name = 'Unmapped Site (URL: ' . esc_html( $ticket['client_site_url'] ) . ')';
            
            if ( $ticket_client_id > 0 && isset( $site_name_map[ $ticket_client_id ] ) ) {
                // BEST CASE: Mapped, Name Exists. Output: SiteName -> link to dashboard
                $client_site_name = '<a href="' . esc_url( $mainwp_dashboard_url ) . '">' . esc_html( $site_name_map[ $ticket_client_id ] ) . '</a>';
            } else if ( $ticket_client_id > 0 && ! empty( $mainwp_dashboard_url ) ) {
                // ODD CASE: Mapped ID exists but Name is missing. Output: URL -> link to dashboard
                $client_site_name = '<a href="' . esc_url( $mainwp_dashboard_url ) . '">' . esc_html( $ticket['client_site_url'] ) . '</a>';
            }

            $parsed_tickets[] = array(
                'title'            => $ticket['ticket_title'],
                'status'           => ucfirst( $ticket['ticket_status'] ),
                'updated_at'       => $ticket['last_update'], // Pass the raw datetime string for widget formatting
                'ticket_url'       => $ticket['ticket_url'],
                'client_site_name' => $client_site_name,
                'client_site_id'   => $ticket_client_id, // Required for the SiteOpen link
            );
        }

        return array( 'tickets' => $parsed_tickets, 'success' => true );
    }

    /**
     * Gets the count of tickets based on status.
     * * @param array $status Array of status strings to count by.
     * @param int $site_id Site ID to filter by. Default: 0 (all sites).
     * @return int Count of tickets.
     */
    public static function get_ticket_count( $status = array(), $site_id = 0 ) {
        global $wpdb;
        $db = MainWP_FluentSupport_DB::get_instance();
        $table_name = $db->get_full_table_name();
        
        $where_clauses = array();
        $sql_params = array();

        // Site ID Filter
        if ( ! empty( $site_id ) ) {
            $where_clauses[] = "client_site_id = %d";
            $sql_params[] = $site_id;
        }

        if ( ! empty( $status ) && is_array( $status ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $status ), '%s' ) );
            $where_clauses[] = "ticket_status IN ({$placeholders})";
            $sql_params = array_merge( $sql_params, $status );
        }
        
        $where_sql = ! empty( $where_clauses ) ? ' WHERE ' . implode( ' AND ', $where_clauses ) : '';

        // FIX: Use $wpdb->prepare() for the full query if params exist.
        $sql = "SELECT COUNT(id) FROM {$table_name} {$where_sql}";
        
        if ( ! empty( $sql_params ) ) {
             $count = $wpdb->get_var( $wpdb->prepare( $sql, ...$sql_params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, PluginCheck.Security.DirectDBorUnescapedParameter -- Necessary for widget count.
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql only contains $table_name and structural clauses, no user variables.
             $count = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.NoCaching, PluginCheck.Security.DirectDBorUnescapedParameter -- Necessary for widget count.
        }

        return intval( $count );
    }
}

}
