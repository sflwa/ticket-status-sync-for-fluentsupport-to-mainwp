<?php
/**
 * SFLWA Admin Notice Handler
 *
 * This file contains the reusable functions and AJAX handlers for displaying
 * a dismissible admin notice, submitting data to Gravity Forms on a remote site
 * via a custom proxy endpoint, and permanently dismissing the notice.
 *
 * This file is intended to be included by multiple WordPress plugins.
 */

// Prevent direct access to this file.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define a unique constant for this plugin's name.
// This makes the notice handler flexible for multiple plugins.
// IMPORTANT: This line should ideally be in your main plugin file,
// but included here for a self-contained example.
if ( ! defined( 'SFLWA_PLUGIN_NAME' ) ) {
    define( 'SFLWA_PLUGIN_NAME', 'Ticket Status Sync for FluentSupport to MainWP' ); // <--- IMPORTANT: Change this for each plugin!
}

// Register the custom admin notice to be displayed.
// This hook ensures the notice is checked and potentially displayed on every admin page load.
add_action( 'admin_init', 'sflwa_register_admin_notice' );

/**
 * Registers the admin notice if it hasn't been permanently dismissed.
 *
 * This function checks for a persistent option set when the user dismisses the notice.
 * If the option is not set or not equal to '1', it means the notice should be displayed.
 */
function sflwa_register_admin_notice() {
    // Construct a unique option name based on the plugin name.
    // sanitize_title() ensures the name is safe for an option key.
    $option_name = 'sflwa_notice_dismissed_' . sanitize_title( SFLWA_PLUGIN_NAME );

    // Check if the permanent option is set to '1'. If not, add the action to display the notice.
    if ( get_option( $option_name ) !== '1' ) {
        add_action( 'admin_notices', 'sflwa_display_admin_notice' );
    }
}

/**
 * Displays the admin notice using the shared handler function.
 *
 * This function is hooked into 'admin_notices' and calls the reusable
 * `sflwa_display_notice_handler` function, passing in the current plugin's name
 * and the AJAX URL needed for the JavaScript.
 */
function sflwa_display_admin_notice() {
    // Ensure the current user has permission to see admin notices (e.g., administrator).
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Call the shared handler function, passing the plugin name and WordPress AJAX URL.
    sflwa_display_notice_handler( SFLWA_PLUGIN_NAME, admin_url( 'admin-ajax.php' ) );
}

/**
 * Displays the dismissible admin notice HTML and JavaScript.
 *
 * @param string $plugin_name The name of the plugin for which the notice is displayed.
 * @param string $ajax_url    The URL for the WordPress AJAX endpoint.
 */
