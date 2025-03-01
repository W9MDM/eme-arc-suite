<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_email_code_page() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'events-made-easy'));
    }

    $message = '';
    $email_content = '';
    $selected_discount = null;
    $email_to = '';
    $discounts_table = $wpdb->prefix . 'eme_discounts';
    $tracking_table = $wpdb->prefix . 'eme_email_tracking';
    
    $current_time = new DateTime('now', new DateTimeZone('UTC'));
    
    $discounts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `$discounts_table` 
             WHERE valid_from <= %s AND valid_to >= %s 
             AND dgroup = 'Monthly Testing' 
             ORDER BY valid_to ASC",
            $current_time->format('Y-m-d H:i:s'),
            $current_time->format('Y-m-d H:i:s')
        ),
        ARRAY_A
    );

    if (isset($_POST['eme_arc_email_submit']) && check_admin_referer('eme_arc_email_nonce', 'eme_arc_nonce')) {
        $discount_id = intval($_POST['discount_id']);
        $email_to = sanitize_email($_POST['email_to'] ?? '');
        
        foreach ($discounts as $discount) {
            if ($discount['id'] == $discount_id) {
                $selected_discount = $discount;
                break;
            }
        }

        if ($selected_discount) {
            $expiration_date = new DateTime($selected_discount['valid_to'], new DateTimeZone('UTC'));
            $expiration_formatted = $expiration_date->format('F j, Y \a\t g:i A T');
            
            $email_template = "Congratulations on becoming a newly licensed amateur radio operator! This is a remarkable achievement, " .
                "and we’re thrilled to welcome you into the world of amateur radio. Your hard work and dedication have " .
                "truly paid off, and this is just the beginning of an exciting journey ahead.\n\n" .
                "To help you get started, we’re pleased to offer you an exclusive discount. Use the code " .
                "{discountcode} to enjoy a special discount when you register for a membership at PC-ARC. " .
                "Simply visit the website, sign up for a membership, and apply the code during checkout.\n\n" .
                "Please note that the discount code expires on {expiration}, so be sure to register soon to " .
                "take advantage of this offer!\n\n" .
                "Once again, congratulations on your license, and we look forward to seeing you further your " .
                "involvement in amateur radio. Should you have any questions or need assistance, don't hesitate " .
                "to reach out.\n\n" .
                "73 (Best regards),\n" .
                "Members of the Porter County Amateur Radio Club\n" .
                "Callsign: K9PC";

            $email_content = str_replace(
                ['{discountcode}', '{expiration}'],
                [$selected_discount['coupon'], $expiration_formatted],
                $email_template
            );
            
            if (!empty($email_to)) {
                if (is_email($email_to)) {
                    $subject = "Congratulations on Your New Amateur Radio License!";
                    $headers = ['Content-Type: text/plain; charset=UTF-8'];
                    $sent = wp_mail($email_to, $subject, $email_content, $headers);
                    
                    if ($sent) {
                        $wpdb->insert(
                            $tracking_table,
                            [
                                'email_to' => $email_to,
                                'discount_id' => $selected_discount['id'],
                                'discount_code' => $selected_discount['coupon'],
                                'sent_datetime' => $current_time->format('Y-m-d H:i:s')
                            ],
                            ['%s', '%d', '%s', '%s']
                        );
                        
                        $message = '<div class="updated"><p>' . esc_html__('Email sent successfully to ' . $email_to . ' and logged.', 'events-made-easy') . '</p></div>';
                        error_log("Email sent and logged to $email_to with discount code {$selected_discount['coupon']}");
                    } else {
                        $message = '<div class="error"><p>' . esc_html__('Failed to send email to ' . $email_to . '. Check your server mail configuration.', 'events-made-easy') . '</p></div>';
                        error_log("Failed to send email to $email_to");
                    }
                } else {
                    $message = '<div class="error"><p>' . esc_html__('Invalid email address provided.', 'events-made-easy') . '</p></div>';
                }
            } else {
                $message = '<div class="updated"><p>' . esc_html__('Email content generated successfully. Enter an email address to send it.', 'events-made-easy') . '</p></div>';
            }
        } else {
            $message = '<div class="error"><p>' . esc_html__('Invalid discount code selected.', 'events-made-easy') . '</p></div>';
        }
    }

    $tracking_data = $wpdb->get_results("SELECT * FROM `$tracking_table` ORDER BY sent_datetime DESC LIMIT 100", ARRAY_A);

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Email Code Generator', 'events-made-easy'); ?></h1>
        <?php echo wp_kses_post($message); ?>

        <div class="eme-arc-tabs">
            <ul class="nav-tab-wrapper">
                <li><a href="#tab-email-generator" class="nav-tab nav-tab-active"><?php esc_html_e('Email Generator', 'events-made-easy'); ?></a></li>
                <li><a href="#tab-email-tracking" class="nav-tab"><?php esc_html_e('Email Tracking', 'events-made-easy'); ?></a></li>
            </ul>

            <div id="tab-email-generator" class="tab-content">
                <h2><?php esc_html_e('Generate and Send Discount Email', 'events-made-easy'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('eme_arc_email_nonce', 'eme_arc_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="discount_id"><?php esc_html_e('Select Discount Code', 'events-made-easy'); ?></label></th>
                            <td>
                                <?php if (!empty($discounts)): ?>
                                    <select name="discount_id" id="discount_id" required>
                                        <?php foreach ($discounts as $discount): ?>
                                            <?php 
                                            $valid_to = new DateTime($discount['valid_to'], new DateTimeZone('UTC'));
                                            $display_text = sprintf(
                                                '%s (%s, expires %s)',
                                                $discount['coupon'],
                                                $discount['name'],
                                                $valid_to->format('F j, Y g:i A T')
                                            );
                                            ?>
                                            <option value="<?php echo esc_attr($discount['id']); ?>">
                                                <?php echo esc_html($display_text); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Select a currently valid discount code to include in the email.', 'events-made-easy'); ?></p>
                                <?php else: ?>
                                    <p><?php esc_html_e('No valid discount codes available for the current time.', 'events-made-easy'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="email_to"><?php esc_html_e('Recipient Email', 'events-made-easy'); ?></label></th>
                            <td>
                                <input type="email" name="email_to" id="email_to" value="<?php echo esc_attr($email_to); ?>" class="regular-text" placeholder="e.g., newham@example.com">
                                <p class="description"><?php esc_html_e('Enter an email address to send the generated email. Leave blank to just preview.', 'events-made-easy'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php if (!empty($discounts)): ?>
                        <p class="submit">
                            <input type="submit" name="eme_arc_email_submit" class="button-primary" value="<?php esc_attr_e('Generate and Send Email', 'events-made-easy'); ?>">
                        </p>
                    <?php endif; ?>
                </form>

                <?php if ($email_content): ?>
                    <h2><?php esc_html_e('Generated Email Content', 'events-made-easy'); ?></h2>
                    <div class="eme-email-preview">
                        <textarea rows="15" cols="80" readonly><?php echo esc_textarea($email_content); ?></textarea>
                        <p class="description"><?php esc_html_e('This is the email content that was generated and sent (if an email address was provided).', 'events-made-easy'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="tab-email-tracking" class="tab-content" style="display: none;">
                <h2><?php esc_html_e('Email Tracking', 'events-made-easy'); ?></h2>
                <?php if ($tracking_data): ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Email To', 'events-made-easy'); ?></th>
                                <th><?php esc_html_e('Discount Code', 'events-made-easy'); ?></th>
                                <th><?php esc_html_e('Sent Date', 'events-made-easy'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tracking_data as $track): ?>
                                <tr>
                                    <td><?php echo esc_html($track['email_to']); ?></td>
                                    <td><?php echo esc_html($track['discount_code']); ?></td>
                                    <td><?php echo esc_html($track['sent_datetime']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php esc_html_e('No email tracking data available.', 'events-made-easy'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}