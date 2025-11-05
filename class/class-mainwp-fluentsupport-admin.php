<?php
/**
 * MainWP FluentSupport Admin
 * REST API Code - Uses Application Passwords for direct connection and local DB storage.
 */

namespace MainWP\Extensions\FluentSupport;

class MainWP_FluentSupport_Admin {

	public static $instance = null;
    private $version = '1.1.9'; // Cleaned version identifier

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// Initialize the DB class (This will create the custom table on activation)
		// CRITICAL FIX: Use the simple class name for correct namespace resolution within the same namespace.
		MainWP_FluentSupport_DB::get_instance()->install(); 
        
        // NEW: Initialize the Widget class
        MainWP_FluentSupport_Widget::get_instance();

		// AJAX handlers for fetching tickets and saving settings
		add_action( 'wp_ajax_mainwp_fluentsupport_fetch_tickets', array( $this, 'ajax_fetch_tickets' ) );
		add_action( 'wp_ajax_mainwp_fluentsupport_save_settings', array( $this, 'ajax_save_settings' ) );
        // New AJAX handler for connection test
        add_action( 'wp_ajax_mainwp_fluentsupport_test_api', array( $this, 'ajax_test_api' ) );
	}

    /**
     * Retrieves the stored URL, bypassing the object cache and normalizing it.
     * @return string
     */
    private function get_support_site_url() {
        return rtrim( get_option( 'mainwp_fluentsupport_site_url', '', false ), '/' );
    }
    
    /**
     * Retrieves the stored API Username.
     * @return string
     */
    private function get_api_username() {
        return get_option( 'mainwp_fluentsupport_api_username', '', false );
    }

    /**
     * Retrieves the stored API Password.
     * @return string
     */
    private function get_api_password() {
        return get_option( 'mainwp_fluentsupport_api_password', '', false );
    }

    // -----------------------------------------------------------------
    // RENDERING METHODS
    // -----------------------------------------------------------------

    /**
     * Renders the tab for configuring the single FluentSupport site (URL text field) and API credentials.
     */
    public function render_settings_tab() {
        $current_site_url = $this->get_support_site_url();
        $current_api_username = $this->get_api_username();
        $current_api_password = $this->get_api_password();
        
        ?>
        <div class="mainwp-padd-cont">
            <h3>Support Site Configuration (REST API)</h3>
            <p>Enter the URL and credentials for the site hosting FluentSupport. This extension communicates directly via the WordPress REST API.</p>
            
            <form method="post" id="mainwp-fluentsupport-settings-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fluentsupport_site_url">Support Site URL</label>
                        </th>
                        <td>
                             <input 
                                type="url"
                                id="fluentsupport_site_url" 
                                name="fluentsupport_site_url" 
                                class="regular-text"
                                value="<?php echo esc_attr( $current_site_url ); ?>"
                                placeholder="https://your-support-site.com"
                                required
                            />
                            <p class="description">The base URL for the site hosting FluentSupport.</p>
                        </td>
                    </tr>
                </table>
                
                <h3 style="margin-top: 30px;">REST API Credentials</h3>
                <p>Use the username and **Application Password** from an administrative account on the Support Site. This allows the MainWP Dashboard to directly query the FluentSupport API endpoint.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fluentsupport_api_username">API Username</label>
                        </th>
                        <td>
                            <input 
                                type="text"
                                id="fluentsupport_api_username" 
                                name="fluentsupport_api_username" 
                                class="regular-text"
                                value="<?php echo esc_attr( $current_api_username ); ?>"
                                required
                            />
                            <p class="description">The WordPress username that generated the Application Password.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fluentsupport_api_password">Application Password</label>
                        </th>
                        <td>
                            <input 
                                type="password"
                                id="fluentsupport_api_password" 
                                name="fluentsupport_api_password" 
                                class="regular-text"
                                value="<?php echo esc_attr( $current_api_password ); ?>"
                                required
                            />
                            <p class="description">The Application Password (must have permissions to access FluentSupport).</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" id="mainwp-fluentsupport-save-settings-btn" class="button button-primary">Save Settings</button>
                    <button type="button" id="mainwp-fluentsupport-test-api-btn" class="button button-secondary">Test API Connection</button>
                </p>
            </form>

        </div>
        <?php
    }

    /**
     * Renders the main Overview tab.
     */
    public function render_overview_tab() {
        $support_site_url = $this->get_support_site_url();
        $api_username = $this->get_api_username();
        $api_password = $this->get_api_password();
        
        if ( empty( $support_site_url ) || empty( $api_username ) || empty( $api_password ) ) {
            echo '<div class="mainwp-notice mainwp-notice-red">Please go to the **Settings** tab and configure your Support Site URL and REST API credentials.</div>';
            return;
        }
        
        $site_name = $support_site_url;
        
        // New: Pull data from the local database immediately for display
        $db_results = MainWP_FluentSupport_Utility::api_get_tickets_from_db();
        // Updated colspan to 4 and removed priority data
        $initial_html = '<tr><td colspan="4">No tickets found in local database. Click "Fetch Latest Tickets" to sync.</td></tr>';
        
        if ( $db_results['success'] && ! empty( $db_results['tickets'] ) ) {
            $initial_html = '';
            foreach ($db_results['tickets'] as $ticket) {
                 $initial_html .= '
                    <tr>
                        <td>' . $ticket['client_site_name'] . '</td>
                        <td><a href="' . esc_url( $ticket['ticket_url'] ) . '" target="_blank">' . esc_html( $ticket['title'] ) . '</a></td>
                        <td>' . esc_html( $ticket['status'] ) . '</td>
                        <td>' . esc_html( $ticket['updated_at'] ) . '</td>
                    </tr>';
            }
        }
        
        ?>
        <div class="mainwp-padd-cont">
            <div class="mainwp-notice mainwp-notice-blue">
                Ticket data displayed is from the local database, last synced from **<?php echo esc_html( $site_name ); ?>**.
            </div>
            
            <p><button id="mainwp-fluentsupport-fetch-btn" class="button button-primary">Fetch Latest Tickets</button></p>
            
            <table class="ui stackable table mainwp-favorites-table dataTable unstackable" id="mainwp-fluentsupport-tickets-table">
                <thead>
                    <tr>
                        <th width="15%">Client Site</th>
                        <th width="50%">Ticket Title</th>
                        <th width="15%">Status</th>
                        <th width="20%">Last Update</th>
                    </tr>
                </thead>
                <tbody id="fluentsupport-ticket-data">
                    <?php echo $initial_html; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    // -----------------------------------------------------------------
    // CORE LOGIC METHODS (AJAX & UTILITY)
    // -----------------------------------------------------------------

    /**
     * AJAX Handler: Handles saving the Support Site URL and API Credentials.
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        // NOTE: Sanitization functions used to protect database
        $input_site_url = isset( $_POST['fluentsupport_site_url'] ) ? sanitize_url( wp_unslash( $_POST['fluentsupport_site_url'] ) ) : '';
        $input_api_username = isset( $_POST['fluentsupport_api_username'] ) ? sanitize_text_field( wp_unslash( $_POST['fluentsupport_api_username'] ) ) : '';
        $input_api_password = isset( $_POST['fluentsupport_api_password'] ) ? sanitize_text_field( wp_unslash( $_POST['fluentsupport_api_password'] ) ) : '';

        // Save the Site URL
        update_option( 'mainwp_fluentsupport_site_url', $input_site_url ); 
        
        // Save the API Credentials
        update_option( 'mainwp_fluentsupport_api_username', $input_api_username );
        update_option( 'mainwp_fluentsupport_api_password', $input_api_password );

        $message = 'Settings saved successfully! You can now test the connection or view tickets.';

        if ( empty($input_site_url) || empty($input_api_username) || empty($input_api_password) ) {
            $message = 'WARNING: API connection details are incomplete. Connection test will fail.';
        }

        wp_send_json_success( array( 'message' => $message ) );
    }
    
    /**
     * AJAX Handler: Test the REST API connection.
     */
    public function ajax_test_api() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $support_site_url = $this->get_support_site_url();
        $api_username = $this->get_api_username();
        $api_password = $this->get_api_password();

        if ( empty( $support_site_url ) || empty( $api_username ) || empty( $api_password ) ) {
            wp_send_json_error( array( 'message' => 'API connection details are incomplete. Please save settings first.' ) );
        }
        
        // Call the new utility function to test the connection
        $result = MainWP_FluentSupport_Utility::api_test_connection( 
            $support_site_url, 
            $api_username, 
            $api_password 
        );

        if ( isset( $result['success'] ) && $result['success'] === true ) {
            wp_send_json_success( array( 'message' => 'Connection successful! API endpoint is reachable.' ) );
        } else {
            $error = isset( $result['error'] ) ? $result['error'] : 'Unknown connection error.';
            wp_send_json_error( array( 'message' => 'Connection failed: ' . $error ) );
        }
    }


    /**
     * AJAX Handler: Kicks off the sync process and returns data from the local DB.
     */
    public function ajax_fetch_tickets() {
        global $wpdb;
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        
        $support_site_url = $this->get_support_site_url();
        $api_username = $this->get_api_username();
        $api_password = $this->get_api_password();

        // Updated colspan to 4 (5 columns reduced to 4)
        if ( empty( $support_site_url ) || empty( $api_username ) || empty( $api_password ) ) {
            $html_output = '<tr><td colspan="4">API connection details are missing. Check Settings.</td></tr>';
            wp_send_json_error( array( 'html' => $html_output, 'message' => 'API connection details are missing. Check Settings.' ) );
        }

        // 1. Run the full synchronization process (heavy lifting)
        $sync_result = MainWP_FluentSupport_Utility::api_sync_tickets( 
            $support_site_url, 
            $api_username, 
            $api_password 
        );
        
        // 2. Retrieve the data from the local database
        $db_results = MainWP_FluentSupport_Utility::api_get_tickets_from_db();
        
        $html_output = '';
        $sync_message = '';
        $is_success = true;
        
        // Final message determination
        if ( $sync_result['success'] === false && isset($sync_result['error']) ) {
             $sync_message = 'Sync failed: ' . $sync_result['error'];
             $is_success = false;
        } else if ($sync_result['success'] && $sync_result['synced'] > 0) {
             $sync_message = 'Sync complete! ' . $sync_result['synced'] . ' tickets processed.';
        } else if ($sync_result['success'] && $sync_result['synced'] === 0) {
             // If sync was attempted but zero tickets were processed, treat as a failure 
             // for debugging and check the last DB error.
             $sync_message = 'Sync failed: Zero tickets processed. Possible API/SQL error.';
             $is_success = false;
        } else {
             $sync_message = 'Unknown sync failure.';
             $is_success = false;
        }

        if ( $db_results['success'] && ! empty( $db_results['tickets'] ) ) {
            foreach ($db_results['tickets'] as $ticket) {
                 $html_output .= '
                    <tr>
                        <td>' . $ticket['client_site_name'] . '</td>
                        <td><a href="' . esc_url( $ticket['ticket_url'] ) . '" target="_blank">' . esc_html( $ticket['title'] ) . '</a></td>
                        <td>' . esc_html( $ticket['status'] ) . '</td>
                        <td>' . esc_html( $ticket['updated_at'] ) . '</td>
                    </tr>';
            }
        } else {
            // Updated colspan to 4
            $html_output = '<tr><td colspan="4">No tickets found in local database.</td></tr>';
        }


        // Final message uses only the sync result message (no debug details)
        $final_message = $sync_message;

        if ( $is_success ) {
             wp_send_json_success( array( 
                 'html' => $html_output,
                 'message' => $final_message
             ) );
        } else {
             wp_send_json_error( array( 
                 'html' => $html_output,
                 'message' => $final_message
             ) );
        }
    }
    
    // REMOVED: MainWP site actions are no longer used.
    public function inject_client_sites_data( $data, $action, $website_id ) {
        return $data;
    }
    public function get_support_site_tickets( $data, $website_id ) {
        return array( 'error' => 'This MainWP action handler is disabled for REST API use.' );
    }
}
