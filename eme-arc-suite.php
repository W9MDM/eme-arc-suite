<?php
/*
 * Plugin Name: EME ARC Suite
 * Description: A comprehensive suite for amateur radio club management with membership admin, event/member check-ins, propagation forecasts, attendance cards, renewal dashboards, accounting, and callsign username enforcement.
 * Version: 1.0.2
 * Author: W9MDM
 * License: GPL2
 * Requires: Events Made Easy, EME ARC Suite - Core
 */

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!defined('ABSPATH')) {
    exit;
}

define('EME_ARC_SUITE_DIR', plugin_dir_path(__FILE__));
define('EME_ARC_SUITE_URL', plugin_dir_url(__FILE__));
define('EME_ARC_SUITE_VERSION', '1.0.2');

// Load front-end features
require_once EME_ARC_SUITE_DIR . 'includes/front/member-check.php';

// Load utilities with existence checks
$utils = [
    'database.php',
    'callsign-utils.php',
    'pdf-generator.php',
    'propagation-utils.php',
    'accounting-utils.php',
    'checkin-utils.php', // Ensure this is included
];

foreach ($utils as $util) {
    $path = EME_ARC_SUITE_DIR . 'includes/utils/' . $util;
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("EME ARC Suite: Missing utility file - $path");
    }
}

// Load admin features with existence checks
$admin_files = [
    'membership-admin.php',
    'callsign-lookup.php',
    'member-check-admin.php',
    'member-checkin-admin.php',
    'csv-import.php',
    'discount-dashboard.php',
    'email-code.php',
    'membership-renewal-admin.php',
    'attendance-cards-admin.php',
    'propagation-admin.php',
    'accounting-page.php',
    'membership-accounting.php',
];
foreach ($admin_files as $file) {
    $path = EME_ARC_SUITE_DIR . 'includes/admin/' . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("EME ARC Suite: Missing admin file - $path");
    }
}

// Load front-end features with existence checks
$front_files = [
    'eme-member-checkin-front.php',
];
foreach ($front_files as $file) {
    $path = EME_ARC_SUITE_DIR . 'includes/front/' . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("EME ARC Suite: Missing front-end file - $path");
    }
}

// Initialization function with dependency checks
add_action('plugins_loaded', 'eme_arc_suite_init', 100);
function eme_arc_suite_init() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . sprintf(esc_html__('EME ARC Suite requires PHP 7.4 or higher. Your server is running PHP %s.', 'events-made-easy'), PHP_VERSION) . '</p></div>';
        });
        return;
    }

    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $dependencies_met = true;
    $eme_active = is_plugin_active('events-made-easy/events-manager.php');
    if (!$eme_active || !defined('EME_ARC_CORE_VERSION')) {
        $dependencies_met = false;
    }

    if (!$dependencies_met) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__('EME ARC Suite requires "Events Made Easy" and "EME ARC Suite - Core" to be installed and activated.', 'events-made-easy') . '</p></div>';
        });
        return;
    }
    load_plugin_textdomain('events-made-easy', false, dirname(plugin_basename(__FILE__)) . '/languages/'); 
    
    // Register shortcodes from member-check.php 
    if (function_exists('eme_register_member_check_shortcodes')) {  
        eme_register_member_check_shortcodes();  
    } else {  
        error_log("EME ARC Suite: eme_register_member_check_shortcodes() not found after loading member-check.php");  
    }
}

// Activation hook
register_activation_hook(__FILE__, 'eme_arc_suite_activate');
function eme_arc_suite_activate() {
    if (function_exists('eme_membership_create_pages')) eme_membership_create_pages();
    if (function_exists('eme_member_checkin_activate')) eme_member_checkin_activate();
    if (function_exists('eme_arc_suite_create_transactions_table')) eme_arc_suite_create_transactions_table();
}

// Menu registration
add_action('admin_menu', 'eme_arc_suite_register_menus', 9);
function eme_arc_suite_register_menus() {
    add_menu_page(
        'EME ARC Suite',
        'EME ARC Suite',
        'manage_options',
        'eme-arc-suite',
        'eme_arc_suite_management_page_callback',
        'dashicons-groups',
        6
    );

    add_submenu_page(
        'eme-arc-suite',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'eme-arc-suite',
        'eme_arc_suite_management_page_callback'
    );
}

function eme_arc_suite_management_page_callback() {
    echo '<div class="wrap"><h1>EME ARC Suite Dashboard</h1><p>Welcome to the EME ARC Suite management dashboard.</p></div>';
}

add_action('admin_menu', 'eme_arc_suite_register_accounting_page', 10);