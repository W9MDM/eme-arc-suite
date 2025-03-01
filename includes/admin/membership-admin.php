<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_admin_add_menu() {
    add_menu_page(
        __('EME ARC Membership Management', 'events-made-easy'),
        __('EME ARC Management', 'events-made-easy'),
        'manage_options',
        'eme-arc-manage',
        'eme_arc_admin_management_page',
        'dashicons-admin-users',
        80
    );

    add_submenu_page(
        'eme-arc-manage',
        __('Callsign Lookup', 'events-made-easy'),
        __('Callsign Lookup', 'events-made-easy'),
        'manage_options',
        'eme-arc-callsign-lookup',
        'eme_arc_callsign_lookup_page'
    );

    add_submenu_page(
        'eme-arc-manage',
        __('Member Check', 'events-made-easy'),
        __('Member Check', 'events-made-easy'),
        'manage_options',
        'eme-arc-member-check',
        'eme_arc_member_check_page'
    );

    add_submenu_page(
        'eme-arc-manage',
        __('Discount Codes Dashboard', 'events-made-easy'),
        __('Discount Codes', 'events-made-easy'),
        'manage_options',
        'eme-arc-discount-dashboard',
        'eme_arc_discount_dashboard_page'
    );

    add_submenu_page(
        'eme-arc-manage',
        __('Email Code Generator', 'events-made-easy'),
        __('Email Code', 'events-made-easy'),
        'manage_options',
        'eme-arc-email-code',
        'eme_arc_email_code_page'
    );
}

function eme_arc_admin_enqueue_assets() {
    if (isset($_GET['page']) && in_array($_GET['page'], ['eme-arc-manage', 'eme-arc-callsign-lookup', 'eme-arc-member-check', 'eme-arc-discount-dashboard', 'eme-arc-email-code'])) {
        wp_enqueue_style('eme-arc-tabs', EME_ARC_SUITE_URL . 'assets/css/eme-arc-tabs.css');
        wp_enqueue_script('eme-arc-tabs', EME_ARC_SUITE_URL . 'assets/js/eme-arc-tabs.js', [], false, true);
    }
}

