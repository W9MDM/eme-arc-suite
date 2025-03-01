<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_arc_admin_process_csv_import() {
    global $wpdb;

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        return '<div class="error"><p>' . esc_html__('Error uploading CSV file.', 'events-made-easy') . '</p></div>';
    }

    $table = sanitize_text_field($_POST['import_table'] ?? '');
    $file_path = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file_path, 'r');

    if (!$handle) {
        return '<div class="error"><p>' . esc_html__('Could not open CSV file.', 'events-made-easy') . '</p></div>';
    }

    $headers = fgetcsv($handle, 0, ',', '"');
    if (!$headers) {
        fclose($handle);
        return '<div class="error"><p>' . esc_html__('Invalid CSV format.', 'events-made-easy') . '</p></div>';
    }
    $headers = array_map('trim', $headers);
    $full_table = $wpdb->prefix . $table;

    $inserted = $updated = $errors = 0;
    $error_msg = '';

    while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
        $data = array_combine($headers, $row);
        if ($data === false) {
            $errors++;
            $error_msg .= '<br>' . esc_html__('Row skipped due to column mismatch: ', 'events-made-easy') . esc_html(implode(',', $row));
            continue; // Line 47: Skip to next iteration of while loop (no nesting, so continue 1)
        }

        $data = array_map('sanitize_text_field', $data);

        switch ($table) {
            case 'eme_people':
                if (isset($data['callsign']) && !empty($data['callsign']) && !preg_match('/^[A-Za-z]{1,2}[0-9][A-Za-z]{1,3}$/', $data['callsign'])) {
                    $errors++;
                    $error_msg .= '<br>' . esc_html__('Invalid callsign format: ', 'events-made-easy') . esc_html($data['callsign']);
                    continue; // Line 86: Skip to next iteration of while loop (within switch, so continue 1)
                }
                $person_data = [
                    'firstname' => $data['firstname'] ?? '',
                    'lastname' => $data['lastname'] ?? '',
                    'address1' => $data['address1'] ?? '',
                    'city' => $data['city'] ?? '',
                    'state_code' => $data['state_code'] ?? '',
                    'zip' => $data['zip'] ?? '',
                    'country_code' => $data['country_code'] ?? ''
                ];
                $callsign = isset($data['callsign']) ? strtoupper(trim($data['callsign'])) : '';
                $person = $callsign ? $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT p.* FROM {$wpdb->prefix}eme_people p
                         INNER JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id
                         WHERE a.type = 'person' AND a.field_id = 2 AND a.answer = %s",
                        $callsign
                    ),
                    ARRAY_A
                ) : null;
                if ($person) {
                    $person_id = $person['person_id'];
                    $result = eme_db_update_person($person_id, $person_data);
                    if ($result) {
                        $updated++;
                        error_log("Updated person ID $person_id with callsign $callsign");
                    } else {
                        $errors++;
                        $error_msg .= '<br>' . esc_html__('Failed to update person: ', 'events-made-easy') . esc_html($wpdb->last_error);
                    }
                } else {
                    $person_id = eme_db_insert_person($person_data);
                    if ($person_id) {
                        $inserted++;
                        error_log("Inserted person ID $person_id with callsign $callsign");
                    } else {
                        $errors++;
                        $error_msg .= '<br>' . esc_html__('Failed to insert person: ', 'events-made-easy') . esc_html($wpdb->last_error);
                        continue; // Line 132: Skip to next iteration of while loop (within switch, so continue 1)
                    }
                }
                if ($person_id && $callsign) {
                    $existing_answer = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}eme_answers WHERE related_id = %d AND type = 'person' AND field_id = 2",
                            $person_id
                        ),
                        ARRAY_A
                    );
                    if ($existing_answer) {
                        $result = $wpdb->update(
                            "{$wpdb->prefix}eme_answers",
                            ['answer' => $callsign],
                            ['answer_id' => $existing_answer['answer_id']],
                            ['%s'],
                            ['%d']
                        );
                        if ($result === false) {
                            $errors++;
                            $error_msg .= '<br>' . esc_html__('Failed to update callsign for person_id ', 'events-made-easy') . $person_id . ': ' . $wpdb->last_error;
                        }
                    } else {
                        $result = $wpdb->insert(
                            "{$wpdb->prefix}eme_answers",
                            [
                                'related_id' => $person_id,
                                'type' => 'person',
                                'field_id' => 2,
                                'answer' => $callsign
                            ],
                            ['%d', '%s', '%d', '%s']
                        );
                        if ($result === false) {
                            $errors++;
                            $error_msg .= '<br>' . esc_html__('Failed to insert callsign for person_id ', 'events-made-easy') . $person_id . ': ' . $wpdb->last_error;
                        }
                    }
                }
                break;

            case 'eme_members':
                if (isset($data['callsign']) && !empty($data['callsign']) && !preg_match('/^[A-Za-z]{1,2}[0-9][A-Za-z]{1,3}$/', $data['callsign'])) {
                    $errors++;
                    $error_msg .= '<br>' . esc_html__('Invalid callsign format: ', 'events-made-easy') . esc_html($data['callsign']);
                    continue; // Line 138: Skip to next iteration of while loop (within switch, so continue 1)
                }
                $data['callsign'] = strtoupper($data['callsign'] ?? '');
                if (!isset($data['member_id'])) {
                    $errors++;
                    $error_msg .= '<br>' . esc_html__('Missing member_id: ', 'events-made-easy') . esc_html(implode(',', $row));
                    continue; // Line 187: Skip to next iteration of while loop (within switch, so continue 1)
                }
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$full_table` WHERE `member_id` = %d", $data['member_id']));
                $result = $exists
                    ? $wpdb->update($full_table, $data, ['member_id' => $data['member_id']])
                    : $wpdb->insert($full_table, $data);
                if ($result !== false) {
                    $exists ? $updated++ : $inserted++;
                    if ($data['callsign']) {
                        $member = eme_get_member($data['member_id']);
                        $person_id = $member['person_id'];
                        $existing_answer = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}eme_answers WHERE related_id = %d AND type = 'person' AND field_id = 2",
                                $person_id
                            ),
                            ARRAY_A
                        );
                        if ($existing_answer) {
                            $wpdb->update(
                                "{$wpdb->prefix}eme_answers",
                                ['answer' => $data['callsign']],
                                ['answer_id' => $existing_answer['answer_id']],
                                ['%s'],
                                ['%d']
                            );
                        } else {
                            $wpdb->insert(
                                "{$wpdb->prefix}eme_answers",
                                [
                                    'related_id' => $person_id,
                                    'type' => 'person',
                                    'field_id' => 2,
                                    'answer' => $data['callsign']
                                ],
                                ['%d', '%s', '%d', '%s']
                            );
                        }
                    }
                } else {
                    $errors++;
                    $error_msg .= '<br>' . esc_html__('Database error: ', 'events-made-easy') . esc_html($wpdb->last_error);
                }
                break;

            case 'eme_formfields':
                if (!isset($data['field_id'])) {
                    $errors++;
                    $error_msg .= '<br>' . esc_html__('Missing field_id: ', 'events-made-easy') . esc_html(implode(',', $row));
                    continue; // Line 226: Skip to next iteration of while loop (within switch, so continue 1)
                }
                $formfield_data = [
                    'field_id' => (int) $data['field_id'],
                    'field_type' => $data['field_type'] ?? '',
                    'field_name' => $data['field_name'] ?? '',
                    'field_values' => $data['field_values'] ?? '',
                    'admin_values' => $data['admin_values'] ?? '',
                    'field_tags' => $data['field_tags'] ?? '',
                    'admin_tags' => $data['admin_tags'] ?? '',
                    'field_attributes' => $data['field_attributes'] ?? '',
                    'field_purpose' => $data['field_purpose'] ?? '',
                    'field_condition' => $data['field_condition'] ?? '',
                    'field_required' => (int) ($data['field_required'] ?? 0),
                    'export' => (int) ($data['export'] ?? 0),
                    'extra_charge' => (int) ($data['extra_charge'] ?? 0),
                    'searchable' => (int) ($data['searchable'] ?? 0),
                    'field_info' => $data['field_info'] ?? '',
                    'field_order' => (int) ($data['field_order'] ?? 0),
                ];
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$full_table` WHERE `field_id` = %d", $formfield_data['field_id']));
                $result = $exists
                    ? $wpdb->update($full_table, $formfield_data, ['field_id' => $formfield_data['field_id']])
                    : $wpdb->insert($full_table, $formfield_data);
                if ($result !== false) {
                    $exists ? $updated++ : $inserted++;
                } else {
                    $errors++;
                    $error_msg .= '<br>' . esc_html__('Database error for field_id ', 'events-made-easy') . esc_html($formfield_data['field_id']) . ': ' . esc_html($wpdb->last_error);
                }
                break;

            case 'eme_memberships':
            case 'eme_countries':
            case 'eme_states':
                $id_field = $table === 'eme_memberships' ? 'membership_id' : 'id';
                if (!isset($data[$id_field])) {
                    $errors++;
                    $error_msg .= '<br>' . esc_html__('Missing ID field: ', 'events-made-easy') . esc_html(implode(',', $row));
                    continue; // Line 246: Skip to next iteration of while loop (within switch, so continue 1)
                }
                if ($table === 'eme_memberships') {
                    $data['properties'] = json_decode($data['properties'] ?? '{}', true) ? $data['properties'] : '{}';
                }
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$full_table` WHERE `$id_field` = %d", $data[$id_field]));
                $result = $exists
                    ? $wpdb->update($full_table, $data, [$id_field => $data[$id_field]])
                    : $wpdb->insert($full_table, $data);
                if ($result !== false) {
                    $exists ? $updated++ : $inserted++;
                } else {
                    $errors++;
                    $error_msg .= '<br>' . esc_html__('Database error for ID ', 'events-made-easy') . esc_html($data[$id_field]) . ': ' . esc_html($wpdb->last_error);
                }
                break;

            default:
                $errors++;
                $error_msg .= '<br>' . esc_html__('Unknown table: ', 'events-made-easy') . esc_html($table);
                continue; // Skip to next iteration of while loop (within switch, so continue 1)
        }
    }

    fclose($handle);
    return sprintf(
        '<div class="updated"><p>' . esc_html__('Import finished: %d inserted, %d updated, %d errors', 'events-made-easy') . '</p></div>',
        $inserted,
        $updated,
        $errors
    ) . $error_msg;
}