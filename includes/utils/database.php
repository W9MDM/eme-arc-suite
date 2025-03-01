<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_create_email_tracking_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'eme_email_tracking';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        email_to VARCHAR(255) NOT NULL,
        discount_id BIGINT(20) UNSIGNED NOT NULL,
        discount_code VARCHAR(50) NOT NULL,
        sent_datetime DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY discount_id (discount_id),
        KEY sent_datetime (sent_datetime)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function eme_arc_suite_check_deps() {
    $missing = [];
    if (!function_exists('eme_get_person_by_wp_id') || !function_exists('eme_db_insert_person') || !function_exists('eme_db_update_person') || !function_exists('eme_get_person_answers') || !function_exists('eme_get_events')) {
        $missing[] = 'Events Made Easy';
    }
    $core_plugin = 'eme-arc-suite-core/eme-arc-core.php';
    if (!is_plugin_active($core_plugin)) {
        $missing[] = 'EME ARC Suite - Core';
    }
    if (!empty($missing)) {
        deactivate_plugins(plugin_basename(EME_ARC_SUITE_DIR . 'eme-arc-suite.php'));
        wp_die(
            __('EME ARC Suite requires the following plugins to be active: ', 'eme-arc-suite') . implode(', ', $missing) . 
            __('. Please install and activate them.', 'eme-arc-suite')
        );
    }
}

add_action('plugins_loaded', 'eme_arc_suite_check_deps');