function sflwa_display_notice_handler( $plugin_name, $ajax_url ) {
    // Enqueue jQuery if it's not already enqueued (WordPress usually does this in admin).
    // This ensures our inline script can use jQuery.
    wp_enqueue_script( 'jquery' );
    ?>
    <div class="notice notice-info sflwa-admin-notice is-dismissible" data-plugin-name="<?php echo esc_attr( $plugin_name ); ?>" style="padding-bottom: 10px;">
        <p>
            <strong>Thank you for installing our plugin: <?php echo esc_html( $plugin_name ); ?>!</strong>
        </p>
        <p>
            We'd love to know who has installed our plugin to better understand our users.
            Click the button below to let us know. The only data transmitted is your website URL and the plugin name.
        </p>
        <button class="button button-primary sflwa-submit-button">Let us know!</button>
        <button class="button button-secondary sflwa-no-thank-you-button">No thank you</button>
        <span class="sflwa-notice-spinner spinner" style="visibility: hidden;"></span>
        <span class="sflwa-notice-thank-you" style="display: none; margin-left: 10px; font-weight: bold;">Thank you for letting us know!</span>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle the "Let us know!" button click for Gravity Forms submission.
            $('.sflwa-admin-notice .sflwa-submit-button').on('click', function(e) {
                e.preventDefault(); // Prevent default button action (e.g., form submission).

                var button = $(this);
                var notice = button.closest('.sflwa-admin-notice');
                var spinner = notice.find('.spinner');
                var thankYouMessage = notice.find('.sflwa-notice-thank-you');
                var noThankYouButton = notice.find('.sflwa-no-thank-you-button');


                // Show spinner and disable the button
                spinner.css('visibility', 'visible');
                button.prop('disabled', true);
                noThankYouButton.prop('disabled', true); // Disable "No thank you" button too


                // Perform AJAX request to submit plugin info.
                $.post('<?php echo esc_url( $ajax_url ); ?>', {
                    action: 'sflwa_submit_plugin_info', // WordPress AJAX action hook.
                    plugin_name: notice.data('plugin-name'), // Get plugin name from data attribute.
                    website_url: '<?php echo esc_url( get_home_url() ); ?>', // Get current website URL.
                    _ajax_nonce: '<?php echo esc_attr( wp_create_nonce( 'sflwa_submit_nonce' ) ); ?>' // Security nonce.
                }, function(response) {
                    // Hide spinner after response.
                    spinner.css('visibility', 'hidden');
                    if (response.success) {
                        // On successful submission, hide the button and show the thank you message.
                        button.hide();
                        thankYouMessage.show();
                        noThankYouButton.text('Close').prop('disabled', false); // Change text to "Close" and re-enable
                    } else {
                        // Optionally, handle errors (e.g., show an error message).
                        console.error('SFLWA Notice: Form submission failed.', response.data);
                        button.prop('disabled', false); // Re-enable button on failure.
                        noThankYouButton.prop('disabled', false); // Re-enable "No thank you" button
                        console.log('Error submitting info: ' + (response.data.message || 'Unknown error.'));
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    spinner.css('visibility', 'hidden');
                    button.prop('disabled', false);
                    noThankYouButton.prop('disabled', false); // Re-enable "No thank you" button
                    console.error('Network error during submission:', textStatus, errorThrown);
                    console.log('Network error. Please try again.');
                });
            });

            // Handle the "No thank you" button click
            $('.sflwa-admin-notice .sflwa-no-thank-you-button').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var notice = button.closest('.sflwa-admin-notice');
                var submitButton = notice.find('.sflwa-submit-button');

                // Disable buttons to prevent multiple clicks
                button.prop('disabled', true);
                submitButton.prop('disabled', true);

                // Perform AJAX request to dismiss the notice permanently.
                $.post('<?php echo esc_url( $ajax_url ); ?>', {
                    action: 'sflwa_dismiss_admin_notice', // WordPress AJAX action hook.
                    plugin_name: notice.data('plugin-name'), // Get plugin name from data attribute.
                    _ajax_nonce: '<?php echo esc_attr( wp_create_nonce( 'sflwa_dismiss_nonce' ) ); ?>' // Security nonce.
                }, function(response) {
                    if (response.success) {
                        notice.fadeTo(100, 0, function() {
                            notice.slideUp(100, function() {
                                notice.remove();
                            });
                        });
                    } else {
                        console.error('SFLWA Notice: Dismissal failed.', response.data);
                        button.prop('disabled', false); // Re-enable on failure
                        submitButton.prop('disabled', false); // Re-enable on failure
                    }
                }).fail(function() {
                    button.prop('disabled', false);
                    submitButton.prop('disabled', false);
                    console.error('Network error during dismissal.');
                });
            });

            // Handle the notice dismissal when the 'x' button is clicked.
            // Using event delegation because the dismiss button is added by WordPress.
            $(document).on('click', '.sflwa-admin-notice .notice-dismiss', function() {
                var notice = $(this).closest('.sflwa-admin-notice');
                $.post('<?php echo esc_url( $ajax_url ); ?>', {
                    action: 'sflwa_dismiss_admin_notice', // WordPress AJAX action hook.
                    plugin_name: notice.data('plugin-name'), // Get plugin name from data attribute.
                    _ajax_nonce: '<?php echo esc_attr( wp_create_nonce( 'sflwa_dismiss_nonce' ) ); ?>' // Security nonce.
                }, function(response) {
                    // No specific UI update needed here as the notice is already hidden by WordPress.
                    if (!response.success) {
                        console.error('SFLWA Notice: Dismissal failed.', response.data);
                    }
                });
            });
        });
    </script>
    <?php
}

/**
 * AJAX callback to handle the Gravity Forms submission via a custom proxy endpoint.
 *
 * This function is hooked to 'wp_ajax_sflwa_submit_plugin_info'.
 * It receives the plugin name and website URL, and submits them to a custom
 * REST API endpoint on southfloridawebadvisors.com.
 */
