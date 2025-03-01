<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_discount_dashboard_page() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'events-made-easy'));
    }

    $message = '';
    $table_name = $wpdb->prefix . 'eme_discounts';

    if (isset($_POST['eme_arc_discount_submit']) && check_admin_referer('eme_arc_discount_nonce', 'eme_arc_nonce')) {
        $discount_data = [
            'name' => sanitize_text_field("Passed Exam Discount " . $_POST['discount_month'] . " " . $_POST['discount_year']),
            'description' => sanitize_textarea_field($_POST['discount_description'] ?? ''),
            'type' => (int) ($_POST['discount_type'] ?? 2),
            'coupon' => strtoupper(sanitize_text_field($_POST['coupon'] ?? '')),
            'dgroup' => 'Monthly Testing',
            'value' => (int) ($_POST['discount_value'] ?? 0),
            'maxcount' => (int) ($_POST['maxcount'] ?? 0),
            'count' => (int) ($_POST['count'] ?? 0),
            'strcase' => isset($_POST['strcase']) ? 1 : 0,
            'use_per_seat' => isset($_POST['use_per_seat']) ? 1 : 0,
            'valid_from' => sanitize_text_field($_POST['valid_from'] ?? ''),
            'valid_to' => sanitize_text_field($_POST['valid_to'] ?? ''),
            'properties' => json_encode([
                'invite_only' => isset($_POST['invite_only']) ? 1 : 0,
                'wp_users_only' => isset($_POST['wp_users_only']) ? 1 : 0,
                'wp_role' => sanitize_text_field($_POST['wp_role'] ?? ''),
                'group_ids' => array_map('intval', $_POST['group_ids'] ?? []),
                'membership_ids' => array_map('intval', $_POST['membership_ids'] ?? [])
            ])
        ];

        $result = $wpdb->insert($table_name, $discount_data);
        if ($result) {
            $message = '<div class="updated"><p>' . esc_html__('Discount code added successfully to Monthly Testing group.', 'events-made-easy') . '</p></div>';
        } else {
            $message = '<div class="error"><p>' . esc_html__('Failed to add discount code: ', 'events-made-easy') . esc_html($wpdb->last_error) . '</p></div>';
        }
    }

    $discounts = $wpdb->get_results("SELECT * FROM `$table_name` ORDER BY valid_to DESC", ARRAY_A);

    $today = new DateTime('now', new DateTimeZone('UTC'));
    $default_month = $today->format('F');
    $default_year = $today->format('Y');

    $month_number = sprintf('%02d', array_search($default_month, array_map('ucfirst', array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'))) + 1);
    $first_day = new DateTime("$default_year-$month_number-01", new DateTimeZone('UTC'));
    $first_saturday = $first_day->modify('first saturday of this month');
    $second_saturday = clone $first_saturday;
    $second_saturday->modify('+1 week');
    $start_date = $second_saturday->setTime(8, 0, 0);

    if ($today > $start_date) {
        $first_day->modify('+1 month');
        $default_month = $first_day->format('F');
        $default_year = $first_day->format('Y');
        $month_number = sprintf('%02d', array_search($default_month, array_map('ucfirst', array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'))) + 1);
        $first_day = new DateTime("$default_year-$month_number-01", new DateTimeZone('UTC'));
        $first_saturday = $first_day->modify('first saturday of this month');
        $second_saturday = clone $first_saturday;
        $second_saturday->modify('+1 week');
        $start_date = $second_saturday->setTime(8, 0, 0);
    }

    $end_date = clone $start_date;
    $end_date->modify('+4 weeks')->setTime(0, 0, 0);

    $furthest_date = null;
    foreach ($discounts as $discount) {
        $valid_to = new DateTime($discount['valid_to'], new DateTimeZone('UTC'));
        if (!$furthest_date || $valid_to > $furthest_date) {
            $furthest_date = $valid_to;
        }
    }

    $next_start = $furthest_date ? clone $furthest_date : clone $start_date;
    if ($furthest_date) {
        $next_start->modify('first saturday of next month');
        $second_saturday = clone $next_start;
        $second_saturday->modify('+1 week');
        $next_start = $second_saturday->setTime(8, 0, 0);
    }
    $next_end = clone $next_start;
    $next_end->modify('+4 weeks')->setTime(0, 0, 0);
    $next_name = 'Passed Exam Discount ' . $next_start->format('F Y');

    $months = array_map('ucfirst', array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'));
    $years = range(date('Y'), date('Y') + 5);

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Discount Codes Dashboard', 'events-made-easy'); ?></h1>
        <?php echo wp_kses_post($message); ?>

        <h2><?php esc_html_e('Add New Discount Code', 'events-made-easy'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('eme_arc_discount_nonce', 'eme_arc_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e('Name', 'events-made-easy'); ?></label></th>
                    <td>
                        <span>Passed Exam Discount </span>
                        <select name="discount_month" id="discount_month" required>
                            <?php foreach ($months as $month): ?>
                                <option value="<?php echo esc_attr($month); ?>" <?php selected($default_month, $month); ?>><?php echo esc_html($month); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="discount_year" id="discount_year" required>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo esc_attr($year); ?>" <?php selected($default_year, $year); ?>><?php echo esc_html($year); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="discount_description"><?php esc_html_e('Description', 'events-made-easy'); ?></label></th>
                    <td><textarea name="discount_description" id="discount_description" class="regular-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="discount_type"><?php esc_html_e('Type', 'events-made-easy'); ?></label></th>
                    <td>
                        <select name="discount_type" id="discount_type">
                            <option value="2" selected><?php esc_html_e('Fixed Amount', 'events-made-easy'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="coupon"><?php esc_html_e('Coupon Code', 'events-made-easy'); ?></label></th>
                    <td><input type="text" name="coupon" id="coupon" value="<?php echo esc_attr(strtoupper(wp_generate_password(10, false))); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="dgroup"><?php esc_html_e('Discount Group', 'events-made-easy'); ?></label></th>
                    <td>
                        <input type="text" name="dgroup" id="dgroup" value="Monthly Testing" class="regular-text" readonly>
                        <p class="description"><?php esc_html_e('Automatically set to "Monthly Testing".', 'events-made-easy'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="discount_value"><?php esc_html_e('Value', 'events-made-easy'); ?></label></th>
                    <td><input type="number" name="discount_value" id="discount_value" value="100" min="0" class="small-text" required></td>
                </tr>
                <tr>
                    <th><label for="valid_from"><?php esc_html_e('Valid From', 'events-made-easy'); ?></label></th>
                    <td><input type="datetime-local" name="valid_from" id="valid_from" value="<?php echo esc_attr($start_date->format('Y-m-d\TH:i')); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="valid_to"><?php esc_html_e('Valid To', 'events-made-easy'); ?></label></th>
                    <td><input type="datetime-local" name="valid_to" id="valid_to" value="<?php echo esc_attr($end_date->format('Y-m-d\TH:i')); ?>" required></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Options', 'events-made-easy'); ?></th>
                    <td>
                        <label><input type="checkbox" name="strcase" value="1" checked> <?php esc_html_e('Case Sensitive', 'events-made-easy'); ?></label><br>
                        <label><input type="checkbox" name="use_per_seat" value="1"> <?php esc_html_e('Use Per Seat', 'events-made-easy'); ?></label><br>
                        <label><input type="checkbox" name="invite_only" value="1"> <?php esc_html_e('Invite Only', 'events-made-easy'); ?></label><br>
                        <label><input type="checkbox" name="wp_users_only" value="1"> <?php esc_html_e('WP Users Only', 'events-made-easy'); ?></label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="eme_arc_discount_submit" class="button-primary" value="<?php esc_attr_e('Add Discount Code', 'events-made-easy'); ?>">
                <button type="button" id="generate-next" class="button" data-next-name="<?php echo esc_attr($next_name); ?>" data-next-start="<?php echo esc_attr($next_start->format('Y-m-d\TH:i')); ?>" data-next-end="<?php echo esc_attr($next_end->format('Y-m-d\TH:i')); ?>" data-next-coupon="<?php echo esc_attr(strtoupper(wp_generate_password(10, false))); ?>" data-next-month="<?php echo esc_attr($next_start->format('F')); ?>" data-next-year="<?php echo esc_attr($next_start->format('Y')); ?>">
                    <?php esc_html_e('Generate Next Code', 'events-made-easy'); ?>
                </button>
            </p>
        </form>

        <h2><?php esc_html_e('Existing Discount Codes', 'events-made-easy'); ?></h2>
        <?php if (!empty($discounts)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'events-made-easy'); ?></th>
                        <th><?php esc_html_e('Name', 'events-made-easy'); ?></th>
                        <th><?php esc_html_e('Coupon', 'events-made-easy'); ?></th>
                        <th><?php esc_html_e('Value', 'events-made-easy'); ?></th>
                        <th><?php esc_html_e('Valid From', 'events-made-easy'); ?></th>
                        <th><?php esc_html_e('Valid To', 'events-made-easy'); ?></th>
                        <th><?php esc_html_e('Group', 'events-made-easy'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discounts as $discount): ?>
                        <tr>
                            <td><?php echo esc_html($discount['id']); ?></td>
                            <td><?php echo esc_html($discount['name']); ?></td>
                            <td><?php echo esc_html($discount['coupon']); ?></td>
                            <td><?php echo esc_html($discount['value']); ?></td>
                            <td><?php echo esc_html($discount['valid_from']); ?></td>
                            <td><?php echo esc_html($discount['valid_to']); ?></td>
                            <td><?php echo esc_html($discount['dgroup']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php esc_html_e('No discount codes found.', 'events-made-easy'); ?></p>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const monthSelect = document.getElementById('discount_month');
        const yearSelect = document.getElementById('discount_year');
        const validFromInput = document.getElementById('valid_from');
        const validToInput = document.getElementById('valid_to');

        function updateDates() {
            const month = monthSelect.value;
            const year = yearSelect.value;
            const monthIndex = Array.from(monthSelect.options).findIndex(option => option.value === month);
            const firstDay = new Date(`${year}-${String(monthIndex + 1).padStart(2, '0')}-01`);
            const firstSaturday = new Date(firstDay);
            firstSaturday.setDate(firstDay.getDate() + ((6 - firstDay.getDay() + 7) % 7));
            const secondSaturday = new Date(firstSaturday);
            secondSaturday.setDate(firstSaturday.getDate() + 7);
            const startDate = new Date(secondSaturday.setHours(8, 0, 0, 0));
            const endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 28);
            endDate.setHours(0, 0, 0, 0);

            validFromInput.value = startDate.toISOString().slice(0, 16);
            validToInput.value = endDate.toISOString().slice(0, 16);
        }

        monthSelect.addEventListener('change', updateDates);
        yearSelect.addEventListener('change', updateDates);

        document.getElementById('generate-next').addEventListener('click', function() {
            const button = this;
            monthSelect.value = button.getAttribute('data-next-month');
            yearSelect.value = button.getAttribute('data-next-year');
            document.getElementById('coupon').value = button.getAttribute('data-next-coupon');
            validFromInput.value = button.getAttribute('data-next-start');
            validToInput.value = button.getAttribute('data-next-end');
            document.getElementById('discount_value').value = '100';
            document.getElementById('dgroup').value = 'Monthly Testing';
            document.querySelector('input[name="strcase"]').checked = true;
            document.querySelector('input[name="use_per_seat"]').checked = false;
            document.querySelector('input[name="invite_only"]').checked = false;
            document.querySelector('input[name="wp_users_only"]').checked = false;
        });

        updateDates();
    });
    </script>
    <?php
}