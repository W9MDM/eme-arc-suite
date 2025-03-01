jQuery(document).ready(function($) {
    $('#eme-membership-form').on('submit', function(e) {
        e.preventDefault(); // Prevent traditional form submission

        var callsign = $('#callsign-input').val();
        if (!callsign) {
            $('#membership-status').html('<p class="error">Please enter a callsign.</p>');
            return;
        }

        $.ajax({
            url: eme_membership_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'eme_membership_check_status',
                nonce: eme_membership_ajax.nonce,
                callsign: callsign
            },
            success: function(response) {
                if (response.success) {
                    $('#membership-status').html(response.data);
                } else {
                    $('#membership-status').html('<p class="error">' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#membership-status').html('<p class="error">An error occurred. Please try again.</p>');
                console.log('AJAX error:', status, error);
            }
        });
    });
});