<?php
/**
 * MainWP FluentSupport Admin
 * Final Code - Bypasses object caching for settings retrieval.
 */

namespace MainWP\Extensions\FluentSupport;

class MainWP_FluentSupport_Admin {

	public static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// Initialize the DB class (Required for MainWP structural pattern)
		MainWP_FluentSupport_DB::get_instance()->install();

		// AJAX handlers for fetching tickets and saving settings
		add_action( 'wp_ajax_mainwp_fluentsupport_fetch_tickets', array( $this, 'ajax_fetch_tickets' ) );
		add_action( 'wp_ajax_mainwp_fluentsupport_save_settings', array( $this, 'ajax_save_settings' ) );

		// MainWP filter hook for injecting client site data into the remote call
		add_filter( 'mainwp_before_do_actions', array( $this, 'inject_client_sites_data' ), 10, 3 );
        
        // REMOVED: add_filter( 'mainwp_site_actions_fluent_support_tickets_all', array( $this, 'get_support_site_tickets' ), 10, 2 );
	}

    /**
     * Retrieves the stored MainWP Site ID, bypassing the object cache.
     * @return int
     */
    private function get_support_site_id() {
        return (int) get_option( 'mainwp_fluentsupport_site_id', 0, false );
    }

    /**
     * Retrieves the stored URL, bypassing the object cache and normalizing it.
     * @return string
     */
    private function get_support_site_url() {
        return rtrim( get_option( 'mainwp_fluentsupport_site_url', '', false ), '/' );
    }

    // -----------------------------------------------------------------
    // RENDERING METHODS
    // -----------------------------------------------------------------

    /**
     * Renders the tab for configuring the single FluentSupport site (Select Box).
     */
    public function render_settings_tab() {
        $current_site_id = $this->get_support_site_id();
        $current_site_url = $this->get_support_site_url();
        
        // 🔑 FETCH ALL WEBSITES
        $all_websites = MainWP_FluentSupport_Utility::get_websites();
        
        // DEBUG VALUES: Get raw option values, forcing uncached read for accurate display
        $debug_raw_url = get_option('mainwp_fluentsupport_site_url', 'NOT SET', false);
        $debug_raw_id = get_option('mainwp_fluentsupport_site_id', 'NOT SET', false);

        ?>
        <div class="mainwp-padd-cont">
            <h3>Support Site Configuration</h3>
            <p>Select the connected MainWP Child Site where **FluentSupport** is installed. This method uses the reliable MainWP Site ID to prevent lookup errors.</p>
            
            <form method="post" id="mainwp-fluentsupport-settings-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fluentsupport_site_selection">Support Site</label>
                        </th>
                        <td>
                            <select 
                                id="fluentsupport_site_selection" 
                                name="fluentsupport_site_id" // Changed name to pass site ID directly
                                class="regular-text"
                                required
                            >
                                <option value="0">-- Select a Connected Site --</option>
                                <?php 
                                    // 🔑 FIX 1: Add is_array() check to prevent fatal error on failure
                                    if ( is_array( $all_websites ) && ! empty( $all_websites ) ) {
                                        foreach ( $all_websites as $website ) {
                                            $normalized_url = rtrim($website['url'], '/');
                                            $selected = ( (int)$website['id'] === $current_site_id ) ? 'selected="selected"' : '';
                                            $site_display_name = ! empty( $website['name'] ) ? $website['name'] : $normalized_url;
                                            // Value is the site ID
                                            echo '<option value="' . esc_attr( $website['id'] ) . '" ' . $selected . '>' . esc_html( $site_display_name . ' (' . $normalized_url . ')' ) . '</option>';
                                        }
                                    }
                                ?>
                            </select>
                            <p class="description">**Must** select the site that is both connected to MainWP and has FluentSupport installed.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" id="mainwp-fluentsupport-save-settings-btn" class="button button-primary">Save Settings</button>
                </p>
            </form>

            <hr/>
            <h4>⚙️ Settings Debug Output (Stored DB Values)</h4>
            <table class="form-table">
                <tr>
                    <th>Raw DB Option (URL)</th>
                    <td><code>mainwp_fluentsupport_site_url</code>: **<?php echo esc_html($debug_raw_url); ?>**</td>
                </tr>
                <tr>
                    <th>Raw DB Option (ID)</th>
                    <td><code>mainwp_fluentsupport_site_id</code>: **<?php echo esc_html($debug_raw_id); ?>**</td>
                </tr>
                <tr>
                    <th>Site Connection Check</th>
                    <td>**Is Site ID Found & Set?** <?php echo ($this->get_support_site_id() > 0) ? '✅ YES' : '❌ NO'; ?></td>
                </tr>
                <tr>
                    <td colspan="2">**Troubleshooting Tip:** If URL saves but ID is 'NOT SET' or '0', check your **PHP Error Log** for output from the `ajax_save_settings` function.</td>
                </tr>
            </table>

            <hr/>
            <h4>📋 Site List Debug (Output from <code>mainwp_getsites</code> filter)</h4>
            <?php 
                // 🔑 FIX 2: Safely count the sites, defaulting to 0 if it's not an array
                $site_count = is_array($all_websites) ? count($all_websites) : 0;
            ?>
            <p><strong>Total Sites Found:</strong> <?php echo $site_count; ?></p>
            <?php 
                // 🔑 FIX 3: Ensure $all_websites is an array before attempting to loop and display
                if ( is_array( $all_websites ) && ! empty( $all_websites ) ) : 
            ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="10%">ID</th>
                            <th width="30%">Name</th>
                            <th width="60%">URL (Stored by MainWP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $all_websites as $website ) : ?>
                            <tr>
                                <td><?php echo esc_html( $website['id'] ); ?></td>
                                <td><?php echo esc_html( $website['name'] ); ?></td>
                                <td><?php echo esc_html( $website['url'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="mainwp-notice mainwp-notice-red">No connected child sites were returned by the <code>mainwp_getsites</code> filter.</div>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Renders the main Overview tab.
     */
    public function render_overview_tab() {
        $support_site_id = $this->get_support_site_id();
        $support_site_url = $this->get_support_site_url();
        
        if ( empty( $support_site_id ) || empty( $support_site_url ) ) {
            echo '<div class="mainwp-notice mainwp-notice-red">Please go to the **Settings** tab and configure your FluentSupport site URL. The site must be a connected MainWP Child Site.</div>';
            return;
        }
        
        $site_name = $support_site_url;
        
        ?>
        <div class="mainwp-padd-cont">
            <div class="mainwp-notice mainwp-notice-blue">
                Fetching ticket data from **<?php echo esc_html( $site_name ); ?>**. This site holds all your FluentSupport tickets.
            </div>
            
            <p><button id="mainwp-fluentsupport-fetch-btn" class="button button-primary">Fetch Latest Tickets</button></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="15%">Client Site</th>
                        <th width="40%">Ticket Title</th>
                        <th width="15%">Status</th>
                        <th width="15%">Priority</th>
                        <th width="15%">Last Update</th>
                    </tr>
                </thead>
                <tbody id="fluentsupport-ticket-data">
                    <tr id="initial-load-row"><td colspan="5">Click "Fetch Latest Tickets" to retrieve the list.</td></tr>
                </tbody>
            </table>
            
            <hr/>
            <h4>⚙️ Fetch Debug Output</h4>
            <table class="form-table">
                <tr>
                    <th>Target Site URL</th>
                    <td>**<?php echo esc_html($support_site_url); ?>**</td>
                </tr>
                <tr>
                    <th>Target Site ID (for remote call)</th>
                    <td>**<?php echo esc_html($support_site_id); ?>**</td>
                </tr>
                <tr>
                    <td colspan="2">**If button does nothing:** The JavaScript file <code>mainwp-fluentsupport.js</code> is not loading. Check your browser's **Network** tab.</td>
                </tr>
            </table>
            <hr/>
            <h4>✅ Last AJAX Call Details (Appears after you click "Fetch Latest Tickets")</h4>
            <div id="mainwp-fluentsupport-ajax-debug">
                <p>Status: Awaiting first fetch.</p>
                <p>Site Lookup: N/A</p>
                <p>Remote Result:</p>
                <pre id="mainwp-fluentsupport-ajax-result">Click 'Fetch Latest Tickets' to update this section.</pre>
            </div>
            <script>
                // Inject an extra debug handler into the existing JS logic
                jQuery(document).ready(function ($) {
                    var originalSuccess = jQuery.ajaxSettings.success;
                    var originalError = jQuery.ajaxSettings.error;

                    // Override global handlers if needed, but safer to inject into the click handler
                    
                    $('#mainwp-fluentsupport-fetch-btn').off('click').on('click', function (e) {
                        e.preventDefault();

                        var $fetchButton = $('#mainwp-fluentsupport-fetch-btn');
                        var $ticketDataBody = $('#fluentsupport-ticket-data');
                        var $messageDiv = $('#mainwp-fluentsupport-message');
                        var fetchAction = 'mainwp_fluentsupport_fetch_tickets';

                        $fetchButton.prop('disabled', true).text('Fetching Tickets...');
                        $messageDiv.hide().removeClass().empty();
                        $('#mainwp-fluentsupport-ajax-debug p').text('Status: Running...');
                        $('#mainwp-fluentsupport-ajax-result').text('Sending request...');
                        $ticketDataBody.html('<tr class="loading-row"><td colspan="5"><i class="fa fa-spinner fa-pulse"></i> Contacting Support Site for tickets...</td></tr>');

                        $.ajax({
                            url: mainwpFluentSupport.ajaxurl,
                            type: 'POST',
                            data: {
                                action: fetchAction,
                                security: mainwpFluentSupport.nonce
                            },
                            success: function (response) {
                                $('#mainwp-fluentsupport-ajax-debug p').text('Status: Success/Failure reported by MainWP handler.');
                                $('#mainwp-fluentsupport-ajax-result').text(JSON.stringify(response, null, 2));

                                if (response.success) {
                                    $ticketDataBody.html(response.data.html);
                                    $messageDiv
                                        .addClass('mainwp-notice mainwp-notice-green')
                                        .html('<strong>Success!</strong> Ticket data updated.')
                                        .slideDown();
                                } else {
                                    $ticketDataBody.html(response.data.html || '<tr><td colspan="5">An error occurred while fetching data. Check PHP logs or AJAX Debug below.</td></tr>');
                                    $messageDiv
                                        .addClass('mainwp-notice mainwp-notice-red')
                                        .html('<strong>Error:</strong> ' + (response.data.message || 'Unknown Error (Check PHP logs)'))
                                        .slideDown();
                                }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                $('#mainwp-fluentsupport-ajax-debug p').text('Status: Network/Server Error.');
                                $('#mainwp-fluentsupport-ajax-result').text('Status: ' + textStatus + '\nError: ' + errorThrown);
                                $ticketDataBody.html('<tr><td colspan="5">Network Error: Request timed out or was blocked. Status: ' + textStatus + '</td></tr>');
                                $messageDiv
                                    .addClass('mainwp-notice mainwp-notice-red')
                                    .html('<strong>Fatal Network Error:</strong> Could not reach the server.')
                                    .slideDown();
                            },
                            complete: function () {
                                $fetchButton.prop('disabled', false).text('Fetch Latest Tickets');
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
    
    // -----------------------------------------------------------------
    // CORE LOGIC METHODS (AJAX & HOOKS)
    // -----------------------------------------------------------------

    /**
     * Injects all MainWP Child Site URLs into the remote call data.
     */
    public function inject_client_sites_data( $data, $action, $website_id ) {
        if ( 'fluent_support_tickets_all' === $action ) {
            $all_websites = MainWP_FluentSupport_Utility::get_websites();
            
            $client_sites = array();
            // Ensure $all_websites is an array before trying to iterate
            if ( is_array( $all_websites ) ) {
                foreach ( $all_websites as $website ) {
                    // Store normalized URL (no trailing slash) as key
                    $client_sites[ rtrim($website['url'], '/') ] = $website['name'];
                }
            }
            $data['client_sites'] = $client_sites;
        }
        return $data;
    }

    /**
     * AJAX Handler: Handles saving the Support Site ID and finding its MainWP URL.
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        // Retrieve the site ID directly from the select box input
        $input_site_id = isset( $_POST['fluentsupport_site_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['fluentsupport_site_id'] ) ) : 0;

        if ( $input_site_id < 0 ) {
             wp_send_json_error( array( 'message' => 'Invalid Support Site ID selected.' ) );
        }
        
        error_log( '[FluentSupport] Attempting to save settings for selected ID: ' . $input_site_id );

        $found_site_id = 0;
        $site_url_to_save = '';

        if ($input_site_id > 0) {
            // Retrieve the full site object using the reliable ID
            $websites = MainWP_FluentSupport_Utility::get_websites( $input_site_id );
            
            // Ensure $websites is an array before processing
            $website = is_array( $websites ) && ! empty( $websites ) ? current( $websites ) : null;
            
            if ( ! empty( $website ) ) {
                $found_site_id = (int)$website['id']; 
                // Use the normalized URL from the MainWP child site record for maximum consistency
                $site_url_to_save = rtrim( $website['url'], '/' ); 
                error_log( '[FluentSupport] Site FOUND by ID. ID: ' . $found_site_id . ', URL: ' . $site_url_to_save );
            } else {
                // If the ID was selected but the site is disconnected/deleted, we treat it as not found
                error_log( '[FluentSupport] Site NOT FOUND or DISCONNECTED by ID lookup.' );
            }
        } else {
             error_log( '[FluentSupport] Selected ID is 0, clearing settings.' );
        }

        // Store the URL (for display)
        $url_saved = update_option( 'mainwp_fluentsupport_site_url', $site_url_to_save ); 
        
        // Store the ID (0 if not found or if "Select a Site" was chosen)
        $id_saved = update_option( 'mainwp_fluentsupport_site_id', $found_site_id ); 

        error_log( '[FluentSupport] DB Update Status: URL Saved: ' . ($url_saved ? 'T' : 'F') . ', ID Saved: ' . ($id_saved ? 'T' : 'F') . ', Final ID: ' . $this->get_support_site_id() );

        // Check if the setting was saved successfully (i.e., not changed and already up to date, or updated successfully)
        if ( $url_saved === false && $id_saved === false && $this->get_support_site_url() === $site_url_to_save ) {
            wp_send_json_success( array( 'message' => 'Settings already up to date.' ) );
        }

        $message = 'Settings saved successfully! You can now view tickets in the Overview tab.';

        if ($found_site_id === 0) {
            $message = 'Settings cleared or saved. WARNING: No connected MainWP Child Site selected. Ticket fetching will fail.';
        }

        wp_send_json_success( array( 'message' => $message ) );
    }

    /**
     * AJAX Handler: Kicks off the remote execution on the single designated Support Site.
     */
    public function ajax_fetch_tickets() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        
        $support_site_id = $this->get_support_site_id();
        $support_site_url = $this->get_support_site_url(); // For debug output
        
        if ( empty( $support_site_id ) ) {
            $html_output = '<tr><td colspan="5">Support Site ID is 0. Check Settings.</td></tr>';
            wp_send_json_error( array( 'html' => $html_output, 'message' => 'Support Site not configured. Please check settings.' ) );
        }

        // Fetch the site object using the Utility function
        $websites = MainWP_FluentSupport_Utility::get_websites( $support_site_id );
        
        $website = is_array( $websites ) && ! empty( $websites ) ? current( $websites ) : null;

        if ( empty( $website ) ) {
            $error_message = 'Configured Support Site (ID: ' . $support_site_id . ') not found or disconnected. Please re-sync.';
            $html_output = '<tr class="error-row"><td colspan="5">' . esc_html($error_message) . '</td></tr>';
            wp_send_json_error( array( 'html' => $html_output, 'message' => $error_message ) );
        }
        
        // Fetch the plugin file path for the remote call
        $plugin_file = MainWP_FluentSupport_Utility::get_file_name();
        
        error_log( '[FluentSupport] Attempting remote call to ID: ' . $website['id'] . ' using file: ' . $plugin_file );

        // Use the explicit 'mainwp_do_actions' filter for reliability.
        // The data array explicitly maps the action to the plugin file path/key.
        $data = array(
            'fluent_support_tickets_all' => 'fluent-support-child/fluent-support-child.php', // CRITICAL: This must match the child plugin file path!
        );

        // Execute the action on the single site
        $result = apply_filters( 'mainwp_do_actions', 'fluent_support_tickets_all', $website['id'], $data );
        
        $html_output = '';

        if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] === true ) {
            if ( ! empty( $result['tickets'] ) ) {
                foreach ( $result['tickets'] as $ticket ) {
                    $html_output .= '
                        <tr>
                            <td>' . esc_html( $ticket['client_site_name'] ) . '</td>
                            <td><a href="' . esc_url( $ticket['ticket_url'] ) . '" target="_blank">' . esc_html( $ticket['title'] ) . '</a></td>
                            <td>' . esc_html( $ticket['status'] ) . '</td>
                            <td>' . esc_html( $ticket['priority'] ) . '</td>
                            <td>' . esc_html( $ticket['updated_at'] ) . '</td>
                        </tr>';
                }
            } else {
                 $html_output = '<tr><td colspan="5">No open tickets found on the Support Site.</td></tr>';
            }
            wp_send_json_success( array( 'html' => $html_output ) );

        } else {
            $error_message = 'Failed to get tickets. Check MainWP connection.';
            if ( is_array( $result ) && isset( $result['error'] ) ) {
                $error_message = esc_html( $result['error'] );
            } else if (is_array($result) && !empty($result)) {
                $error_message .= ' (Raw response seen in Debug Output)';
            }
            
            // Add the plugin file used to the error message for easy debugging
            $error_message .= ' (Remote action attempted with file: ' . esc_html($data['fluent_support_tickets_all']) . ')';
            
            $html_output = '<tr class="error-row"><td colspan="5">' . $error_message . '</td></tr>';
            wp_send_json_error( array( 'html' => $html_output, 'message' => $error_message, 'raw_response' => $result ) );
        }
    }
    
    // REMOVED: public function get_support_site_tickets( $data, $website_id ) { ... }
}
