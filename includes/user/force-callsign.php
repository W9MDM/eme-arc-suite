<?php
/*
 * Plugin Name: EME Force Callsign
 * Description: Forces WordPress usernames to match callsigns stored in Events Made Easy (EME) plugin tables.
 * Version: 1.0
 * Author: [Your Name]
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access to this file
}

/**
 * Modifies user data before insertion to set username based on EME callsign.
 * Only applies to new user creation, not updates.
 *
 * @param array $data User data array
 * @param bool $update Whether this is an update operation
 * @param int|null $id User ID (null for new users)
 * @return array Modified user data
 */
function eme_force_callsign_pre_insert_user_data($data, $update, $id) {
    global $wpdb;

    // Skip if this is an update operation
    if ($update) {
        return $data;
    }

    // Ensure email exists in data
    if (!isset($data['user_email'])) {
        return $data;
    }

    // Look up EME person by email
    $person = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT person_id FROM {$wpdb->prefix}eme_people WHERE email = %s",
            $data['user_email']
        ),
        ARRAY_A
    );

    // Return unchanged if no EME person found
    if (!$person) {
        return $data;
    }

    $person_id = $person['person_id'];

    // Fetch callsign from EME answers table (field_id 2)
    $callsign = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT answer 
             FROM {$wpdb->prefix}eme_answers 
             WHERE related_id = %d AND type = 'person' AND field_id = 2",
            $person_id
        )
    );

    // Return unchanged if no callsign found
    if (!$callsign) {
        return $data;
    }

    // Format callsign and generate username
    $callsign = strtoupper($callsign);
    $sanitized_callsign = sanitize_user($callsign, false);

    // Ensure username is unique
    if (username_exists($sanitized_callsign)) {
        $counter = 1;
        $base_callsign = $sanitized_callsign;
        do {
            $sanitized_callsign = $base_callsign . $counter;
            $counter++;
        } while (username_exists($sanitized_callsign));
    }

    // Set user data fields
    $data['user_login'] = $sanitized_callsign;
    $data['user_nicename'] = $sanitized_callsign;
    $data['display_name'] = $callsign;

    return $data;
}

/**
 * Syncs EME callsign with WordPress user data after registration.
 *
 * @param int $user_id The ID of the newly registered user
 */
function eme_force_callsign_on_user_register($user_id) {
    global $wpdb;

    // Load WordPress user data
    $user = get_userdata($user_id);
    if (!$user) {
        return; // Bail if user data can't be loaded
    }

    // Try to find EME person by WordPress ID first
    $person = eme_get_person_by_wp_id($user_id);
    if (!$person) {
        // Fallback to email lookup
        $person = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT person_id FROM {$wpdb->prefix}eme_people WHERE email = %s",
                $user->user_email
            ),
            ARRAY_A
        );
        if (!$person) {
            return; // No EME person found
        }
    }

    $person_id = $person['person_id'];

    // Get callsign from EME answers
    $callsign = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT answer 
             FROM {$wpdb->prefix}eme_answers 
             WHERE related_id = %d AND type = 'person' AND field_id = 2",
            $person_id
        )
    );

    if (!$callsign) {
        return; // No callsign available
    }

    // Format callsign
    $callsign = strtoupper($callsign);
    $sanitized_callsign = sanitize_user($callsign, false);

    // Update nickname meta
    update_user_meta($user_id, 'nickname', $callsign);

    // Update user data only if needed
    if ($user->user_nicename !== $sanitized_callsign || $user->display_name !== $callsign) {
        $update_data = [
            'ID' => $user_id,
            'user_nicename' => $sanitized_callsign,
            'display_name' => $callsign
        ];
        wp_update_user($update_data);
    }

    // Link WordPress user to EME person if not already linked
    if (!$person['wp_id']) {
        $wpdb->update(
            "{$wpdb->prefix}eme_people",
            ['wp_id' => $user_id],
            ['person_id' => $person_id],
            ['%d'],
            ['%d']
        );
    }
}

/**
 * Updates WordPress user data when profile is updated, syncing with EME callsign.
 * Prevents infinite loops with recursion check.
 *
 * @param int $user_id The ID of the updated user
 */
function eme_force_callsign_on_profile_update($user_id) {
    global $wpdb;

    // Static flag to prevent recursive calls
    static $is_processing = false;
    if ($is_processing) {
        return; // Skip if already processing
    }

    $is_processing = true;

    // Get EME person data
    $person = eme_get_person_by_wp_id($user_id);
    if (!$person) {
        $is_processing = false;
        return; // No EME person linked
    }

    $person_id = $person['person_id'];

    // Fetch callsign
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

        // Load current user data
        $user = get_userdata($user_id);
        if (!$user) {
            $is_processing = false;
            return; // Bail if user data can't be loaded
        }

        // Check if update is needed
        $needs_update = $user->user_nicename !== $sanitized_callsign;

        // Always update nickname
        update_user_meta($user_id, 'nickname', $callsign);

        // Update user_nicename if different
        if ($needs_update) {
            wp_update_user([
                'ID' => $user_id,
                'user_nicename' => $sanitized_callsign
            ]);
        }
    }

    $is_processing = false; // Reset flag
}

// Register hooks with WordPress
add_filter('wp_pre_insert_user_data', 'eme_force_callsign_pre_insert_user_data', 10, 3);
add_action('user_register', 'eme_force_callsign_on_user_register');
add_action('profile_update', 'eme_force_callsign_on_profile_update');