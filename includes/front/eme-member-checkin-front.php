<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to display the member check-in form and list.
 */
function eme_member_checkin_shortcode() {
    ob_start();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eme_checkin_nonce']) && !empty($_POST['callsign'])) {
        if (!wp_verify_nonce($_POST['eme_checkin_nonce'], 'eme_member_checkin_action')) {
            echo '<p class="eme-error">Security check failed. Please try again.</p>';
        } else {
            $callsign = sanitize_text_field(strtoupper(trim($_POST['callsign'])));
            if (empty($callsign) || !preg_match('/^[A-Z]{1,2}[0-9][A-Z]{1,3}$/', $callsign)) {
                echo '<p class="eme-error">Invalid callsign format. Use 1-2 letters, 1 number, 1-3 letters (e.g., W9MDM).</p>';
            } else {
                if (eme_member_checkin_record($callsign)) {
                    echo '<p class="eme-success">Check-in recorded for callsign ' . esc_html($callsign) . '.</p>';
                } else {
                    echo '<p class="eme-error">Check-in failed or already recorded for callsign ' . esc_html($callsign) . ' today.</p>';
                }
            }
        }
    }

    // Display the form
    ?>
    <div class="eme-checkin-wrapper">
        <form method="post" class="eme-checkin-form">
            <?php wp_nonce_field('eme_member_checkin_action', 'eme_checkin_nonce'); ?>
            <label for="callsign">Callsign:</label>
            <input type="text" id="callsign" name="callsign" required placeholder="Enter your callsign (e.g., W9MDM)">
            <input type="submit" value="Check In">
        </form>
        <?php echo eme_member_checkin_get_list(); ?>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('eme_member_checkin', 'eme_member_checkin_shortcode');

/**
 * Enqueue front-end styles for the check-in form and list.
 */
function eme_member_checkin_enqueue_styles() {
    if (is_a($GLOBALS['post'], 'WP_Post') && has_shortcode($GLOBALS['post']->post_content, 'eme_member_checkin')) {
        wp_enqueue_style(
            'eme-member-checkin',
            EME_ARC_SUITE_URL . 'includes/front/eme-member-checkin.css',
            [],
            EME_ARC_SUITE_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'eme_member_checkin_enqueue_styles');

/**
 * Optional: Basic inline CSS if no external file exists.
 */
function eme_member_checkin_inline_styles() {
    if (is_a($GLOBALS['post'], 'WP_Post') && has_shortcode($GLOBALS['post']->post_content, 'eme_member_checkin')) {
        $css = "
            .eme-checkin-wrapper { max-width: 600px; margin: 20px auto; }
            .eme-checkin-form { margin-bottom: 20px; }
            .eme-checkin-form label { display: inline-block; width: 100px; font-weight: bold; }
            .eme-checkin-form input[type='text'] { padding: 5px; width: 150px; }
            .eme-checkin-form input[type='submit'] { padding: 5px 15px; background: #0073aa; color: white; border: none; cursor: pointer; }
            .eme-checkin-form input[type='submit']:hover { background: #005177; }
            .eme-success { color: #008000; font-weight: bold; }
            .eme-error { color: #ff0000; font-weight: bold; }
            .eme-checkin-table { width: 100%; border-collapse: collapse; }
            .eme-checkin-table th, .eme-checkin-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .eme-checkin-table th { background: #f5f5f5; }
        ";
        wp_add_inline_style('eme-member-checkin', $css);
    }
}
add_action('wp_enqueue_scripts', 'eme_member_checkin_inline_styles');