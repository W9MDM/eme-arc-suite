<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_member_checkin_admin_menu() {
    add_submenu_page(
        'eme-arc-manage',
        'Member Check-In',
        'Check-In',
        'manage_options',
        'eme-member-checkin-admin',
        'eme_member_checkin_admin_page'
    );
    add_submenu_page(
        'eme-arc-manage',
        'Member Check-In Report',
        'Check-In Report',
        'manage_options',
        'eme-member-checkin-report',
        'eme_member_checkin_report_page'
    );
}

function eme_member_checkin_admin_page() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'eme-member-checkin'));
    }

    $message = '';
    if (isset($_POST['eme_admin_checkin_submit']) && check_admin_referer('eme_admin_checkin_nonce', 'eme_admin_checkin_nonce')) {
        $callsign = sanitize_text_field(strtoupper($_POST['callsign'] ?? ''));
        
        if (empty($callsign)) {
            $message = '<div class="error"><p>' . esc_html__('Please enter a callsign.', 'eme-member-checkin') . '</p></div>';
        } elseif (!preg_match('/^[A-Za-z0-9]{3,7}$/', $callsign)) {
            $message = '<div class="error"><p>' . esc_html__('Invalid callsign format. Must be 3-7 alphanumeric characters.', 'eme-member-checkin') . '</p></div>';
        } else {
            $table_name = $wpdb->prefix . 'eme_member_checkins';
            $person = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT p.person_id
                     FROM {$wpdb->prefix}eme_people p
                     JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id 
                     AND a.type = 'person' 
                     AND a.field_id = 2
                     WHERE a.answer = %s",
                    $callsign
                )
            );

            if (!$person) {
                $api_url = "https://api.hamdb.org/v1/{$callsign}/xml/legacy-api";
                $response = wp_remote_get($api_url, ['timeout' => 10]);
                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                    $message = '<div class="error"><p>' . esc_html__('Invalid callsign or API error. Please verify your callsign.', 'eme-member-checkin') . '</p></div>';
                } else {
                    $xml_body = wp_remote_retrieve_body($response);
                    $xml = @simplexml_load_string($xml_body);

                    if (!$xml || !isset($xml->callsign->call) || empty($xml->callsign->call)) {
                        $message = '<div class="error"><p>' . esc_html__('Callsign not found in HamDB database.', 'eme-member-checkin') . '</p></div>';
                    } else {
                        $api_callsign = (string) $xml->callsign->call ?? '';
                        $firstname = (string) $xml->callsign->fname ?? '';
                        $lastname = (string) $xml->callsign->name ?? '';

                        $invalid_values = ['not_found', 'n/a', '', null];
                        if (in_array(strtolower($api_callsign), $invalid_values) ||
                            in_array(strtolower($firstname), $invalid_values) ||
                            in_array(strtolower($lastname), $invalid_values)) {
                            $missing_fields = [];
                            if (in_array(strtolower($api_callsign), $invalid_values)) $missing_fields[] = 'callsign';
                            if (in_array(strtolower($firstname), $invalid_values)) $missing_fields[] = 'first name';
                            if (in_array(strtolower($lastname), $invalid_values)) $missing_fields[] = 'last name';
                            $message = '<div class="error"><p>' . esc_html__('HamDB API lookup failed: missing or invalid required field(s): ' . implode(', ', $missing_fields) . '.') . '</p></div>';
                        } else {
                            $people_data = [
                                'firstname' => $firstname,
                                'lastname' => $lastname,
                                'address1' => (string) $xml->callsign->addr1 ?? '',
                                'city' => (string) $xml->callsign->addr2 ?? '',
                                'state_code' => (string) $xml->callsign->state ?? '',
                                'zip' => (string) $xml->callsign->zip ?? '',
                                'country_code' => ((string) $xml->callsign->country === 'United States') ? 'US' : (string) $xml->callsign->country ?? ''
                            ];

                            $person_id = eme_db_insert_person($people_data);
                            if ($person_id) {
                                $wpdb->insert(
                                    "{$wpdb->prefix}eme_answers",
                                    [
                                        'related_id' => $person_id,
                                        'type' => 'person',
                                        'field_id' => 2,
                                        'answer' => $api_callsign
                                    ],
                                    ['%d', '%s', '%d', '%s']
                                );
                                $person = (object) ['person_id' => $person_id];
                                error_log("New person added for callsign {$callsign}");
                            } else {
                                $message = '<div class="error"><p>' . esc_html__('Failed to create new person record.', 'eme-member-checkin') . '</p></div>';
                            }
                        }
                    }
                }
            }

            if ($person) {
                $result = $wpdb->insert(
                    $table_name,
                    [
                        'person_id' => $person->person_id,
                        'checkin_time' => current_time('mysql')
                    ],
                    ['%d', '%s']
                );
                if ($result) {
                    $message = '<div class="updated"><p>' . esc_html__('Checked in successfully!', 'eme-member-checkin') . '</p></div>';
                    echo '<script type="text/javascript">document.addEventListener("DOMContentLoaded", function() { document.getElementById("callsign").value = ""; document.getElementById("callsign").focus(); });</script>';
                } else {
                    $message = '<div class="error"><p>' . esc_html__('Check-in failed. Please try again.', 'eme-member-checkin') . '</p></div>';
                }
            }
        }
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Admin Member Check-In', 'eme-member-checkin'); ?></h1>
        <?php echo wp_kses_post($message); ?>
        <form method="post" action="">
            <?php wp_nonce_field('eme_admin_checkin_nonce', 'eme_admin_checkin_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="callsign"><?php esc_html_e('Enter Callsign', 'eme-member-checkin'); ?></label></th>
                    <td>
                        <input type="text" name="callsign" id="callsign" class="regular-text" placeholder="e.g., W9MDM" required>
                        <p class="description"><?php esc_html_e('Enter a valid amateur radio callsign to check in.', 'eme-member-checkin'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="eme_admin_checkin_submit" class="button-primary" value="<?php esc_attr_e('Check In', 'eme-member-checkin'); ?>">
            </p>
        </form>
        <h3><?php esc_html_e('Today’s Attendees', 'eme-member-checkin'); ?></h3>
        <div id="checkin-list">
            <?php 
            $list = eme_member_checkin_get_list();
            error_log("Admin page rendering list: " . substr($list, 0, 100));
            echo $list;
            ?>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('callsign').focus();
            });
        </script>
    </div>
    <?php
}

