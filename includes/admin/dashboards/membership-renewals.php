<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_suite_display_membership_renewals() {
    if (!function_exists('eme_get_members')) {
        echo '<p>Error: Events Made Easy plugin is required.</p>';
        return;
    }

    $members = eme_get_members( [], 'members.status IN (1, 2, 100)' );
    $current_date = new DateTime('now', new DateTimeZone('UTC'));

    ?>
    <h2>Membership Renewals</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Member Name</th>
                <th>Membership Type</th>
                <th>Renewal Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $member) : 
                $end_date_str = $member['end_date'] ?? '0000-00-00';
                if ($end_date_str === '0000-00-00' || empty($end_date_str)) {
                    $renewal_date = null;
                    $status = ($member['status'] == 1) ? 'Active (Forever)' : 'Pending';
                } else {
                    try {
                        $renewal_date = new DateTime($end_date_str, new DateTimeZone('UTC'));
                        $status = ($renewal_date < $current_date) ? 'Overdue' : 
                                  ($member['status'] == 1 ? 'Active' : 'Pending');
                    } catch (Exception $e) {
                        $renewal_date = null;
                        $status = 'Invalid Date';
                    }
                }
            ?>
            <tr>
                <td><?php echo esc_html($member['lastname'] . ', ' . $member['firstname']); ?></td>
                <td><?php echo esc_html($member['membership_name']); ?></td>
                <td><?php echo $renewal_date ? esc_html($renewal_date->format('Y-m-d')) : 'N/A'; ?></td>
                <td><?php echo esc_html($status); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
