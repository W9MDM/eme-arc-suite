<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_checkin_enqueue_assets() {
    wp_enqueue_script('eme-checkin-ajax', EME_ARC_SUITE_URL . 'assets/js/eme-checkin.js', ['jquery'], '1.0.0', true);
    wp_localize_script('eme-checkin-ajax', 'eme_checkin_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('eme_checkin_nonce')
    ]);
    wp_enqueue_style('eme-checkin-style', EME_ARC_SUITE_URL . 'assets/css/eme-checkin.css', [], '1.0.0');
}

function eme_checkin_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_checkins';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        event_id BIGINT(20) NOT NULL,
        person_id BIGINT(20) NOT NULL,
        checkin_time DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY event_person (event_id, person_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function eme_event_checkin_shortcode() {
    global $wpdb;

    $events = eme_get_events(0, 'future', 'ASC');
    error_log("EME Event Check-In: Fetched " . count($events) . " future events");

    if (empty($events)) {
        error_log("EME Event Check-In: No upcoming events found");
        return '<p>No upcoming events found.</p>';
    }

    ob_start();
    ?>
    <div class="eme-checkin-dashboard">
        <h2>Event Check-In</h2>
        <form id="eme-checkin-form">
            <label for="event-select">Select Event:</label>
            <select id="event-select" name="event_id">
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo esc_attr($event->event_id); ?>">
                        <?php echo esc_html($event->event_name) . ' (' . date('Y-m-d', strtotime($event->event_start)) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="callsign-input">Your Callsign:</label>
            <input type="text" id="callsign-input" name="callsign" required>
            <button type="submit">Check In</button>
            <div id="checkin-message"></div>
        </form>
        <h3>Checked-In Members</h3>
        <div id="checkin-list">
            <?php echo eme_checkin_get_list($events[0]->event_id); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function eme_checkin_get_list($event_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_checkins';
    $checkins = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.person_id, p.lastname, p.firstname, a.answer as callsign, c.checkin_time
             FROM $table_name c
             JOIN {$wpdb->prefix}eme_people p ON c.person_id = p.person_id
             JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id AND a.type = 'person' AND a.field_id = 2
             WHERE c.event_id = %d
             ORDER BY c.checkin_time DESC",
            $event_id
        )
    );

    if (empty($checkins)) {
        return '<p>No check-ins yet.</p>';
    }

    ob_start();
    echo '<ul>';
    foreach ($checkins as $checkin) {
        echo '<li>' . esc_html($checkin->callsign) . ' (' . esc_html($checkin->firstname . ' ' . $checkin->lastname) . ') - Checked in: ' . esc_html(date('Y-m-d H:i', strtotime($checkin->checkin_time))) . '</li>';
    }
    echo '</ul>';
    return ob_get_clean();
}

function eme_checkin_submit() {
    check_ajax_referer('eme_checkin_nonce', 'nonce');

    global $wpdb;
    $event_id = intval($_POST['event_id']);
    $callsign = sanitize_text_field(strtoupper($_POST['callsign']));
    $table_name = $wpdb->prefix . 'eme_checkins';

    $person = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.person_id
             FROM {$wpdb->prefix}eme_people p
             JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id AND a.type = 'person' AND a.field_id = 2
             WHERE a.answer = %s",
            $callsign
        )
    );

    if (!$person) {
        wp_send_json_error('Callsign not found in membership database.');
    }

    $person_id = $person->person_id;
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE event_id = %d AND person_id = %d",
            $event_id,
            $person_id
        )
    );

    if ($exists) {
        wp_send_json_error('You have already checked into this event.');
    }

    $wpdb->insert(
        $table_name,
        [
            'event_id' => $event_id,
            'person_id' => $person_id,
            'checkin_time' => current_time('mysql')
        ],
        ['%d', '%d', '%s']
    );

    if ($wpdb->insert_id) {
        $list = eme_checkin_get_list($event_id);
        wp_send_json_success(['message' => 'Checked in successfully!', 'list' => $list]);
    } else {
        wp_send_json_error('Check-in failed. Please try again.');
    }
}

add_action('wp_enqueue_scripts', 'eme_checkin_enqueue_assets');
add_shortcode('eme_event_checkin', 'eme_event_checkin_shortcode');
add_action('wp_ajax_eme_checkin_submit', 'eme_checkin_submit');
add_action('wp_ajax_nopriv_eme_checkin_submit', 'eme_checkin_submit');