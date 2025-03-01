jQuery(document).ready(function($) {
    $('#eme-member-checkin-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $message = $('#checkin-message');
        var $list = $('#checkin-list');

        $.ajax({
            url: eme_member_checkin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'eme_member_checkin_submit',
                nonce: eme_member_checkin_ajax.nonce,
                callsign: $('#callsign-input').val()
            },
            success: function(response) {
                console.log('AJAX Response:', response); // Debug log

                if (response.success) {
                    $message.html('<span class="success">' + response.data.message + '</span>');
                    $list.html(response.data.list);
                    $form[0].reset();

                    // Check for new person data and show popup
                    if (response.data.new_person) {
                        console.log('New person data received:', response.data.new_person); // Debug log
                        var popupContent = '<ul>';
                        for (var key in response.data.new_person.data) {
                            popupContent += '<li><strong>' + key + ':</strong> ' + response.data.new_person.data[key] + '</li>';
                        }
                        popupContent += '</ul>';

                        Swal.fire({
                            title: response.data.new_person.message,
                            html: popupContent,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    }

                    setTimeout(function() { $message.empty(); }, 5000);
                } else {
                    $message.html('<span class="error">' + response.data + '</span>');
                    setTimeout(function() { $message.empty(); }, 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error); // Debug log
                $message.html('<span class="error">An error occurred. Please try again.</span>');
                setTimeout(function() { $message.empty(); }, 5000);
            }
        });
    });
});