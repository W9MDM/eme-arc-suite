<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_membership_enqueue_assets() {
    wp_enqueue_script('eme-membership-ajax', EME_ARC_SUITE_URL . 'assets/js/eme-membership.js', ['jquery'], '1.0.0', true);
    wp_localize_script('eme-membership-ajax', 'eme_membership_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('eme_membership_nonce')
    ]);
    wp_enqueue_style('eme-membership-style', EME_ARC_SUITE_URL . 'assets/css/eme-membership.css', [], '1.0.0');
}

function eme_membership_renewal_shortcode() {
    ob_start();
    ?>
    <div class="eme-membership-dashboard">
        <h2>Membership Renewal</h2>
        <form id="eme-membership-form" action="#" method="post">
            <label for="callsign-input">Enter Your Callsign:</label>
            <input type="text" id="callsign-input" name="callsign" required>
            <button type="submit">Check Status</button>
        </form>
        <div id="membership-status"></div>
    </div>
    <?php
    return ob_get_clean();
}

function eme_membership_check_status() {
    check_ajax_referer('eme_membership_nonce', 'nonce');
    global $wpdb;

    $table_check = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}eme_members'");
    if (!$table_check) {
        wp_send_json_error('Membership database table (eme_members) not found.');
    }

    $callsign = sanitize_text_field(strtoupper($_POST['callsign']));
    $person = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.person_id, p.lastname, p.firstname, m.status, m.membership_id, a.answer as callsign, mem.name as membership_name
             FROM {$wpdb->prefix}eme_people p
             JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id AND a.type = 'person' AND a.field_id = 2
             JOIN {$wpdb->prefix}eme_members m ON p.person_id = m.person_id
             LEFT JOIN {$wpdb->prefix}eme_memberships mem ON m.membership_id = mem.membership_id
             WHERE a.answer = %s",
            $callsign
        )
    );

    if (!$person) {
        wp_send_json_error('Callsign not found in membership database.');
    }

    $status_class = ($person->status == 100) ? 'expired' : 'active';
    $status_text = ($person->status == 100) ? 'Expired' : 'Active';

    $output = '<p class="' . $status_class . '">Callsign: ' . esc_html($callsign) . '<br>';
    $output .= 'Name: ' . esc_html($person->firstname . ' ' . $person->lastname) . '<br>';
    $output .= 'Membership Type: ' . esc_html($person->membership_name ? $person->membership_name : 'Unknown') . ' (ID: ' . esc_html($person->membership_id) . ')<br>';
    $output .= 'Status: ' . $status_text . '</p>';

    if ($person->status == 100) {
        $renewal_pages = [
            2 => 'youth-membership',
            3 => 'regular-adult-membership',
            4 => 'regular-family-membership',
            5 => 'regular-senior-membership'
        ];

        $renewal_slug = isset($renewal_pages[$person->membership_id]) ? $renewal_pages[$person->membership_id] : '';
        if ($renewal_slug) {
            $renewal_url = get_permalink(get_page_by_path($renewal_slug));
            if ($renewal_url) {
                $output .= '<a href="' . esc_url($renewal_url) . '" class="renew-button">Renew Now</a>';
            } else {
                $output .= '<p class="error">Renewal page not found for this membership type: ' . esc_html($person->membership_name ? $person->membership_name : 'Unknown') . ' (ID: ' . esc_html($person->membership_id) . ').</p>';
                error_log("Renewal page not found for membership_id: {$person->membership_id}, slug: $renewal_slug");
            }
        } else {
            $output .= '<p class="error">No renewal page configured for this membership type: ' . esc_html($person->membership_name ? $person->membership_name : 'Unknown') . ' (ID: ' . esc_html($person->membership_id) . ').</p>';
            error_log("No renewal page mapped for membership_id: {$person->membership_id}");
        }
    }

    wp_send_json_success($output);
}

add_action('wp_enqueue_scripts', 'eme_membership_enqueue_assets');
add_shortcode('eme_membership_renewal', 'eme_membership_renewal_shortcode');
add_action('wp_ajax_eme_membership_check_status', 'eme_membership_check_status');
add_action('wp_ajax_nopriv_eme_membership_check_status', 'eme_membership_check_status');