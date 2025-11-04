jQuery(document).ready(function ($) {

    var $fetchButton = $('#mainwp-fluentsupport-fetch-btn');
    var $saveButton = $('#mainwp-fluentsupport-save-settings-btn');
    var $testButton = $('#mainwp-fluentsupport-test-api-btn'); 
    var $ticketDataBody = $('#fluentsupport-ticket-data');
    var $messageDiv = $('#mainwp-fluentsupport-message');
    var fetchAction = 'mainwp_fluentsupport_fetch_tickets';
    var saveAction = 'mainwp_fluentsupport_save_settings';
    var testAction = 'mainwp_fluentsupport_test_api'; 

    // Helper to get form data for saving/testing/fetching
    var getFormData = function(action) {
        return {
            action: action,
            security: mainwpFluentSupport.nonce,
            fluentsupport_site_url: $('#fluentsupport_site_url').val(), 
            fluentsupport_api_username: $('#fluentsupport_api_username').val(),
            fluentsupport_api_password: $('#fluentsupport_api_password').val()
        };
    };

    // -------------------------
    // 1. FETCH TICKETS LOGIC
    // -------------------------
    $fetchButton.on('click', function (e) {
        e.preventDefault();

        // 1. Initial UI Lock and Message Display (CONFIRMING BUTTON IS PRESSED)
        $fetchButton.prop('disabled', true).text('Fetching Tickets...');
        $messageDiv.hide().removeClass().empty();
        console.log('--- FluentSupport AJAX Request Started ---');

        // Show immediate loading indicator
        $ticketDataBody.html('<tr class="loading-row"><td colspan="5"><i class="fa fa-spinner fa-pulse"></i> Contacting Support Site for tickets...</td></tr>');
        
        // Update debug box before sending
        $('#mainwp-fluentsupport-ajax-result').text('Sending request...'); 

        $.ajax({
            url: mainwpFluentSupport.ajaxurl,
            type: 'POST',
            data: getFormData(fetchAction), // Use helper function to get data
            timeout: 60000, // CRITICAL FIX: Increased client-side timeout to 60 seconds
            success: function (response) {
                console.log('--- FluentSupport AJAX Request Succeeded ---');
                // ENHANCED DEBUGGING: Display the raw response in the debug box
                $('#mainwp-fluentsupport-ajax-result').text(JSON.stringify(response, null, 2));

                if (response.success) {
                    // Success: Update the table with the returned HTML
                    $ticketDataBody.html(response.data.html);

                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-green')
                        .html('<strong>Success!</strong> Ticket data updated.')
                        .slideDown();
                } else {
                    // Failure: The PHP handler returned wp_send_json_error or the remote site returned an error
                    $ticketDataBody.html(response.data.html || '<tr><td colspan="5">An error occurred while fetching data. Check Settings and Debug details below.</td></tr>');

                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-red')
                        .html('<strong>Error:</strong> ' + (response.data.message || 'Unknown Error (Check PHP logs)'))
                        .slideDown();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log('--- FluentSupport AJAX Request FAILED (Network/Timeout) ---');
                
                // *** CRITICAL DEBUG UPDATE ***: Capture network error details
                var debugError = {
                    "Network Status": textStatus,
                    "Error Thrown": errorThrown,
                    "jqXHR ReadyState": jqXHR.readyState,
                    "Possible Cause": "PHP Memory or Time Limit Exceeded on MainWP Dashboard Server"
                };
                // Ensure the debug box is updated here
                $('#mainwp-fluentsupport-ajax-result').text(JSON.stringify(debugError, null, 2)); 
                
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
    // 2. SAVE SETTINGS LOGIC 
    // -------------------------
    $saveButton.on('click', function (e) {
        e.preventDefault();

        $saveButton.prop('disabled', true).text('Saving...');
        $messageDiv.hide().removeClass().empty();

        $.ajax({
            url: mainwpFluentSupport.ajaxurl,
            type: 'POST',
            data: getFormData(saveAction),
            success: function (response) {
                if (response.success) {
                    $saveButton.prop('disabled', false).text('Save Settings');
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-green')
                        .html('<strong>Success!</strong> Settings saved.')
                        .slideDown();
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
    
    // -------------------------
    // 3. TEST API CONNECTION LOGIC
    // -------------------------
    $testButton.on('click', function (e) {
        e.preventDefault();

        $testButton.prop('disabled', true).text('Testing...');
        $messageDiv.hide().removeClass().empty();

        $.ajax({
            url: mainwpFluentSupport.ajaxurl,
            type: 'POST',
            data: getFormData(testAction),
            timeout: 30000, // Use a shorter timeout for test, 30s
            success: function (response) {
                $testButton.prop('disabled', false).text('Test API Connection');
                if (response.success) {
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-green')
                        .html('<strong>Test Successful!</strong> ' + response.data.message)
                        .slideDown();
                } else {
                    $messageDiv
                        .addClass('mainwp-notice mainwp-notice-red')
                        .html('<strong>Test Failed:</strong> ' + response.data.message)
                        .slideDown();
                }
            },
            error: function () {
                $testButton.prop('disabled', false).text('Test API Connection');

                $messageDiv
                    .addClass('mainwp-notice mainwp-notice-red')
                    .html('<strong>Fatal Error:</strong> Network connection failed during test.')
                    .slideDown();
            }
        });
    });
});
