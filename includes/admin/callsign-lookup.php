<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_callsign_lookup_page() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'events-made-easy'));
    }

    $message = '';
    $callsign = '';
    $api_data = [];

    if (isset($_POST['eme_arc_callsign_submit']) && check_admin_referer('eme_arc_callsign_nonce', 'eme_arc_nonce')) {
        $callsign = trim(sanitize_text_field($_POST['callsign'] ?? ''));
        
        if (empty($callsign)) {
            $message = '<div class="error"><p>' . esc_html__('Please enter a callsign.', 'events-made-easy') . '</p></div>';
            error_log("Empty callsign submitted");
        } elseif (!preg_match('/^[A-Za-z]{1,2}[0-9][A-Za-z]{1,3}$/', $callsign)) {
            $message = '<div class="error"><p>' . esc_html__('Please enter a valid callsign (e.g., W9MDM).', 'events-made-easy') . '</p></div>';
            error_log("Invalid callsign format: $callsign");
        } else {
            $callsign = strtoupper($callsign);
            $api_url = "https://api.hamdb.org/v1/{$callsign}/xml/legacy-api";

            error_log("Lookup started for callsign: $callsign");
            $response = wp_remote_get($api_url, ['timeout' => 10]);
            if (is_wp_error($response)) {
                $message = '<div class="error"><p>' . esc_html__('Failed to connect to HamDB API: ', 'events-made-easy') . esc_html($response->get_error_message()) . '</p></div>';
                error_log("API connection failed: " . $response->get_error_message());
            } else {
                $xml_body = wp_remote_retrieve_body($response);
                $xml = @simplexml_load_string($xml_body);

                if ($xml === false) {
                    $message = '<div class="error"><p>' . esc_html__('HamDB API returned invalid XML.', 'events-made-easy') . '</p><pre>' . esc_html(substr($xml_body, 0, 100)) . '...</pre></div>';
                    error_log("Invalid XML: " . $xml_body);
                } elseif (isset($xml->callsign->message)) {
                    $message = '<div class="error"><p>' . esc_html__('HamDB API Error: ', 'events-made-easy') . esc_html($xml->callsign->message) . '</p></div>';
                    error_log("API error: " . $xml->callsign->message);
                } elseif (!isset($xml->callsign->call) || empty($xml->callsign->call)) {
                    $message = '<div class="error"><p>' . esc_html__('Callsign not found in HamDB database.', 'events-made-easy') . '</p></div>';
                    error_log("Callsign not found");
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
                        $message = '<div class="error"><p>' . esc_html__('HamDB API lookup failed: missing or invalid required field(s): ', 'events-made-easy') . esc_html(implode(', ', $missing_fields)) . '.</p></div>';
                        error_log("Validation failed for callsign $callsign: missing or invalid " . implode(', ', $missing_fields));
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

                        $answers = [
                            2 => $api_callsign
                        ];
                        $expires = (string) $xml->callsign->expires ?? '';
                        if (!empty($expires)) {
                            $expires_date = DateTime::createFromFormat('m/d/Y', $expires);
                            $answers[4] = $expires_date ? $expires_date->format('Y-m-d') : '';
                        }
                        $class = (string) $xml->callsign->class ?? '';
                        $class_map = ['T' => 'Technician', 'G' => 'General', 'E' => 'Extra'];
                        if (!empty($class) && isset($class_map[$class])) {
                            $answers[6] = $class_map[$class];
                        }

                        error_log("People data prepared: " . print_r($people_data, true));
                        error_log("Answers prepared: " . print_r($answers, true));

                        $existing_person = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT p.* FROM {$wpdb->prefix}eme_people p
                                 INNER JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id
                                 WHERE a.type = 'person' AND a.field_id = 2 AND a.answer = %s",
                                $callsign
                            ),
                            ARRAY_A
                        );

                        if ($existing_person) {
                            $person_id = $existing_person['person_id'];
                            $success = eme_db_update_person($person_id, $people_data);
                            if ($success === false) {
                                $message = '<div class="error"><p>' . esc_html__('Failed to update person in wp_eme_people.', 'events-made-easy') . '</p></div>';
                                error_log("Failed to update person ID $person_id: " . $wpdb->last_error);
                            } else {
                                $message = '<div class="updated"><p>' . esc_html__('Person updated successfully.', 'events-made-easy') . '</p></div>';
                                error_log("Person updated: ID $person_id");
                            }
                        } else {
                            $person_id = eme_db_insert_person($people_data);
                            if ($person_id === false) {
                                $message = '<div class="error"><p>' . esc_html__('Failed to insert person into wp_eme_people.', 'events-made-easy') . '</p></div>';
                                error_log("Failed to insert person: " . print_r($people_data, true) . " - Error: " . $wpdb->last_error);
                            } else {
                                $message = '<div class="updated"><p>' . esc_html__('Person inserted successfully.', 'events-made-easy') . '</p></div>';
                                error_log("Person inserted: ID $person_id");
                            }
                        }

                        if ($person_id !== false && !empty($answers)) {
                            foreach ($answers as $field_id => $answer) {
                                $existing_answer = $wpdb->get_row(
                                    $wpdb->prepare(
                                        "SELECT * FROM {$wpdb->prefix}eme_answers WHERE related_id = %d AND type = 'person' AND field_id = %d",
                                        $person_id,
                                        $field_id
                                    ),
                                    ARRAY_A
                                );

                                if ($existing_answer) {
                                    $result = $wpdb->update(
                                        "{$wpdb->prefix}eme_answers",
                                        ['answer' => $answer],
                                        ['answer_id' => $existing_answer['answer_id']],
                                        ['%s'],
                                        ['%d']
                                    );
                                    error_log("Update answer for field_id $field_id, person_id $person_id: " . ($result !== false ? 'Success' : 'Failed - ' . $wpdb->last_error));
                                } else {
                                    $result = $wpdb->insert(
                                        "{$wpdb->prefix}eme_answers",
                                        [
                                            'related_id' => $person_id,
                                            'type' => 'person',
                                            'field_id' => $field_id,
                                            'answer' => $answer
                                        ],
                                        ['%d', '%s', '%d', '%s']
                                    );
                                    error_log("Insert answer for field_id $field_id, person_id $person_id: " . ($result !== false ? 'Success' : 'Failed - ' . $wpdb->last_error));
                                }
                            }
                        } else {
                            error_log("No answers inserted: person_id = " . ($person_id === false ? 'false' : $person_id) . ", answers empty = " . empty($answers));
                        }

                        $api_data = array_merge($people_data, [
                            'answer_2' => $answers[2] ?? '',
                            'answer_4' => $answers[4] ?? '',
                            'answer_6' => $answers[6] ?? ''
                        ]);
                    }
                }
            }
        }
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Callsign Lookup', 'events-made-easy'); ?></h1>
        <?php echo wp_kses_post($message); ?>

        <form method="post" action="">
            <?php wp_nonce_field('eme_arc_callsign_nonce', 'eme_arc_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="callsign"><?php esc_html_e('Enter Callsign', 'events-made-easy'); ?></label></th>
                    <td>
                        <input type="text" name="callsign" id="callsign" value="<?php echo esc_attr($callsign); ?>" class="regular-text" placeholder="e.g., W9MDM" required>
                        <p class="description"><?php esc_html_e('Enter a valid amateur radio callsign to lookup.', 'events-made-easy'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="eme_arc_callsign_submit" class="button-primary" value="<?php esc_attr_e('Lookup and Save', 'events-made-easy'); ?>">
            </p>
            <p class="description"><?php esc_html_e('This tool looks up an amateur radio callsign using the HamDB API, retrieves details like name and address, and saves or updates them in the Events Made Easy database.', 'events-made-easy'); ?></p>
        </form>

        <?php if (!empty($api_data)): ?>
            <h2><?php esc_html_e('Lookup Results', 'events-made-easy'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Field', 'events-made-easy'); ?></th>
                        <th><?php esc_html_e('Value', 'events-made-easy'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_data as $key => $value): ?>
                        <tr>
                            <td><?php echo esc_html($key); ?></td>
                            <td><?php echo esc_html($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}