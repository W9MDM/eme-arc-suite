<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_suite_display_accounting_summary() {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'eme_payments';
    $members_table = $wpdb->prefix . 'eme_members';
    $memberships_table = $wpdb->prefix . 'eme_memberships';

    // Get filter values from the form (default to empty if not set)
    $filter_description = isset($_GET['filter_description']) ? sanitize_text_field($_GET['filter_description']) : '';
    $filter_payment_gateway = isset($_GET['filter_payment_gateway']) ? sanitize_text_field($_GET['filter_payment_gateway']) : '';
    $filter_paid = isset($_GET['filter_paid']) ? sanitize_text_field($_GET['filter_paid']) : '';

    // Check MySQL version to determine if JSON_EXTRACT is supported
    $mysql_version = $wpdb->get_var("SELECT VERSION()");
    $json_support = version_compare($mysql_version, '5.7.0', '>=');

    // Calculate total income
    if ($json_support) {
        $total_income_query = "
            SELECT SUM(CAST(JSON_EXTRACT(ms.properties, '$.price') AS DECIMAL(10,2)))
            FROM $members_table m
            INNER JOIN $memberships_table ms ON m.membership_id = ms.membership_id
            WHERE m.paid = 1
            AND (JSON_EXTRACT(ms.properties, '$.discount') = '' OR JSON_EXTRACT(ms.properties, '$.discount') IS NULL)
        ";
    } else {
        // Fallback for older MySQL: Extract price using string functions
        $total_income_query = "
            SELECT SUM(CAST(SUBSTRING(ms.properties, LOCATE('\"price\":\"', ms.properties) + 9, 
                LOCATE('\"', ms.properties, LOCATE('\"price\":\"', ms.properties) + 9) - 
                (LOCATE('\"price\":\"', ms.properties) + 9)) AS DECIMAL(10,2)))
            FROM $members_table m
            INNER JOIN $memberships_table ms ON m.membership_id = ms.membership_id
            WHERE m.paid = 1
            AND (ms.properties NOT LIKE '%\"discount\":\"%[^\"\"]%' OR ms.properties LIKE '%\"discount\":\"\"%')
        ";
    }
    $total_income = $wpdb->get_var($total_income_query);
    if ($wpdb->last_error) {
        echo '<p style="color: red;">Database Error (Total Income Query): ' . esc_html($wpdb->last_error) . '</p>';
        return;
    }
    $total_income = $total_income ? $total_income : 0;

    // Build the main query
    if ($json_support) {
        $query = "
            SELECT p.creation_date AS transaction_date, p.related_id AS member_id, 
                   CAST(JSON_EXTRACT(ms.properties, '$.price') AS DECIMAL(10,2)) AS amount, 
                   ms.name AS description, 
                   m.payment_id, m.payment_date, m.paid, m.pg, m.pg_pid,
                   JSON_EXTRACT(ms.properties, '$.discount') AS has_discount
            FROM $payments_table p
            LEFT JOIN $members_table m ON p.related_id = m.member_id
            LEFT JOIN $memberships_table ms ON m.membership_id = ms.membership_id
            WHERE 1=1
        ";
    } else {
        // Fallback for older MySQL: Extract price and discount using string functions
        $query = "
            SELECT p.creation_date AS transaction_date, p.related_id AS member_id, 
                   CAST(SUBSTRING(ms.properties, LOCATE('\"price\":\"', ms.properties) + 9, 
                       LOCATE('\"', ms.properties, LOCATE('\"price\":\"', ms.properties) + 9) - 
                       (LOCATE('\"price\":\"', ms.properties) + 9)) AS DECIMAL(10,2)) AS amount, 
                   ms.name AS description, 
                   m.payment_id, m.payment_date, m.paid, m.pg, m.pg_pid,
                   CASE 
                       WHEN ms.properties LIKE '%\"discount\":\"%[^\"\"]%' THEN 'discount_applied'
                       ELSE ''
                   END AS has_discount
            FROM $payments_table p
            LEFT JOIN $members_table m ON p.related_id = m.member_id
            LEFT JOIN $memberships_table ms ON m.membership_id = ms.membership_id
            WHERE 1=1
        ";
    }

    $conditions = [];
    $params = [];

    if ($filter_description) {
        $conditions[] = "ms.name = %s";
        $params[] = $filter_description;
    }
    if ($filter_payment_gateway && $filter_payment_gateway !== 'all') {
        $conditions[] = "m.pg = %s";
        $params[] = $filter_payment_gateway;
    }
    if ($filter_paid && $filter_paid !== 'all') {
        $conditions[] = "m.paid = %d";
        $params[] = ($filter_paid === 'Yes' ? 1 : 0);
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY p.creation_date DESC";

    // Conditionally use prepare() only if there are parameters
    if (!empty($params)) {
        $transactions = $wpdb->get_results($wpdb->prepare($query, $params));
    } else {
        $transactions = $wpdb->get_results($query);
    }

    // Check for database errors
    if ($wpdb->last_error) {
        echo '<p style="color: red;">Database Error (Main Query): ' . esc_html($wpdb->last_error) . '</p>';
        return;
    }

    // Debug: Check if any rows are returned
    if (empty($transactions)) {
        echo '<p style="color: red;">No transactions found matching the criteria. Check the tables for data.</p>';
    }

    // Get unique descriptions for the dropdown
    $descriptions = $wpdb->get_col("SELECT DISTINCT name FROM $memberships_table WHERE name IS NOT NULL");
    if ($wpdb->last_error) {
        echo '<p style="color: red;">Database Error (Descriptions Query): ' . esc_html($wpdb->last_error) . '</p>';
        return;
    }

    ?>
    <h2>Accounting Summary</h2>
    <p><strong>Total Income (excluding discounted memberships):</strong> $<?php echo number_format($total_income, 2); ?></p>

    <h3>Recent Transactions</h3>
    <form method="get" style="margin-bottom: 10px;">
        <input type="hidden" name="page" value="eme-arc-summary">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Member ID</th>
                    <th>Amount</th>
                    <th>
                        Description
                        <select name="filter_description" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php foreach ($descriptions as $desc) : ?>
                                <option value="<?php echo esc_attr($desc); ?>" <?php echo ($filter_description === $desc) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($desc); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </th>
                    <th>Payment ID</th>
                    <th>Payment Date</th>
                    <th>
                        Paid
                        <select name="filter_paid" onchange="this.form.submit()">
                            <option value="all">All</option>
                            <option value="Yes" <?php echo ($filter_paid === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                            <option value="No" <?php echo ($filter_paid === 'No') ? 'selected' : ''; ?>>No</option>
                        </select>
                    </th>
                    <th>
                        Payment Gateway
                        <select name="filter_payment_gateway" onchange="this.form.submit()">
                            <option value="all">All</option>
                            <option value="stripe" <?php echo ($filter_payment_gateway === 'stripe') ? 'selected' : ''; ?>>Stripe</option>
                            <option value="" <?php echo ($filter_payment_gateway === '') ? 'selected' : ''; ?>>Offline</option>
                        </select>
                    </th>
                    <th>Payment Gateway PID</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                try {
                    foreach ($transactions as $transaction) : ?>
                    <tr>
                        <td><?php echo esc_html($transaction->transaction_date); ?></td>
                        <td><?php echo esc_html($transaction->member_id); ?></td>
                        <td>
                            $<?php echo number_format($transaction->amount ? $transaction->amount : 0, 2); ?>
                            <?php if ($transaction->has_discount && $transaction->has_discount !== '""') : ?>
                                <span style="color: orange;">(Discount may apply)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($transaction->description ? $transaction->description : 'N/A'); ?></td>
                        <td><?php echo esc_html($transaction->payment_id ? $transaction->payment_id : 'N/A'); ?></td>
                        <td><?php echo esc_html($transaction->payment_date ? $transaction->payment_date : 'N/A'); ?></td>
                        <td><?php echo $transaction->paid ? 'Yes' : 'No'; ?></td>
                        <td><?php echo esc_html($transaction->pg ? $transaction->pg : 'offline'); ?></td>
                        <td><?php echo esc_html($transaction->pg_pid ? $transaction->pg_pid : 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; 
                } catch (Exception $e) {
                    echo '<p style="color: red;">Error rendering transactions: ' . esc_html($e->getMessage()) . '</p>';
                }
                ?>
            </tbody>
        </table>
    </form>
    <?php
}