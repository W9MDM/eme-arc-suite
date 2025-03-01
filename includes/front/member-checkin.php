<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_member_checkin_enqueue_assets() {
    wp_enqueue_script('eme-member-checkin-ajax', EME_ARC_SUITE_URL . 'assets/js/eme-member-checkin.js', ['jquery'], '1.3.0', true);
    wp_localize_script('eme-member-checkin-ajax', 'eme_member_checkin_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('eme_member_checkin_nonce')
    ]);
    wp_enqueue_style('eme-member-checkin-style', EME_ARC_SUITE_URL . 'assets/css/eme-member-checkin.css', [], '1.3.0');
}

function eme_member_checkin_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_member_checkins';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        person_id BIGINT(20) NOT NULL,
        checkin_time DATETIME NOT NULL,
        PRIMARY KEY (id),
        INDEX checkin_time (checkin_time)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function eme_member_checkin_shortcode() {
    ob_start();
    ?>
    <div class="eme-member-checkin-dashboard">
        <h2>Member Check-In</h2>
        <form id="eme-member-checkin-form">
            <label for="callsign-input">Your Callsign:</label>
            <input type="text" id="callsign-input" name="callsign" required>
            <button type="submit">Check In</button>
            <div id="checkin-message"></div>
        </form>
        <h3>Today’s Attendees</h3>
        <div id="checkin-list">
            <?php echo eme_member_checkin_get_list(); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function eme_member_checkin_get_list() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_member_checkins';
    $today = current_time('Y-m-d 00:00:00');
    error_log("Fetching check-ins for today: $today");

    $query = $wpdb->prepare(
        "SELECT p.person_id, p.lastname, p.firstname, a.answer as callsign, 
                c.checkin_time, m.membership_id, m.end_date, mem.name as membership_name,
                c.id as checkin_id
         FROM $table_name c
         JOIN {$wpdb->prefix}eme_people p ON c.person_id = p.person_id
         JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id 
            AND a.type = 'person' AND a.field_id = 2
         LEFT JOIN {$wpdb->prefix}eme_members m ON p.person_id = m.person_id
         LEFT JOIN {$wpdb->prefix}eme_memberships mem ON m.membership_id = mem.membership_id
         WHERE c.checkin_time >= %s
         ORDER BY c.checkin_time DESC",
        $today
    );
    $checkins = $wpdb->get_results($query);

    error_log("Query executed: $query");
    error_log("Check-ins found: " . count($checkins));
    if ($wpdb->last_error) {
        error_log("DB Error: " . $wpdb->last_error);
    }

    if (empty($checkins)) {
        error_log("No check-ins returned for today.");
        return '<p>No check-ins today yet.</p>';
    }

    error_log("Generating check-in list for " . count($checkins) . " records.");
    ob_start();
    echo '<ul>';
    foreach ($checkins as $checkin) {
        $membership_info = '';
        $new_person_note = '';
        if ($checkin->membership_id) {
            $expiration = $checkin->end_date ? date('Y-m-d', strtotime($checkin->end_date)) : 'N/A';
            $membership_info = " - {$checkin->membership_name} (Expires: {$expiration})";
        }
        $total_checkins = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE person_id = %d", $checkin->person_id)
        );
        if ($total_checkins == 1 && $checkin->checkin_id == $wpdb->get_var(
            $wpdb->prepare("SELECT MIN(id) FROM $table_name WHERE person_id = %d", $checkin->person_id)
        )) {
            $new_person_note = ' <span class="new-person-note">(New Member)</span>';
        }
        
        echo '<li>' . 
             esc_html($checkin->callsign) . ' (' . 
             esc_html($checkin->firstname . ' ' . $checkin->lastname) . ') - Checked in: ' . 
             esc_html(date('H:i', strtotime($checkin->checkin_time))) . 
             esc_html($membership_info) . 
             $new_person_note . 
             '</li>';
    }
    echo '</ul>';
    $output = ob_get_clean();
    error_log("Output generated: " . substr($output, 0, 100));
    return $output;
}

