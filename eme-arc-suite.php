<?php
/*
 * Plugin Name: EME ARC Suite
 * Description: A comprehensive suite for amateur radio club management with membership admin, event/member check-ins, propagation forecasts, attendance cards, renewal dashboards, and callsign username enforcement.
 * Version: 1.0.0
 * Author: W9MDM
 * License: GPL2
 * Requires: Events Made Easy, EME ARC Suite - Core
 */

// Namespace import for Plugin Update Checker (must be at the top of the file)
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('EME_ARC_SUITE_DIR', plugin_dir_path(__FILE__));
define('EME_ARC_SUITE_URL', plugin_dir_url(__FILE__));

// Load utilities first
require_once EME_ARC_SUITE_DIR . 'includes/utils/database.php';
require_once EME_ARC_SUITE_DIR . 'includes/utils/callsign-utils.php';
require_once EME_ARC_SUITE_DIR . 'includes/utils/pdf-generator.php';
require_once EME_ARC_SUITE_DIR . 'includes/utils/propagation-utils.php';

// Load admin features
require_once EME_ARC_SUITE_DIR . 'includes/admin/membership-admin.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/callsign-lookup.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/member-check.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/csv-import.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/discount-dashboard.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/email-code.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/member-checkin-admin.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/membership-renewal-admin.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/attendance-cards-admin.php';
require_once EME_ARC_SUITE_DIR . 'includes/admin/propagation-admin.php';

// Load front-end features
require_once EME_ARC_SUITE_DIR . 'includes/front/event-checkin.php';
require_once EME_ARC_SUITE_DIR . 'includes/front/member-checkin.php';
require_once EME_ARC_SUITE_DIR . 'includes/front/membership-renewal.php';
require_once EME_ARC_SUITE_DIR . 'includes/front/propagation-widget.php';

// Load user-related features
require_once EME_ARC_SUITE_DIR . 'includes/user/force-callsign.php';

// Register activation hook
register_activation_hook(__FILE__, 'eme_arc_suite_activate');

function eme_arc_suite_activate() {
    eme_arc_create_email_tracking_table(); // From database.php
    eme_checkin_activate();                // From event-checkin.php
    eme_member_checkin_activate();         // From member-checkin.php
    eme_membership_create_pages();         // From membership-renewal-admin.php
    arc_attendance_cards_activate();       // From attendance-cards-admin.php
}

// Include the Plugin Update Checker library (local copy only, per README recommendation)
$checker_path = dirname(__FILE__) . '/plugin-update-checker/plugin-update-checker.php';

if (file_exists($checker_path)) {
    require_once $checker_path;
    
    // Initialize the update checker for GitHub integration (per README)
    $updateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/W9MDM/eme-arc-suite/', // GitHub repo URL, trailing slash per README
        __FILE__, // Full path to the main plugin file, per README
        'eme-arc-suite' // Unique plugin slug, per README
    );
    
    // Set the branch to check (default is 'main', per README)
    $updateChecker->setBranch('main');
    
    // Enable GitHub Releases for stable updates (per README)
    $updateChecker->getVcsApi()->enableReleaseAssets();
}