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
     * Gets all child sites through the 'mainwp_getsites' filter.
     */
	public static function get_websites( $site_id = null ) {
		global $mainWPFluentSupportExtensionActivator;
        $plugin_file = $mainWPFluentSupportExtensionActivator->get_child_file();
		return apply_filters( 'mainwp_getsites', $plugin_file, $plugin_file, $site_id, false );
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
            $mainwp_client_sites = self::get_websites(); // Fetch sites once per page of tickets
            
            foreach ( $ticket_list as $ticket_summary ) {
                
                if ( ! is_array($ticket_summary) || !isset($ticket_summary['id']) ) {
                    continue; 
                }
                
                $ticket_id = $ticket_summary['id'];
                
                // CRITICAL FIX: Use the PLURAL form /tickets/<ID> confirmed by API route list
                $singular_endpoint = $base_endpoint . '/tickets/' . $ticket_id; 
                
                // FIX: REMOVED the problematic '?with[]=custom_fields' parameter

                $single_response = self::execute_remote_get( $singular_endpoint, $username, $password );
                
                // --- DEBUG AND ERROR CHECK START (API call) ---
                $log_message = '';

                if ( is_wp_error( $single_response ) ) {
                    $log_message = 'WP_ERROR: ' . $single_response->get_error_message();
                } else {
                    $response_code = wp_remote_retrieve_response_code( $single_response );
                    if ($response_code !== 200) {
                        $response_body = wp_remote_retrieve_body( $single_response );
                        $log_message = 'HTTP_CODE: ' . $response_code . ' | BODY: ' . substr($response_body, 0, 100);
                    }
                }
                
                if ( ! empty( $log_message ) ) {
                    error_log('[FluentSupport Sync] Ticket ID ' . $ticket_id . ' failed. Reason: ' . $log_message);
                    continue; 
                }
                // --- DEBUG AND ERROR CHECK END (API call) ---
                
                $single_data = json_decode( wp_remote_retrieve_body( $single_response ), true );
                
                // CRITICAL FIX: Access the ticket data via the 'ticket' key
                if ( !isset($single_data['ticket']) || !is_array($single_data['ticket']) ) {
                     error_log('[FluentSupport Sync] Ticket ID ' . $ticket_id . ' failed parsing. Missing "ticket" key in response.');
                     continue; 
                }
                
                $ticket = $single_data['ticket']; // The correct full ticket object
                
                if ( ! is_array($ticket) || !isset($ticket['id']) ) {
                    // Double check if the internal ticket object is valid
                    continue; 
                }
                
                // --- Extract Data and Site URL ---
                $website_url = '';
                
                // CRITICAL FIX: Directly access the custom field by key as confirmed by manual test
                if ( isset($ticket['custom_fields']) && isset($ticket['custom_fields']['cf_website_url']) ) {
                    $raw_url = $ticket['custom_fields']['cf_website_url'];
                    
                    // Strip HTML tags and remove trailing slash
                    $website_url = rtrim( strip_tags($raw_url), '/' );
                }
                
                // Get the MainWP Site ID 
                $client_site_id = 0;
                
                if ( ! empty( $website_url ) && is_array( $mainwp_client_sites ) ) {
                    foreach ( $mainwp_client_sites as $site ) {
                         if ( rtrim($site['url'], '/') === $website_url ) {
                             $client_site_id = $site['id'];
                             break;
                         }
                    }
                }

                // --- Prepare DB Data ---
                $last_update_str = isset($ticket['updated_at']) ? $ticket['updated_at'] : current_time('mysql');
                $created_at_str = isset($ticket['created_at']) ? $ticket['created_at'] : current_time('mysql');
                
                $last_update_mysql = date('Y-m-d H:i:s', strtotime($last_update_str) ?: time());
                $created_at_mysql = date('Y-m-d H:i:s', strtotime($created_at_str) ?: time());


                $data_to_save = array(
                    'ticket_id'      => (int)$ticket_id, 
                    'client_site_id' => (int)$client_site_id,
                    'client_site_url' => $website_url, // This should now contain the clean URL
                    'ticket_title'   => isset($ticket['title']) ? $ticket['title'] : 'N/A',
                    'ticket_status'  => substr(isset($ticket['status']) ? $ticket['status'] : 'N/A', 0, 20),
                    'ticket_priority'=> substr(isset($ticket['priority']) ? $ticket['priority'] : 'N/A', 0, 20),
                    'ticket_url'     => rtrim($site_url, '/') . '/wp-admin/admin.php?page=fluent-support#/tickets/' . $ticket_id . '/view',
                    'last_update'    => $last_update_mysql,
                    'created_at'     => $created_at_mysql,
                );
                
                // --- INSERT or UPDATE Logic (UPSERT) ---
                
                $existing_ticket = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table_name WHERE ticket_id = %d", $ticket_id ) );

                if ( $existing_ticket ) {
                    // Update existing record
                    // CRITICAL FIX: Added format array for security and reliability
                    $updated = $wpdb->update( $table_name, $data_to_save, array( 'ticket_id' => $ticket_id ), $format_array, array('%d') );
                     
                    // LOGGING: Final state check
                    if ($updated === false) {
                         error_log('[FluentSupport DB FINAL] UPDATE FAILED for ID ' . $ticket_id . '. Last Error: ' . $wpdb->last_error . ' | Data: ' . print_r($data_to_save, true));
                    }
                } else {
                    // Insert new record
                    // CRITICAL FIX: Added format array for security and reliability
                    $inserted = $wpdb->insert( $table_name, $data_to_save, $format_array );
                    
                    // LOGGING: Final state check
                    if ($inserted === false) {
                         error_log('[FluentSupport DB FINAL] INSERT FAILED for ID ' . $ticket_id . '. Last Error: ' . $wpdb->last_error . ' | Data: ' . print_r($data_to_save, true));
                    }
                }

                $synced_count++;
            }

            $current_page++;

        } while ( $current_page <= $total_pages && (microtime(true) - $start_time) < 500 ); // Max 500s limit

        return array( 'success' => true, 'synced' => $synced_count );
    }

    /**
     * Fetches ticket data from the local database for display.
     */
    public static function api_get_tickets_from_db() {
        global $wpdb;
        // FIX: Remove redundant namespace prefix
        $db = MainWP_FluentSupport_DB::get_instance();
        $table_name = $db->get_full_table_name();

        $results = $wpdb->get_results( 
            "SELECT * FROM $table_name ORDER BY last_update DESC LIMIT 10", 
            ARRAY_A 
        );

        $parsed_tickets = array();
        
        // Fetch all client sites to map client_site_id to name
        $mainwp_client_sites = self::get_websites(); 
        $site_name_map = array();
        if ( is_array( $mainwp_client_sites ) ) {
            foreach ( $mainwp_client_sites as $site ) {
                 $site_name_map[ $site['id'] ] = $site['name'];
            }
        }

        foreach ($results as $ticket) {
            $client_site_name = 'Unmapped Site (URL: ' . $ticket['client_site_url'] . ')';

            if ( $ticket['client_site_id'] > 0 && isset( $site_name_map[ $ticket['client_site_id'] ] ) ) {
                $client_site_name = $site_name_map[ $ticket['client_site_id'] ];
            }

            $parsed_tickets[] = array(
                'title'            => $ticket['ticket_title'],
                'status'           => ucfirst( $ticket['ticket_status'] ),
                'updated_at'       => date( 'M j, Y H:i', strtotime( $ticket['last_update'] ) ),
                'ticket_url'       => $ticket['ticket_url'],
                'client_site_name' => $client_site_name,
            );
        }

        return array( 'tickets' => $parsed_tickets, 'success' => true );
    }
}
