<?php
/*
 * EME ARC Suite - Admin Member Check-In
 * Description: Admin interface for checking member status and recording attendance
 * File: member-check-admin.php
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the admin member check-in page.
 * Allows admins to check member status by member ID or callsign and record attendance.
 *
 * @return string HTML output of the admin check-in page
 */
function eme_arc_member_check_page() {
    global $wpdb;

    // Permission check: Ensure only users with 'manage_options' capability can access
    if (!current_user_can('manage_options')) {
        return '<div class="wrap"><h1>' . esc_html__('Member Check-In (Admin)', 'events-made-easy') . '</h1><p>' . esc_html__('You do not have permission to access this page.', 'events-made-easy') . '</p></div>';
    }

    $message = '';
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : '';
    $callsign = isset($_POST['callsign']) ? trim(eme_sanitize_request($_POST['callsign'])) : '';

    // Handle form submission
    if (isset($_POST['eme_check_submit']) && check_admin_referer('eme_check_nonce', 'eme_check_nonce')) {
        if (!empty($member_id)) {
            // Check by member ID
            $member = eme_get_member($member_id);
            if (!$member || !eme_is_active_memberid($member_id)) {
                $img = "<img src='" . esc_url(EME_PLUGIN_URL) . "images/error-48.png'>";
                $message = "<div class='eme-message-error eme-member-message-error'>$img " . sprintf(__('NOK: member %d is either not active or does not exist!', 'events-made-easy'), $member_id) . '</div>';
            } else {
                $img = "<img src='" . esc_url(EME_PLUGIN_URL) . "images/good-48.png'>";
                $membership = eme_get_membership($member['membership_id']);
                $message = "<div class='eme-message-success eme-rsvp-message-success'>$img ";
                if (current_user_can(get_option('eme_cap_membercheck')) || current_user_can(get_option('eme_cap_edit_members'))) {
                    eme_update_member_lastseen($member_id);
                    $eme_membership_attendance_msg = eme_nl2br_save_html(get_option('eme_membership_attendance_msg'));
                    $message .= eme_replace_member_placeholders($eme_membership_attendance_msg, $membership, $member);
                    if ($membership['properties']['attendancerecord']) {
                        $res = eme_db_insert_attendance('membership', $member['person_id'], '', $member['membership_id']);
                        if ($res) {
                            $message .= '<br>' . __('Attendance record added', 'events-made-easy');
                        } else {
                            $message .= '<br>' . __('Failed to add attendance record', 'events-made-easy');
                            error_log("Failed to insert attendance for member_id $member_id: " . $wpdb->last_error);
                        }
                    }
                } else {
                    $eme_membership_unauth_attendance_msg = eme_nl2br_save_html(get_option('eme_membership_unauth_attendance_msg'));
                    $message .= eme_replace_member_placeholders($eme_unauth_attendance_msg, $membership, $member);
                }
                $message .= '</div>';
            }
        } elseif (!empty($callsign)) {
            // Check by callsign
            error_log("eme_check_callsign block entered for callsign: $callsign");
            $callsign = strtoupper($callsign);
            $member_id = eme_check_callsign($callsign);
            error_log("Callsign check result: member_id = " . ($member_id ?: '0'));

            if (!$member_id) {
                error_log("Callsign invalid or not active: $callsign");
                $img = "<img src='" . esc_url(EME_PLUGIN_URL) . "images/error-48.png'>";
                $message = "<div class='eme-message-error eme-member-message-error'>$img " . 
                           sprintf(__('NOK: callsign "%s" is either not active or does not exist!', 'events-made-easy'), $callsign) . 
                           "</div>";
            } else {
                $member = eme_get_member($member_id);
                if (!$member) {
                    error_log("No member found for valid callsign: $callsign");
                    $img = "<img src='" . esc_url(EME_PLUGIN_URL) . "images/error-48.png'>";
                    $message = "<div class='eme-message-error eme-member-message-error'>$img " . 
                               __('NOK: No member found for the valid callsign!', 'events-made-easy') . 
                               "</div>";
                } else {
                    error_log("Member found for callsign $callsign: " . print_r($member, true));
                    $img = "<img src='" . esc_url(EME_PLUGIN_URL) . "images/good-48.png'>";
                    $message = "<div class='eme-message-success eme-rsvp-message-success'>$img ";
                    
                    $can_membercheck = current_user_can(get_option('eme_cap_membercheck'));
                    $can_edit_members = current_user_can(get_option('eme_cap_edit_members'));
                    error_log("User permissions - membercheck: " . ($can_membercheck ? 'Yes' : 'No') . ", edit_members: " . ($can_edit_members ? 'Yes' : 'No'));
                    
                    if ($can_membercheck || $can_edit_members) {
                        error_log("User is authorized, proceeding with attendance logic");
                        eme_update_member_lastseen($member_id);
                        $membership = eme_get_membership($member['membership_id']);
                        error_log("Membership retrieved: " . print_r($membership, true));
                        $eme_attendance_msg = eme_nl2br_save_html(get_option('eme_membership_attendance_msg'));
                        $message .= eme_replace_member_placeholders($eme_attendance_msg, $membership, $member);
                        
                        if ($membership['properties']['attendancerecord']) {
                            error_log("attendancerecord is true, attempting to insert attendance for callsign $callsign");
                            $res = eme_db_insert_attendance('membership', $member['person_id'], '', $member['membership_id']);
                            if ($res) {
                                error_log("Attendance recorded successfully for callsign $callsign");
                                $message .= '<br>' . __('Attendance record added', 'events-made-easy');
                            } else {
                                error_log("Attendance recording failed for callsign $callsign: " . $wpdb->last_error);
                                $message .= '<br>' . __('Failed to add attendance record', 'events-made-easy');
                            }
                        } else {
                            error_log("attendancerecord is not set or false for callsign $callsign");
                            $message .= '<br>' . __('Attendance recording not enabled for this membership', 'events-made-easy');
                        }
                    } else {
                        error_log("User not authorized for callsign $callsign, showing unauthorized message");
                        $membership = eme_get_membership($member['membership_id']);
                        $eme_unauth_attendance_msg = eme_nl2br_save_html(get_option('eme_membership_unauth_attendance_msg'));
                        $message .= eme_replace_member_placeholders($eme_unauth_attendance_msg, $membership, $member);
                    }
                    $message .= '</div>';
                }
            }
            error_log("Returning message for callsign $callsign: " . substr($message, 0, 100) . "...");
        } else {
            $message = '<div class="error"><p>' . esc_html__('Please enter a member ID or callsign.', 'events-made-easy') . '</p></div>';
        }
    }

    // Add JavaScript to clear form fields after submission
    $script = "
        document.addEventListener('DOMContentLoaded', function() {
            var callsignInput = document.getElementById('callsign');
            var memberIdInput = document.getElementById('member_id');
            if ('" . (!empty($message) ? 'true' : 'false') . "') {
                callsignInput.value = '';
                memberIdInput.value = '';
                callsignInput.focus();
            }
        });
    ";
    wp_add_inline_script('eme-arc-tabs', $script);

    // Render the admin page
    ob_start();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Member Check-In (Admin)', 'events-made-easy'); ?></h1>
        <?php echo wp_kses_post($message); ?>

        <form method="post" action="">
            <?php wp_nonce_field('eme_check_nonce', 'eme_check_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="member_id"><?php esc_html_e('Enter Member ID', 'events-made-easy'); ?></label></th>
                    <td>
                        <input type="number" name="member_id" id="member_id" value="<?php echo esc_attr($member_id); ?>" class="regular-text" placeholder="e.g., 123">
                        <p class="description"><?php esc_html_e('Enter a member ID to check status.', 'events-made-easy'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="callsign"><?php esc_html_e('OR Enter Callsign', 'events-made-easy'); ?></label></th>
                    <td>
                        <input type="text" name="callsign" id="callsign" value="<?php echo esc_attr($callsign); ?>" class="regular-text" placeholder="e.g., W9MDM">
                        <p class="description"><?php esc_html_e('Enter a callsign to check member status.', 'events-made-easy') . ' ' . esc_html__('Callsigns are case-insensitive.', 'events-made-easy'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="eme_check_submit" class="button-primary" value="<?php esc_attr_e('Check Member', 'events-made-easy'); ?>">
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Register the shortcode for the admin member check-in page.
 */
add_shortcode('eme_member_checkin_admin', 'eme_arc_member_check_page');

/**
 * Add the admin menu for member check-in under ARC Management.
 */
add_action('admin_menu', 'eme_arc_member_check_admin_menu');
function eme_arc_member_check_admin_menu() {
    add_submenu_page(
        'eme-arc-manage',
        'Member Check-In',
        'Member Check-In',
        'manage_options',
        'eme-arc-member-checkin',
        'eme_arc_member_check_page'
    );
}