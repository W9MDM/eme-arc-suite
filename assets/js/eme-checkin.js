jQuery(document).ready(function($) {
    $('#eme-checkin-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $message = $('#checkin-message');
        var $list = $('#checkin-list');

        $.ajax({
            url: eme_checkin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'eme_checkin_submit',
                nonce: eme_checkin_ajax.nonce,
                event_id: $('#event-select').val(),
                callsign: $('#callsign-input').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.html('<span class="success">' + response.data.message + '</span>');
                    $list.html(response.data.list);
                    $form[0].reset();
                } else {
                    $message.html('<span class="error">' + response.data + '</span>');
                }
                setTimeout(function() { $message.empty(); }, 5000);
            },
            error: function() {
                $message.html('<span class="error">An error occurred. Please try again.</span>');
            }
        });
    });
});