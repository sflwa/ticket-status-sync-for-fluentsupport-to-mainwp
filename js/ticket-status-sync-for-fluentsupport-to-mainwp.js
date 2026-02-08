/**
 * Ticket Status Sync for FluentSupport to MainWP - JavaScript
 * * Handles AJAX interactions for ticket fetching.
 * Settings are saved via traditional form POST.
 */
jQuery(document).ready(function ($) {

    var $fetchButton = $('#mainwp-fluentsupport-fetch-btn');
    var $ticketDataBody = $('#fluentsupport-ticket-data');
    var $messageDiv = $('#mainwp-fluentsupport-message');

    /**
     * Helper to clear and show message
     * Targets all instances of the message ID to handle MainWP UI complexity.
     */
    var showMessage = function(msg, type) {
        var $allMessages = $('#mainwp-fluentsupport-message');
        $allMessages.hide().removeClass('mainwp-notice-green mainwp-notice-red mainwp-notice-blue').empty();
        
        var noticeClass = type === 'success' ? 'mainwp-notice-green' : 'mainwp-notice-red';
        var prefix = type === 'success' ? '<strong>Success!</strong> ' : '<strong>Error:</strong> ';
        
        $allMessages.addClass('mainwp-notice ' + noticeClass).html(prefix + msg).slideDown();
    };

    /**
     * FETCH TICKETS (AJAX)
     * Manually triggers a synchronization with the Support Site.
     */
    $fetchButton.on('click', function (e) {
        e.preventDefault();

        $fetchButton.prop('disabled', true).text('Fetching Tickets...');
        $('#mainwp-fluentsupport-message').hide();
        $ticketDataBody.html('<tr class="loading-row"><td colspan="4"><i class="fa fa-spinner fa-pulse"></i> Syncing with Support Site...</td></tr>');

        $.ajax({
            url: ticketStatusSync.ajaxurl,
            type: 'POST',
            data: {
                action: 'mainwp_fluentsupport_fetch_tickets',
                security: ticketStatusSync.security
            },
            success: function (response) {
                if (response.success) {
                    if (response.data.html) {
                        $ticketDataBody.html(response.data.html);
                    }
                    showMessage(response.data.message || 'Ticket data updated.', 'success');
                } else {
                    $ticketDataBody.html('<tr><td colspan="4">Failed to fetch tickets.</td></tr>');
                    showMessage(response.data.message || 'An error occurred during sync.', 'error');
                }
            },
            error: function () {
                $ticketDataBody.html('<tr><td colspan="4">Network error during sync.</td></tr>');
                showMessage('Fatal Error: Request timed out or was blocked.', 'error');
            },
            complete: function () {
                $fetchButton.prop('disabled', false).text('Fetch Latest Tickets');
            }
        });
    });

    /**
     * SAVE SETTINGS
     * Traditional Form POST handles the save. No JS listener required.
     */
});