function eme_member_checkin_report_page() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'eme-member-checkin'));
    }

    $checkins_table = $wpdb->prefix . 'eme_member_checkins';

    // Updated query to include event name
    $query = "
        SELECT c.id, c.checkin_time, p.firstname, p.lastname, p.city, p.state_code, a.answer AS callsign,
               m.membership_id, m.status, m.end_date, mem.name AS membership_name, e.event_name
        FROM $checkins_table c
        JOIN {$wpdb->prefix}eme_people p ON c.person_id = p.person_id
        JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id AND a.type = 'person' AND a.field_id = 2
        LEFT JOIN {$wpdb->prefix}eme_members m ON p.person_id = m.person_id
        LEFT JOIN {$wpdb->prefix}eme_memberships mem ON m.membership_id = mem.membership_id
        LEFT JOIN {$wpdb->prefix}eme_events e ON c.event_id = e.event_id
        ORDER BY c.checkin_time DESC
    ";
    $all_checkins = $wpdb->get_results($query, ARRAY_A);

    $checkins_by_date = [];
    foreach ($all_checkins as $record) {
        $date = date('Y-m-d', strtotime($record['checkin_time']));
        if (!isset($checkins_by_date[$date])) {
            $checkins_by_date[$date] = [];
        }
        $checkins_by_date[$date][] = $record;
    }
    krsort($checkins_by_date);

    $per_page_options = [10, 20, 50, 100];

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Member Check-In Report', 'eme-member-checkin'); ?></h1>

        <div class="eme-arc-tabs">
            <ul class="nav-tab-wrapper">
                <?php $first = true; foreach (array_keys($checkins_by_date) as $date): ?>
                    <li><a href="#tab-<?php echo esc_attr($date); ?>" class="nav-tab <?php echo $first ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($date); ?></a></li>
                    <?php $first = false; endforeach; ?>
            </ul>

            <?php $first = true; foreach ($checkins_by_date as $date => $records): ?>
                <?php
                $total = count($records);
                $per_page = isset($_GET["{$date}_per_page"]) && in_array((int) $_GET["{$date}_per_page"], $per_page_options) ? (int) $_GET["{$date}_per_page"] : 10;
                $current_page = isset($_GET["{$date}_page"]) && (int) $_GET["{$date}_page"] > 0 ? (int) $_GET["{$date}_page"] : 1;
                $total_pages = max(1, ceil($total / $per_page));
                $current_page = min($current_page, $total_pages);
                $offset = ($current_page - 1) * $per_page;

                $paged_records = array_slice($records, $offset, $per_page);
                ?>
                <div id="tab-<?php echo esc_attr($date); ?>" class="tab-content" style="<?php echo $first ? '' : 'display: none;'; ?>">
                    <h3><?php echo esc_html($date); ?> (<?php echo esc_html($total); ?> <?php esc_html_e('check-ins', 'eme-member-checkin'); ?>)</h3>

                    <div class="tablenav top">
                        <!-- Pagination controls remain unchanged -->
                    </div>

                    <?php if (!empty($paged_records)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Time', 'eme-member-checkin'); ?></th>
                                    <th><?php esc_html_e('Callsign', 'eme-member-checkin'); ?></th>
                                    <th><?php esc_html_e('Name', 'eme-member-checkin'); ?></th>
                                    <th><?php esc_html_e('City', 'eme-member-checkin'); ?></th>
                                    <th><?php esc_html_e('State', 'eme-member-checkin'); ?></th>
                                    <th><?php esc_html_e('Event Name', 'eme-member-checkin'); ?></th> <!-- Added Event Name -->
                                    <th><?php esc_html_e('Membership Type', 'eme-member-checkin'); ?></th>
                                    <th><?php esc_html_e('Membership Status', 'eme-member-checkin'); ?></th>
                                    <th><?php esc_html_e('Expiration Date', 'eme-member-checkin'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paged_records as $record): ?>
                                    <tr>
                                        <td><?php echo esc_html(date('H:i:s', strtotime($record['checkin_time']))); ?></td>
                                        <td><?php echo esc_html($record['callsign'] ?? 'N/A'); ?></td>
                                        <td><?php echo esc_html($record['firstname'] . ' ' . $record['lastname'] ?? 'N/A'); ?></td>
                                        <td><?php echo esc_html($record['city'] ?? 'N/A'); ?></td>
                                        <td><?php echo esc_html($record['state_code'] ?? 'N/A'); ?></td>
                                        <td><?php echo esc_html($record['event_name'] ?? 'N/A'); ?></td> <!-- Display Event Name -->
                                        <td><?php echo esc_html($record['membership_name'] ?? 'None'); ?></td>
                                        <td><?php echo esc_html($record['status'] ? 'Active' : 'Inactive'); ?></td>
                                        <td><?php echo esc_html($record['end_date'] ? date('Y-m-d', strtotime($record['end_date'])) : 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php esc_html_e('No check-ins available for this date.', 'eme-member-checkin'); ?></p>
                    <?php endif; ?>

                    <!-- Pagination controls remain unchanged -->
                </div>
                <?php $first = false; endforeach; ?>
        </div>
    </div>
    <?php
}

function eme_member_checkin_admin_enqueue_assets($hook) {
    if (strpos($hook, 'eme-member-checkin-admin') !== false || strpos($hook, 'eme-member-checkin-report') !== false) {
        wp_enqueue_style('eme-arc-tabs', EME_ARC_SUITE_URL . 'assets/css/eme-arc-tabs.css');
        wp_enqueue_script('eme-arc-tabs', EME_ARC_SUITE_URL . 'assets/js/eme-arc-tabs.js', [], '1.3.0', true);
        wp_enqueue_script('eme-member-checkin-ajax', EME_ARC_SUITE_URL . 'assets/js/eme-member-checkin.js', ['jquery'], '1.3.0', true);
        wp_localize_script('eme-member-checkin-ajax', 'eme_member_checkin_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eme_member_checkin_nonce')
        ]);
        wp_enqueue_style('eme-member-checkin-style', EME_ARC_SUITE_URL . 'assets/css/eme-member-checkin.css', [], '1.3.0');
    }
}

add_action('admin_menu', 'eme_member_checkin_admin_menu');
add_action('admin_enqueue_scripts', 'eme_member_checkin_admin_enqueue_assets');