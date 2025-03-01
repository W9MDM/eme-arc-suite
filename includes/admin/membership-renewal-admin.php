<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_membership_admin_menu() {
    $parent_slug = 'eme-arc-manage';
    $hook = add_submenu_page(
        $parent_slug,
        'Membership Renewals',
        'Membership Renewals',
        'manage_options',
        'eme-membership-renewal-dashboard',
        'eme_membership_admin_page'
    );
    error_log("Membership Renewals menu hook: " . $hook);
    error_log("Current user can manage_options: " . (current_user_can('manage_options') ? 'yes' : 'no'));

    if (!$hook) {
        $hook = add_menu_page(
            'Membership Renewals',
            'Membership Renewals',
            'manage_options',
            'eme-membership-renewal-dashboard',
            'eme_membership_admin_page',
            'dashicons-groups',
            80
        );
        error_log("Fallback top-level menu hook: " . $hook);
    }
}

function eme_membership_admin_page() {
    if (!current_user_can('manage_options')) {
        error_log("User denied access to Membership Renewals page - lacks manage_options");
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    error_log("User accessing Membership Renewals page - has permission");

    global $wpdb;

    $table_check = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}eme_members'");
    if (!$table_check) {
        echo '<div class="wrap"><h1>Membership Renewals</h1><p>Error: Members table (eme_members) not found in the database.</p></div>';
        return;
    }

    $people = $wpdb->get_results(
        "SELECT p.person_id, p.lastname, p.firstname, m.status, m.membership_id, a.answer as callsign, mem.name as membership_name
         FROM {$wpdb->prefix}eme_people p
         JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id AND a.type = 'person' AND a.field_id = 2
         JOIN {$wpdb->prefix}eme_members m ON p.person_id = m.person_id
         LEFT JOIN {$wpdb->prefix}eme_memberships mem ON m.membership_id = mem.membership_id
         WHERE m.status = 100
         ORDER BY m.status ASC"
    );
    error_log("Membership Renewals: Fetched " . count($people) . " expired members (status = 100)");

    echo '<div class="wrap">';
    echo '<h1>Expired Memberships</h1>';
    if (empty($people)) {
        echo '<p>No expired memberships found (status = 100).</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Callsign</th><th>Name</th><th>Status</th><th>Membership Type</th><th>Membership ID</th></tr></thead>';
        echo '<tbody>';
        foreach ($people as $person) {
            echo '<tr>';
            echo '<td>' . esc_html($person->callsign) . '</td>';
            echo '<td>' . esc_html($person->firstname . ' ' . $person->lastname) . '</td>';
            echo '<td>Expired [Raw: ' . esc_html($person->status) . ']</td>';
            echo '<td>' . esc_html($person->membership_name ? $person->membership_name : 'Unknown') . '</td>';
            echo '<td>' . esc_html($person->membership_id) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

function eme_membership_admin_init() {
    register_setting('eme_membership_settings', 'eme_membership_payment_url');
    add_settings_section('eme_membership_main', 'Membership Settings', null, 'eme_membership');
    add_settings_field('payment_url', 'Renewal Payment URL', 'eme_membership_payment_url_callback', 'eme_membership', 'eme_membership_main');
}

function eme_membership_payment_url_callback() {
    $value = get_option('eme_membership_payment_url', '');
    echo '<input type="url" name="eme_membership_payment_url" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">Enter the URL where members can renew their membership (e.g., payment page). Note: This is overridden for expired members by specific renewal pages.</p>';
}

function eme_membership_settings_menu() {
    $parent_slug = 'eme-arc-manage';
    $hook = add_submenu_page(
        $parent_slug,
        'Membership Settings',
        'Membership Settings',
        'manage_options',
        'eme_membership',
        'eme_membership_settings_page'
    );
    error_log("Membership Settings menu hook: " . $hook);
    error_log("Current user can manage_options for settings: " . (current_user_can('manage_options') ? 'yes' : 'no'));
}

function eme_membership_settings_page() {
    if (!current_user_can('manage_options')) {
        error_log("User denied access to Membership Settings page - lacks manage_options");
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    error_log("User accessing Membership Settings page - has permission");
    ?>
    <div class="wrap">
        <h1>Membership Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('eme_membership_settings'); ?>
            <?php do_settings_sections('eme_membership'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function eme_membership_create_pages() {
    error_log("eme_membership_create_pages function triggered on activation");

    if (!current_user_can('publish_pages')) {
        error_log("User lacks publish_pages capability, cannot create pages");
        return;
    }

    $pages = [
        [
            'title' => 'Youth Membership',
            'slug' => 'youth-membership',
            'content' => '[eme_add_member_form id=2]'
        ],
        [
            'title' => 'Regular Adult Membership',
            'slug' => 'regular-adult-membership',
            'content' => '[eme_add_member_form id=3]'
        ],
        [
            'title' => 'Regular Family Membership',
            'slug' => 'regular-family-membership',
            'content' => '[eme_add_member_form id=4]'
        ],
        [
            'title' => 'Regular Senior Membership',
            'slug' => 'regular-senior-membership',
            'content' => '[eme_add_member_form id=5]'
        ]
    ];

    foreach ($pages as $page) {
        $page_exists = get_page_by_path($page['slug'], OBJECT, 'page');
        if (!$page_exists) {
            $page_args = [
                'post_title' => $page['title'],
                'post_name' => $page['slug'],
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id() ?: 1,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ];
            error_log("Attempting to create page: {$page['title']} with args: " . print_r($page_args, true));
            $page_id = wp_insert_post($page_args, true);
            if (is_wp_error($page_id)) {
                error_log("Failed to create page: {$page['title']} - Error: " . $page_id->get_error_message());
            } elseif ($page_id) {
                error_log("Successfully created page: {$page['title']} (ID: $page_id)");
            } else {
                error_log("Unexpected failure creating page: {$page['title']}");
            }
        } else {
            error_log("Page already exists: {$page['title']} (ID: {$page_exists->ID}, Status: {$page_exists->post_status})");
            if ($page_exists->post_status !== 'publish') {
                wp_update_post(['ID' => $page_exists->ID, 'post_status' => 'publish']);
                error_log("Updated page status to publish: {$page['title']} (ID: {$page_exists->ID})");
            }
        }
    }
}

function eme_membership_manual_create_pages() {
    if (isset($_GET['eme_create_pages']) && current_user_can('manage_options')) {
        eme_membership_create_pages();
        wp_die('Page creation triggered manually. Check debug.log for details.');
    }
}

add_action('admin_menu', 'eme_membership_admin_menu');
add_action('admin_init', 'eme_membership_admin_init');
add_action('admin_menu', 'eme_membership_settings_menu');
add_action('admin_init', 'eme_membership_manual_create_pages');