function eme_member_checkin_submit() {
    check_ajax_referer('eme_member_checkin_nonce', 'nonce');

    global $wpdb;
    $callsign = sanitize_text_field(strtoupper($_POST['callsign']));
    $table_name = $wpdb->prefix . 'eme_member_checkins';

    if (!preg_match('/^[A-Za-z0-9]{3,7}$/', $callsign)) {
        wp_send_json_error('Invalid callsign format. Must be 3-7 alphanumeric characters.');
    }

    $person = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.person_id
             FROM {$wpdb->prefix}eme_people p
             JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id 
             AND a.type = 'person' 
             AND a.field_id = 2
             WHERE a.answer = %s",
            $callsign
        )
    );

    if (!$person) {
        $api_url = "https://api.hamdb.org/v1/{$callsign}/xml/legacy-api";
        $response = wp_remote_get($api_url, ['timeout' => 10]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            wp_send_json_error('Invalid callsign or API error. Please verify your callsign.');
        }

        $xml_body = wp_remote_retrieve_body($response);
        $xml = @simplexml_load_string($xml_body);

        if (!$xml || !isset($xml->callsign->call) || empty($xml->callsign->call)) {
            wp_send_json_error('Callsign not found in HamDB database.');
        }

        $api_callsign = (string) $xml->callsign->call ?? '';
        $firstname = (string) $xml->callsign->fname ?? '';
        $lastname = (string) $xml->callsign->name ?? '';

        $invalid_values = ['not_found', 'n/a', '', null];
        if (in_array(strtolower($api_callsign), $invalid_values) ||
            in_array(strtolower($firstname), $invalid_values) ||
            in_array(strtolower($lastname), $invalid_values)) {
            $missing_fields = [];
            if (in_array(strtolower($api_callsign), $invalid_values)) $missing_fields[] = 'callsign';
            if (in_array(strtolower($firstname), $invalid_values)) $missing_fields[] = 'first name';
            if (in_array(strtolower($lastname), $invalid_values)) $missing_fields[] = 'last name';
            wp_send_json_error('HamDB API lookup failed: missing or invalid required field(s): ' . implode(', ', $missing_fields) . '.');
        }

        $people_data = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'address1' => (string) $xml->callsign->addr1 ?? '',
            'city' => (string) $xml->callsign->addr2 ?? '',
            'state_code' => (string) $xml->callsign->state ?? '',
            'zip' => (string) $xml->callsign->zip ?? '',
            'country_code' => ((string) $xml->callsign->country === 'United States') ? 'US' : (string) $xml->callsign->country ?? ''
        ];

        $answers = [
            2 => $api_callsign
        ];
        $expires = (string) $xml->callsign->expires ?? '';
        if (!empty($expires)) {
            $expires_date = DateTime::createFromFormat('m/d/Y', $expires);
            $answers[4] = $expires_date ? $expires_date->format('Y-m-d') : '';
        }
        $class = (string) $xml->callsign->class ?? '';
        $class_map = ['T' => 'Technician', 'G' => 'General', 'E' => 'Extra'];
        if (!empty($class) && isset($class_map[$class])) {
            $answers[6] = $class_map[$class];
        }

        $person_id = eme_db_insert_person($people_data);
        if ($person_id) {
            foreach ($answers as $field_id => $answer) {
                $wpdb->insert(
                    "{$wpdb->prefix}eme_answers",
                    [
                        'related_id' => $person_id,
                        'type' => 'person',
                        'field_id' => $field_id,
                        'answer' => $answer
                    ],
                    ['%d', '%s', '%d', '%s']
                );
            }
            $person = (object) ['person_id' => $person_id];
            error_log("New person added for callsign {$callsign}");
        } else {
            wp_send_json_error('Failed to create new person record.');
        }
    }

    $person_id = $person->person_id;

    $wpdb->insert(
        $table_name,
        [
            'person_id' => $person_id,
            'checkin_time' => current_time('mysql')
        ],
        ['%d', '%s']
    );

    if ($wpdb->insert_id) {
        $list = eme_member_checkin_get_list();
        wp_send_json_success([
            'message' => 'Checked in successfully!',
            'list' => $list
        ]);
    } else {
        wp_send_json_error('Check-in failed. Please try again.');
    }
}

add_action('wp_enqueue_scripts', 'eme_member_checkin_enqueue_assets');
add_shortcode('eme_member_checkin', 'eme_member_checkin_shortcode');
add_action('wp_ajax_eme_member_checkin_submit', 'eme_member_checkin_submit');
add_action('wp_ajax_nopriv_eme_member_checkin_submit', 'eme_member_checkin_submit');