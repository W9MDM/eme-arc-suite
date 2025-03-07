<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Records a member check-in using a callsign, mapping it to person_id.
 */
function eme_member_checkin_record($callsign) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_member_checkins';
    $answers_table = $wpdb->prefix . 'eme_answers';
    $callsign = sanitize_text_field(strtoupper(trim($callsign)));
    $current_time = current_time('mysql');
    $event_id = eme_member_checkin_get_active_event_id();

    // Map callsign to person_id
    $person_id = $wpdb->get_var($wpdb->prepare(
        "SELECT related_id 
         FROM $answers_table 
         WHERE type = 'person' AND field_id = 2 AND answer = %s 
         LIMIT 1",
        $callsign
    ));

    if (!$person_id) {
        error_log("Check-in failed: No person found for callsign $callsign.");
        return false;
    }

    error_log("Attempting check-in for callsign $callsign (person_id $person_id) at $current_time, Event ID: " . ($event_id ?? 'none'));

    if (!$event_id) {
        error_log("No active event, recording check-in without event_id.");
    }

    // Prepare the query with correct number of placeholders based on event_id
    $query = "SELECT COUNT(*) FROM $table_name WHERE person_id = %d AND DATE(checkin_time) = %s";
    $args = [$person_id, date('Y-m-d', strtotime($current_time))];
    if ($event_id) {
        $query .= " AND event_id = %d";
        $args[] = $event_id;
    }

    $existing_checkin = $wpdb->get_var($wpdb->prepare($query, ...$args));

    if ($existing_checkin > 0) {
        error_log("Check-in failed: Callsign $callsign (person_id $person_id) already checked in today" . ($event_id ? " for event $event_id" : "."));
        return false;
    }

    $result = $wpdb->insert(
        $table_name,
        [
            'person_id' => $person_id,
            'checkin_time' => $current_time,
            'event_id' => $event_id
        ],
        ['%d', '%s', $event_id ? '%d' : '%s']
    );

    error_log("Check-in result for callsign $callsign (person_id $person_id): " . ($result !== false ? 'Success' : 'Failed'));
    return $result !== false;
}

/**
 * Retrieves the list of all check-ins for today with person details and event names.
 */
function eme_member_checkin_get_list() {
    global $wpdb;
    $checkins_table = $wpdb->prefix . 'eme_member_checkins';
    $people_table = $wpdb->prefix . 'eme_people';
    $answers_table = $wpdb->prefix . 'eme_answers';
    $events_table = $wpdb->prefix . 'eme_events';
    $today = date('Y-m-d', strtotime(current_time('mysql')));
    $event_id = eme_member_checkin_get_active_event_id();

    // Fetch check-ins with person name (first + last), callsign, and event name
    $checkins = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            c.person_id,
            c.checkin_time,
            c.event_id,
            p.firstname AS person_firstname,
            p.lastname AS person_lastname,
            a.answer AS callsign,
            e.event_name
         FROM $checkins_table c
         LEFT JOIN $people_table p ON p.person_id = c.person_id
         LEFT JOIN $answers_table a ON a.related_id = c.person_id AND a.type = 'person' AND a.field_id = 2
         LEFT JOIN $events_table e ON e.event_id = c.event_id
         WHERE DATE(c.checkin_time) = %s 
         ORDER BY c.checkin_time DESC",
        $today
    ));

    if (empty($checkins)) {
        return '<p>No check-ins recorded today (' . esc_html($today) . ').</p>';
    }

    $header = '<h3>Today’s Attendees (' . esc_html($today) . ')</h3>';
    if ($event_id) {
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT event_name FROM $events_table WHERE event_id = %d",
            $event_id
        ));
        if ($event) {
            $header = sprintf('<h3>Today’s Attendees for %s (%s)</h3>', esc_html($event->event_name), esc_html($today));
        }
    }

    $output = $header;
    $output .= '<table class="eme-checkin-table"><thead><tr><th>Person ID</th><th>Name</th><th>Callsign</th><th>Check-In Time</th><th>Event Name</th></tr></thead><tbody>';
    foreach ($checkins as $checkin) {
        // Combine first and last name, fallback to "N/A" if both are missing
        $full_name = trim($checkin->person_firstname . ' ' . $checkin->person_lastname);
        $display_name = !empty($full_name) ? $full_name : 'N/A';
        $output .= sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            esc_html($checkin->person_id),
            esc_html($display_name),
            esc_html($checkin->callsign ?? 'N/A'),
            esc_html(date('H:i:s', strtotime($checkin->checkin_time))),
            esc_html($checkin->event_name ?? 'None')
        );
    }
    $output .= '</tbody></table>';
    return $output;
}

/**
 * Gets the active event ID based on current time, allowing 1 hour and 10 minutes before start.
 */
function eme_member_checkin_get_active_event_id() {
    global $wpdb;
    $events_table = $wpdb->prefix . 'eme_events';
    $current_time = current_time('mysql');
    $current_timestamp = strtotime($current_time);

    error_log("Checking active event at $current_time, timestamp: $current_timestamp");

    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT event_id 
         FROM $events_table 
         WHERE event_status = 5 
         AND UNIX_TIMESTAMP(event_start) - 4200 <= %d 
         AND UNIX_TIMESTAMP(event_end) >= %d 
         LIMIT 1",
        $current_timestamp,
        $current_timestamp
    ));

    $active_event_id = $event ? $event->event_id : null;
    error_log("Active event ID: " . ($active_event_id ?? 'none'));
    return $active_event_id;
}

/**
 * Creates the check-ins table with person_id if it doesn’t exist.
 */
function eme_member_checkin_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_member_checkins';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        person_id BIGINT(20) NOT NULL,
        checkin_time DATETIME NOT NULL,
        event_id BIGINT(20) UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        KEY person_id (person_id),
        KEY event_id (event_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function eme_member_checkin_activate() {
    eme_member_checkin_create_table();
}

register_activation_hook(__FILE__, 'eme_member_checkin_activate');