add_action( 'wp_ajax_sflwa_submit_plugin_info', 'sflwa_submit_plugin_info_callback' );
function sflwa_submit_plugin_info_callback() {
    // Verify nonce for security.
    check_ajax_referer( 'sflwa_submit_nonce', '_ajax_nonce' );

    // Sanitize and retrieve data from the AJAX request.
    $plugin_name = isset( $_POST['plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) ) : '';
    $website_url = isset( $_POST['website_url'] ) ? esc_url_raw( wp_unslash( $_POST['website_url'] ) ) : '';

    // --- Custom Proxy Endpoint Configuration ---
    $remote_proxy_base_url = 'https://southfloridawebadvisors.com/'; // Base URL of your proxy site.
    // This is the new custom endpoint we will create on southfloridawebadvisors.com
    $remote_proxy_submit_url = trailingslashit( $remote_proxy_base_url ) . 'wp-json/sflwa/v1/submit-plugin-info';

    // Ensure the website URL has a scheme (http or https) for proper formatting.
    if ( ! preg_match( '~^(?:f|ht)tps?://~i', $website_url ) ) {
        $website_url = 'https://' . $website_url;
    }

    // Prepare the data array to send to your custom proxy endpoint.
    // This data will then be re-mapped to Gravity Forms fields on the proxy server.
    $post_data = array(
        'plugin_name' => $plugin_name,
        'website_url' => $website_url,
    );

    // Arguments for wp_remote_post().
    $args = array(
        'method'      => 'POST',
        'timeout'     => 15, // Max time in seconds to wait for response.
        'redirection' => 5,  // Max number of redirects.
        'httpversion' => '1.0',
        'blocking'    => true, // Whether to block until the request completes.
        'headers'     => array(
            'Content-Type'  => 'application/json', // Sending JSON to your custom endpoint.
        ),
        'body'        => json_encode( $post_data ), // Encode data as JSON string.
        'sslverify'   => false, // Set to true in production if you have proper SSL certs.
                                 // Setting to false can help with local testing or self-signed certs.
    );

    // Perform the remote POST request.
    $response = wp_remote_post( $remote_proxy_submit_url, $args );

    // Check for WP_Error.
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        wp_send_json_error( array( 'message' => 'Failed to submit info to remote server.', 'error' => $error_message ) );
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Your proxy endpoint should return a JSON response.
        $decoded_response = json_decode( $response_body, true );

        // Check for a successful response from your proxy.
        // We'll assume your proxy returns a 'success' boolean.
        if ( $response_code === 200 && isset( $decoded_response['success'] ) && $decoded_response['success'] === true ) {
            wp_send_json_success( 'Form submitted successfully via proxy.' );
        } else {
            wp_send_json_error( array(
                'message' => 'Remote proxy submission failed or returned an unexpected response.',
                'response_code' => $response_code,
                'response_body' => $response_body,
                'decoded_response' => $decoded_response,
            ) );
        }
    }

    wp_die(); // Always exit after an AJAX request.
}

/**
 * AJAX callback to permanently dismiss the admin notice.
 *
 * This function is hooked to 'wp_ajax_sflwa_dismiss_admin_notice'.
 * It sets an option to prevent the notice from showing again for this plugin.
 */
add_action( 'wp_ajax_sflwa_dismiss_admin_notice', 'sflwa_dismiss_admin_notice_callback' );
function sflwa_dismiss_admin_notice_callback() {
    // Verify nonce for security.
    check_ajax_referer( 'sflwa_dismiss_nonce', '_ajax_nonce' );

    // Sanitize and retrieve the plugin name from the AJAX request.
    $plugin_name = isset( $_POST['plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) ) : '';

    // Construct the unique option name.
    $option_name = 'sflwa_notice_dismissed_' . sanitize_title( $plugin_name );

    // Set a permanent option to '1' to permanently hide the notice.
    // The 'true' flag tells WordPress to autoload the option if it's new (which is good for a quick check).
    update_option( $option_name, '1', true ); 

    wp_send_json_success( 'Notice dismissed permanently.' );
    wp_die(); // Always exit after an AJAX request.
}