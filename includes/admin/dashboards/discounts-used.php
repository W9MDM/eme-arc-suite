<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_suite_display_discounts_used() {
    if (!function_exists('eme_get_members')) {
        echo '<p>Error: Events Made Easy plugin is required.</p>';
        return;
    }

    $members = eme_get_members( [], 'members.discount > 0 OR members.discountids != "[]"' );
    $lost_income = 0;
    $members_with_discount = [];

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

        $discount_amount = floatval($member['discount']);
        if ($discount_amount == 0 && !empty($member['discountids'])) {
            $discount_ids = json_decode($member['discountids'], true);
            if (!empty($discount_ids)) {
                foreach ($discount_ids as $discount_id) {
                    $discount_value = $wpdb->get_var($wpdb->prepare(
                        "SELECT value FROM {$wpdb->prefix}eme_discounts WHERE id = %d",
                        $discount_id
                    ));
                    if ($discount_value) {
                        $discount_type = $wpdb->get_var($wpdb->prepare(
                            "SELECT type FROM {$wpdb->prefix}eme_discounts WHERE id = %d",
                            $discount_id
                        ));
                        if ($discount_type == 2) {
                            $discount_amount += ($price * $discount_value / 100);
                        } else {
                            $discount_amount += floatval($discount_value);
                        }
                    }
                }
            }
        }

        $member['original_price'] = $price;
        $member['discount_amount'] = $discount_amount;
        $lost_income += $discount_amount;
        $members_with_discount[] = $member;
    }

    ?>
    <div class="wrap">
        <h2>Discounts Used</h2>
        <p><strong>Income Lost to Discounts:</strong> $<?php echo number_format($lost_income, 2); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Member Name</th>
                    <th>Membership Type</th>
                    <th>Discount Applied</th>
                    <th>Original Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members_with_discount as $member) : ?>
                <tr>
                    <td><?php echo esc_html($member['lastname'] . ', ' . $member['firstname']); ?></td>
                    <td><?php echo esc_html($member['membership_name']); ?></td>
                    <td>$<?php echo number_format($member['discount_amount'], 2); ?></td>
                    <td>$<?php echo number_format($member['original_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($members_with_discount)) : ?>
        <p>No discounts have been applied yet.</p>
        <?php endif; ?>
    </div>
    <?php
}