function eme_arc_admin_management_page() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'events-made-easy'));
    }

    $message = '';
    if (isset($_POST['eme_arc_import']) && check_admin_referer('eme_arc_import_nonce', 'eme_arc_nonce')) {
        $message = eme_arc_admin_process_csv_import();
    }

    $tables = [
        'eme_people' => __('People (with callsign)', 'events-made-easy'),
        'eme_members' => __('Members (with callsign)', 'events-made-easy'),
        'eme_formfields' => __('Form Fields', 'events-made-easy'),
        'eme_memberships' => __('Memberships', 'events-made-easy'),
        'eme_countries' => __('Countries', 'events-made-easy'),
        'eme_states' => __('States', 'events-made-easy'),
    ];

    $per_page_options = [10, 20, 50, 100];

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('EME ARC Membership Management', 'events-made-easy'); ?></h1>
        <?php echo wp_kses_post($message); ?>

        <h2><?php esc_html_e('Import CSV Data', 'events-made-easy'); ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('eme_arc_import_nonce', 'eme_arc_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="import_table"><?php esc_html_e('Select Table', 'events-made-easy'); ?></label></th>
                    <td>
                        <select name="import_table" id="import_table" required>
                            <?php foreach ($tables as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="csv_file"><?php esc_html_e('CSV File', 'events-made-easy'); ?></label></th>
                    <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="eme_arc_import" class="button-primary" value="<?php esc_attr_e('Import CSV', 'events-made-easy'); ?>">
            </p>
        </form>

        <h2><?php esc_html_e('Current Data Summary', 'events-made-easy'); ?></h2>
        <div class="eme-arc-tabs">
            <ul class="nav-tab-wrapper">
                <?php $first = true; foreach ($tables as $table => $label): ?>
                    <li><a href="#tab-<?php echo esc_attr($table); ?>" class="nav-tab <?php echo $first ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a></li>
                    <?php $first = false; endforeach; ?>
            </ul>

            <?php $first = true; foreach ($tables as $table => $label): ?>
                <?php
                $full_table = $wpdb->prefix . $table;
                $total = $wpdb->get_var("SELECT COUNT(*) FROM `$full_table`");
                $per_page = isset($_GET["{$table}_per_page"]) && in_array((int) $_GET["{$table}_per_page"], $per_page_options) ? (int) $_GET["{$table}_per_page"] : 10;
                $current_page = isset($_GET["{$table}_page"]) && (int) $_GET["{$table}_page"] > 0 ? (int) $_GET["{$table}_page"] : 1;
                $total_pages = max(1, ceil($total / $per_page));
                $current_page = min($current_page, $total_pages);
                $offset = ($current_page - 1) * $per_page;

                $sample = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$full_table` LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);
                ?>
                <div id="tab-<?php echo esc_attr($table); ?>" class="tab-content" style="<?php echo $first ? '' : 'display: none;'; ?>">
                    <h3><?php echo esc_html($label); ?> (<?php echo esc_html($total); ?> <?php esc_html_e('entries', 'events-made-easy'); ?>)</h3>

                    <div class="tablenav top">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(esc_html__('%d items', 'events-made-easy'), $total); ?></span>
                            <span class="pagination-links">
                                <?php if ($current_page > 1): ?>
                                    <a class="first-page button" href="<?php echo esc_url(add_query_arg(["{$table}_page" => 1, "{$table}_per_page" => $per_page])); ?>">«</a>
                                    <a class="prev-page button" href="<?php echo esc_url(add_query_arg(["{$table}_page" => $current_page - 1, "{$table}_per_page" => $per_page])); ?>">‹</a>
                                <?php else: ?>
                                    <span class="tablenav-pages-navspan button disabled">«</span>
                                    <span class="tablenav-pages-navspan button disabled">‹</span>
                                <?php endif; ?>
                                <span class="paging-input">
                                    <?php printf(esc_html__('Page %d of %d', 'events-made-easy'), $current_page, $total_pages); ?>
                                </span>
                                <?php if ($current_page < $total_pages): ?>
                                    <a class="next-page button" href="<?php echo esc_url(add_query_arg(["{$table}_page" => $current_page + 1, "{$table}_per_page" => $per_page])); ?>">›</a>
                                    <a class="last-page button" href="<?php echo esc_url(add_query_arg(["{$table}_page" => $total_pages, "{$table}_per_page" => $per_page])); ?>">»</a>
                                <?php else: ?>
                                    <span class="tablenav-pages-navspan button disabled">›</span>
                                    <span class="tablenav-pages-navspan button disabled">»</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="alignright">
                            <label for="<?php echo esc_attr($table); ?>_per_page" class="screen-reader-text"><?php esc_html_e('Entries per page', 'events-made-easy'); ?></label>
                            <select name="<?php echo esc_attr($table); ?>_per_page" onchange="window.location.href=this.value;">
                                <?php foreach ($per_page_options as $option): ?>
                                    <option value="<?php echo esc_url(add_query_arg(["{$table}_per_page" => $option, "{$table}_page" => 1])); ?>" <?php selected($per_page, $option); ?>><?php echo esc_html($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if (!empty($sample)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($sample[0]) as $column): ?>
                                        <th><?php echo esc_html($column); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sample as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?php echo esc_html($value ?? ''); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php esc_html_e('No data available.', 'events-made-easy'); ?></p>
                    <?php endif; ?>

                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="pagination-links">
                                    <?php if ($current_page > 1): ?>
                                        <a class="first-page button" href="<?php echo esc_url(add_query_arg(["{$table}_page" => 1, "{$table}_per_page" => $per_page])); ?>">«</a>
                                        <a class="prev-page button" href="<?php echo esc_url(add_query_arg(["{$table}_page" => $current_page - 1, "{$table}_per_page" => $per_page])); ?>">‹</a>
                                    <?php else: ?>
                                        <span class="tablenav-pages-navspan button disabled">«</span>
                                        <span class="tablenav-pages-navspan button disabled">‹</span>
                                    <?php endif; ?>
                                    <span class="paging-input">
                                        <?php printf(esc_html__('Page %d of %d', 'events-made-easy'), $current_page, $total_pages); ?>
                                    </span>
                                    <?php if ($current_page < $total_pages): ?>
                                        <a class="next-page button" href="<?php echo esc_url(add_query_arg(["{$table}_page" => $current_page + 1, "{$table}_per_page" => $per_page])); ?>">›</a>
                                        <a class="last-page button" href="<?php echo esc_url(add_query_arg(["{$table}_page" => $total_pages, "{$table}_per_page" => $per_page])); ?>">»</a>
                                    <?php else: ?>
                                        <span class="tablenav-pages-navspan button disabled">›</span>
                                        <span class="tablenav-pages-navspan button disabled">»</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php $first = false; endforeach; ?>
        </div>
    </div>
    <?php
}

add_action('admin_menu', 'eme_arc_admin_add_menu');
add_action('admin_enqueue_scripts', 'eme_arc_admin_enqueue_assets');