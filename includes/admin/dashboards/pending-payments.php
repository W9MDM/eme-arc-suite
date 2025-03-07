<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_suite_display_pending_payments() {
    if (!function_exists('eme_get_members')) {
        echo '<p>Error: Events Made Easy plugin is required.</p>';
        return;
    }

    $members = eme_get_members( [], 'members.status = 0 AND members.paid = 0' );
    $potential_income = 0;
    $members_with_price = [];

    global $wpdb;
    foreach ($members as $member) {
        $properties = $wpdb->get_var($wpdb->prepare(
            "SELECT properties FROM {$wpdb->prefix}eme_memberships WHERE membership_id = %d",
            $member['membership_id']
        ));
        $price = 0;
        if ($properties) {
            $props = json_decode($properties, true);
            $price = isset($props['price']) ? floatval($props['price']) : 0;
        }
        if ($price == 0) {
            $price = 25.00; // Adjust this default as needed
        }
        $member['price'] = $price;
        $potential_income += $price;
        $members_with_price[] = $member;
    }

    ?>
    <div class="wrap">
        <h2>Pending Payments</h2>
        <p><strong>Potential Income if Paid:</strong> $<?php echo number_format($potential_income, 2); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Member Name</th>
                    <th>Membership Type</th>
                    <th>Start Date</th>
                    <th>Amount Due</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members_with_price as $member) : ?>
                <tr>
                    <td><?php echo esc_html($member['lastname'] . ', ' . $member['firstname']); ?></td>
                    <td><?php echo esc_html($member['membership_name']); ?></td>
                    <td><?php echo esc_html($member['start_date']); ?></td>
                    <td>$<?php echo number_format($member['price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($members_with_price)) : ?>
        <p>No pending payments found.</p>
        <?php endif; ?>
    </div>
    <?php
}
