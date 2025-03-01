<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_force_callsign_pre_insert_user_data($data, $update, $id) {
    global $wpdb;

    error_log("EME Force Callsign: Entering wp_pre_insert_user_data - Update: " . ($update ? 'true' : 'false') . ", Data: " . print_r($data, true));

    if ($update) {
        error_log("EME Force Callsign: Skipping update, only handling new user creation.");
        return $data;
    }

    error_log("EME Force Callsign: Querying EME person for email: {$data['user_email']}");
    $person = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT person_id FROM {$wpdb->prefix}eme_people WHERE email = %s",
            $data['user_email']
        ),
        ARRAY_A
    );

    if (!$person) {
        error_log("EME Force Callsign: No EME person found for email {$data['user_email']}");
        return $data;
    }

    $person_id = $person['person_id'];
    error_log("EME Force Callsign: Found EME person_id: $person_id");

    error_log("EME Force Callsign: Querying callsign for person_id: $person_id");
    $callsign = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT answer 
             FROM {$wpdb->prefix}eme_answers 
             WHERE related_id = %d AND type = 'person' AND field_id = 2",
            $person_id
        )
    );

    if (!$callsign) {
        error_log("EME Force Callsign: No callsign (answer_2) found for person_id: $person_id");
        return $data;
    }

    $callsign = strtoupper($callsign);
    $sanitized_callsign = sanitize_user($callsign, false);
    error_log("EME Force Callsign: Callsign retrieved: $callsign, Sanitized: $sanitized_callsign");

    if (username_exists($sanitized_callsign)) {
        error_log("EME Force Callsign: Username $sanitized_callsign already exists, generating unique version.");
        $counter = 1;
        $base_callsign = $sanitized_callsign;
        do {
            $sanitized_callsign = $base_callsign . $counter;
            $counter++;
        } while (username_exists($sanitized_callsign));
        error_log("EME Force Callsign: Generated unique username: $sanitized_callsign");
    }

    $data['user_login'] = $sanitized_callsign;
    $data['user_nicename'] = $sanitized_callsign;
    $data['display_name'] = $callsign;

    error_log("EME Force Callsign: Modified user data (pre-insert) - " . print_r($data, true));
    return $data;
}

function eme_force_callsign_on_user_register($user_id) {
    global $wpdb;

    error_log("EME Force Callsign: User registered, WP user_id: $user_id");

    $person = eme_get_person_by_wp_id($user_id);
    if (!$person) {
        error_log("EME Force Callsign: No EME person linked to WP user_id $user_id, checking by email.");
        $user = get_userdata($user_id);
        $person = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT person_id FROM {$wpdb->prefix}eme_people WHERE email = %s",
                $user->user_email
            ),
            ARRAY_A
        );
        if (!$person) {
            error_log("EME Force Callsign: No EME person found for WP user_id $user_id with email {$user->user_email}");
            return;
        }
    }

    $person_id = $person['person_id'];
    error_log("EME Force Callsign: Found EME person_id: $person_id for WP user_id: $user_id");

    $callsign = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT answer 
             FROM {$wpdb->prefix}eme_answers 
             WHERE related_id = %d AND type = 'person' AND field_id = 2",
            $person_id
        )
    );

    if (!$callsign) {
        error_log("EME Force Callsign: No callsign (answer_2) found for person_id: $person_id, WP user_id: $user_id");
        return;
    }

    $callsign = strtoupper($callsign);
    $sanitized_callsign = sanitize_user($callsign, false);
    error_log("EME Force Callsign: Callsign: $callsign, Sanitized: $sanitized_callsign for user_id: $user_id");

    $user = get_userdata($user_id);

    update_user_meta($user_id, 'nickname', $callsign);
    if ($user->user_nicename !== $sanitized_callsign || $user->display_name !== $callsign) {
        $update_data = [
            'ID' => $user_id,
            'user_nicename' => $sanitized_callsign,
            'display_name' => $callsign
        ];
        error_log("EME Force Callsign: Updating user data for user_id $user_id - " . print_r($update_data, true));
        wp_update_user($update_data);
    }
    error_log("EME Force Callsign: Set nickname to $callsign and synced user_nicename/display_name for user $user_id");

    if (!$person['wp_id']) {
        error_log("EME Force Callsign: Linking WP user $user_id to EME person $person_id");
        $wpdb->update(
            "{$wpdb->prefix}eme_people",
            ['wp_id' => $user_id],
            ['person_id' => $person_id],
            ['%d'],
            ['%d']
        );
        error_log("EME Force Callsign: Linked WP user $user_id to EME person $person_id");
    }
}

function eme_force_callsign_on_profile_update($user_id) {
    global $wpdb;

    error_log("EME Force Callsign: Profile update triggered for WP user_id: $user_id");

    $person = eme_get_person_by_wp_id($user_id);
    if (!$person) {
        error_log("EME Force Callsign: No EME person found for WP user_id $user_id on profile update");
        return;
    }

    $person_id = $person['person_id'];
    error_log("EME Force Callsign: Found EME person_id: $person_id for profile update");

    $callsign = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT answer 
             FROM {$wpdb->prefix}eme_answers 
             WHERE related_id = %d AND type = 'person' AND field_id = 2",
            $person_id
        )
    );

    if ($callsign) {
        $callsign = strtoupper($callsign);
        $sanitized_callsign = sanitize_user($callsign, false);
        error_log("EME Force Callsign: Updating nickname and nicename to $callsign/$sanitized_callsign for user_id: $user_id");
        update_user_meta($user_id, 'nickname', $callsign);
        wp_update_user(['ID' => $user_id, 'user_nicename' => $sanitized_callsign]);
        error_log("EME Force Callsign: Synced nickname and user_nicename for user $user_id on profile update");
    } else {
        error_log("EME Force Callsign: No callsign found for person_id: $person_id on profile update");
    }
}

add_filter('wp_pre_insert_user_data', 'eme_force_callsign_pre_insert_user_data', 10, 3);
add_action('user_register', 'eme_force_callsign_on_user_register');
add_action('profile_update', 'eme_force_callsign_on_profile_update');