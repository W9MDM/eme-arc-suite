<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_suite_register_accounting_page() {
    add_menu_page(
        'Membership Accounting',
        'Membership Accounting',
        'manage_options',
        'eme-arc-accounting',
        'eme_arc_suite_render_accounting_overview',
        'dashicons-money-alt',
        30
    );

    add_submenu_page(
        'eme-arc-accounting',
        'Membership Renewals',
        'Renewals',
        'manage_options',
        'eme-arc-renewals',
        'eme_arc_suite_display_membership_renewals' // Already loaded
    );

    add_submenu_page(
        'eme-arc-accounting',
        'Accounting Summary',
        'Summary',
        'manage_options',
        'eme-arc-summary',
        'eme_arc_suite_display_accounting_summary'
    );

    add_submenu_page(
        'eme-arc-accounting',
        'Pending Payments',
        'Pending Payments',
        'manage_options',
        'eme-arc-pending',
        'eme_arc_suite_display_pending_payments'
    );

    add_submenu_page(
        'eme-arc-accounting',
        'Discounts Used',
        'Discounts Used',
        'manage_options',
        'eme-arc-discounts',
        'eme_arc_suite_display_discounts_used'
    );

    add_submenu_page(
        'eme-arc-accounting',
        'Checkbook Log',
        'Checkbook Log',
        'manage_options',
        'eme-arc-checkbook',
        'eme_arc_suite_display_checkbook_log'
    );
}

function eme_arc_suite_render_accounting_overview() {
    ?>
    <div class="wrap">
        <h1>Membership Accounting</h1>
        <p>Welcome to the Membership Accounting dashboard. Use the submenu items to view renewals, summaries, pending payments, discounts used, and the checkbook log.</p>
    </div>
    <?php
}