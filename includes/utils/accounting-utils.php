<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_suite_create_transactions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_arc_transactions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_id mediumint(9) NOT NULL,
        amount decimal(10,2) NOT NULL,
        transaction_date datetime DEFAULT CURRENT_TIMESTAMP,
        description varchar(255) DEFAULT '',
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function eme_arc_suite_log_transaction($member_id, $amount, $description) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_arc_transactions';
    $wpdb->insert(
        $table_name,
        [
            'member_id' => $member_id,
            'amount' => $amount,
            'description' => $description,
        ]
    );
}

// Hook into EME membership payment (adjust hook name based on EME)
function eme_arc_suite_on_membership_payment($member) {
    $amount = $member->price; // Adjust field name
    $description = "Membership renewal for " . $member->membership_name;
    eme_arc_suite_log_transaction($member->member_id, $amount, $description);
}
// add_action('eme_membership_paid', 'eme_arc_suite_on_membership_payment'); // Uncomment and adjust