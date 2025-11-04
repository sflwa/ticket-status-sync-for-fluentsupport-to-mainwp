jQuery(document).ready(function ($) {

    var $fetchButton = $('#mainwp-fluentsupport-fetch-btn');
    var $saveButton = $('#mainwp-fluentsupport-save-settings-btn');
    var $ticketDataBody = $('#fluentsupport-ticket-data');
    var $messageDiv = $('#mainwp-fluentsupport-message');
    var fetchAction = 'mainwp_fluentsupport_fetch_tickets';
    var saveAction = 'mainwp_fluentsupport_save_settings';

    // -------------------------
    // 1. FETCH TICKETS LOGIC
    // -------------------------
    $fetchButton.on('click', function (e) {
        e.preventDefault();

        // 1. Initial UI Lock and Message Display (CONFIRMING BUTTON IS PRESSED)
        $fetchButton.prop('disabled', true).text('Fetching Tickets...');
        $messageDiv.hide().removeClass().empty();

        // Show immediate loading indicator
        $ticketDataBody.html('<tr class="loading-row"><td colspan="5"><i class="fa fa-spinner fa-pulse"></i> Contacting Support Site for tickets...</td></tr>');

        $.ajax({
            url: mainwpFluentSupport.ajaxurl,
            type: 'POST',
            data: {
                action: fetchAction,
                security: mainwpFluentSupport.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Success: Update the table with the returned HTML
                    $ticketDataBody.html(response.data.html);

                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-green')
                        .html('<strong>Success!</strong> Ticket data updated.')
                        .slideDown();
                } else {
                    // Failure: The PHP handler returned wp_send_json_error or the remote site returned an error
                    $ticketDataBody.html(response.data.html || '<tr><td colspan="5">An error occurred while fetching data. Check Settings and try refreshing the page.</td></tr>');

                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-red')
                        .html('<strong>Error:</strong> ' + (response.data.message || 'Unknown Error (Check PHP logs)'))
                        .slideDown();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // Network/Timeout Error (Most likely cause if the page just sits there)
                $ticketDataBody.html('<tr><td colspan="5">Network Error: Request timed out or was blocked. Status: ' + textStatus + '</td></tr>');

                $messageDiv
                    .addClass('mainwp-notice mainwp-notice-red')
                    .html('<strong>Fatal Network Error:</strong> Could not reach the server. Check Dashboard health.')
                    .slideDown();
            },
            complete: function () {
                // Re-enable the button regardless of success/failure
                $fetchButton.prop('disabled', false).text('Fetch Latest Tickets');
            }
        });
    });

    // -------------------------
    // 2. SAVE SETTINGS LOGIC (Included for completeness - no changes here)
    // -------------------------
    $saveButton.on('click', function (e) {
        e.preventDefault();

        $saveButton.prop('disabled', true).text('Saving...');
        $messageDiv.hide().removeClass().empty();

        var formData = {
            action: saveAction,
            security: mainwpFluentSupport.nonce,
            fluentsupport_site_id: $('#fluentsupport_site_selection').val() 
        };

        $.ajax({
            url: mainwpFluentSupport.ajaxurl,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    location.reload(); 
                } else {
                    $saveButton.prop('disabled', false).text('Save Settings');

                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-red')
                        .html('<strong>Error:</strong> ' + response.data.message)
                        .slideDown();
                }
            },
            error: function () {
                $saveButton.prop('disabled', false).text('Save Settings');

                $messageDiv
                    .addClass('mainwp-notice mainwp-notice-red')
                    .html('<strong>Fatal Error:</strong> Failed to connect to save settings.')
                    .slideDown();
            },
            complete: function () {
                // Button re-enabled and message shown by success/error handlers
            }
        });
    });
});
