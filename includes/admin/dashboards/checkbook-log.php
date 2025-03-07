<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_suite_display_checkbook_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'eme_arc_transactions';

    // Handle manual transaction submission
    if (isset($_POST['add_transaction']) && check_admin_referer('add_transaction_nonce', 'nonce')) {
        $date = sanitize_text_field($_POST['transaction_date']);
        $member_input = sanitize_text_field($_POST['member_id_callsign']);
        $member_id = is_numeric($member_input) ? intval($member_input) : eme_check_callsign($member_input);
        $amount = floatval($_POST['amount']);
        $description = sanitize_text_field($_POST['description']);
        $vendor_payee = sanitize_text_field($_POST['vendor_payee']);

        $result = $wpdb->insert(
            $table_name,
            [
                'transaction_date' => $date,
                'member_id' => $member_id,
                'amount' => $amount,
                'description' => $description,
                'vendor_payee' => $vendor_payee
            ],
            ['%s', '%d', '%f', '%s', '%s']
        );

        if ($result !== false) {
            echo '<div class="updated"><p>Transaction added successfully.</p></div>';
        } else {
            echo '<div class="error"><p>Error adding transaction: ' . esc_html($wpdb->last_error) . '</p></div>';
        }
    }

    // Handle CSV import
    if (isset($_POST['import_csv']) && check_admin_referer('import_csv_nonce', 'nonce')) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($file, 'r')) !== false) {
                $header = fgetcsv($handle); // Skip header row
                $expected_headers = ['transaction_date', 'member_id_callsign', 'amount', 'description', 'vendor_payee'];
                if (array_diff($expected_headers, $header) === []) {
                    while (($data = fgetcsv($handle)) !== false) {
                        $date = sanitize_text_field($data[0]);
                        $member_input = sanitize_text_field($data[1]);
                        $member_id = is_numeric($member_input) ? intval($member_input) : eme_check_callsign($member_input);
                        $amount = floatval($data[2]);
                        $description = sanitize_text_field($data[3]);
                        $vendor_payee = sanitize_text_field($data[4]);

                        $wpdb->insert(
                            $table_name,
                            [
                                'transaction_date' => $date,
                                'member_id' => $member_id,
                                'amount' => $amount,
                                'description' => $description,
                                'vendor_payee' => $vendor_payee
                            ],
                            ['%s', '%d', '%f', '%s', '%s']
                        );
                    }
                    echo '<div class="updated"><p>CSV imported successfully.</p></div>';
                } else {
                    echo '<div class="error"><p>Invalid CSV format. Expected headers: ' . implode(', ', $expected_headers) . '</p></div>';
                }
                fclose($handle);
            } else {
                echo '<div class="error"><p>Failed to open CSV file.</p></div>';
            }
        } else {
            echo '<div class="error"><p>No CSV file uploaded or upload error.</p></div>';
        }
    }

    // Fetch all transactions
    $transactions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY transaction_date ASC");
    $running_balance = 0;

    ?>
    <div class="wrap">
        <h2>Checkbook Log</h2>
        <p>Track all incomes (positive amounts) and expenditures (negative amounts).</p>

        <!-- Manual Add Transaction Form -->
        <h3>Add Transaction</h3>
        <form method="post" class="checkbook-form">
            <?php wp_nonce_field('add_transaction_nonce', 'nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="transaction_date">Date</label></th>
                    <td><input type="datetime-local" id="transaction_date" name="transaction_date" required value="<?php echo esc_attr(date('Y-m-d\TH:i')); ?>"></td>
                </tr>
                <tr>
                    <th><label for="member_id_callsign">Member ID or Callsign (optional)</label></th>
                    <td><input type="text" id="member_id_callsign" name="member_id_callsign" placeholder="e.g., 2 or W9MDM"></td>
                </tr>
                <tr>
                    <th><label for="amount">Amount</label></th>
                    <td><input type="number" id="amount" name="amount" step="0.01" required placeholder="Positive for income, negative for expenditure"></td>
                </tr>
                <tr>
                    <th><label for="description">Description</label></th>
                    <td><input type="text" id="description" name="description" required placeholder="e.g., Donation, Event Cost"></td>
                </tr>
                <tr>
                    <th><label for="vendor_payee">Vendor/Payee</label></th>
                    <td><input type="text" id="vendor_payee" name="vendor_payee" placeholder="e.g., John Doe, ACME Supplies"></td>
                </tr>
            </table>
            <p><input type="submit" name="add_transaction" value="Add Transaction" class="button-primary"></p>
        </form>

        <!-- CSV Import Form -->
        <h3>Import Transactions from CSV</h3>
        <form method="post" enctype="multipart/form-data" class="checkbook-form">
            <?php wp_nonce_field('import_csv_nonce', 'nonce'); ?>
            <p>
                <label for="csv_file">Upload CSV File</label><br>
                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                <input type="submit" name="import_csv" value="Import CSV" class="button-secondary">
            </p>
            <p><small>CSV Format: transaction_date,member_id_callsign,amount,description,vendor_payee<br>Example: "2025-03-06 10:00:00","W9MDM","25.00","Donation","John Doe"</small></p>
        </form>

        <!-- Transaction Log -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Member ID/Callsign</th>
                    <th>Description</th>
                    <th>Vendor/Payee</th>
                    <th>Income</th>
                    <th>Expenditure</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction) : 
                    $amount = floatval($transaction->amount);
                    $running_balance += $amount;
                    $income = $amount > 0 ? $amount : 0;
                    $expenditure = $amount < 0 ? abs($amount) : 0;
                    $member_display = $transaction->member_id ? $transaction->member_id : 'N/A';
                ?>
                <tr>
                    <td><?php echo esc_html($transaction->transaction_date); ?></td>
                    <td><?php echo esc_html($member_display); ?></td>
                    <td><?php echo esc_html($transaction->description); ?></td>
                    <td><?php echo esc_html($transaction->vendor_payee ?: 'N/A'); ?></td>
                    <td><?php echo $income > 0 ? '$' . number_format($income, 2) : ''; ?></td>
                    <td><?php echo $expenditure > 0 ? '$' . number_format($expenditure, 2) : ''; ?></td>
                    <td>$<?php echo number_format($running_balance, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($transactions)) : ?>
        <p>No transactions recorded yet.</p>
        <?php endif; ?>
    </div>
    <?php
}
