<?php
if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Handle membership form submission, create WordPress user with first/last name, log them in, and redirect
 * Called via 'init' hook by the main plugin
 */
function eme_handle_membership_submission() {
    global $wpdb;

    if (!isset($_POST['eme_membership_submit']) || !check_admin_referer('eme_membership_nonce', 'eme_membership_nonce')) {
        error_log("Membership submission not triggered or nonce failed");
        return;
    }

    $email = sanitize_email($_POST['email'] ?? '');
    $callsign = sanitize_text_field(trim($_POST['callsign'] ?? ''));
    $membership_id = intval($_POST['membership_id'] ?? 0);

    error_log("Membership submission: email=$email, callsign=$callsign, membership_id=$membership_id");

    if (empty($email) || empty($callsign)) {
        set_transient('eme_membership_error', 'Email or callsign missing.', 30);
        error_log("Missing email or callsign");
        return;
    }

    if ($membership_id <= 0) {
        set_transient('eme_membership_error', 'Please select a membership option.', 30);
        error_log("Invalid membership_id: $membership_id");
        return;
    }

    $person = eme_get_person_by_callsign($wpdb, $callsign);
    if (!$person) {
        set_transient('eme_membership_error', 'Person not found in database. Please check your callsign.', 30);
        error_log("No person found for callsign: $callsign");
        return;
    }

    $person_id = $person['person_id'];
    $person_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT firstname, lastname FROM {$wpdb->prefix}eme_people WHERE person_id = %d",
            $person_id
        ),
        ARRAY_A
    );
    $firstname = $person_data['firstname'] ?? '';
    $lastname = $person_data['lastname'] ?? '';
    $sanitized_callsign = sanitize_user(strtoupper($callsign), false);

    $user = get_user_by('email', $email);
    if (!$user) {
        $random_password = wp_generate_password(12, true);
        $user_data = [
            'user_login' => $sanitized_callsign,
            'user_email' => $email,
            'user_pass' => $random_password,
            'role' => 'subscriber',
            'first_name' => $firstname,
            'last_name' => $lastname,
        ];

        $user_id = wp_insert_user($user_data);
        if (is_wp_error($user_id)) {
            $error_msg = $user_id->get_error_message();
            set_transient('eme_membership_error', "Failed to create user: $error_msg", 30);
            error_log("User creation failed for $email: $error_msg");
            return;
        }

        update_user_meta($user_id, 'first_name', $firstname);
        update_user_meta($user_id, 'last_name', $lastname);

        $wpdb->update(
            "{$wpdb->prefix}eme_people",
            ['wp_id' => $user_id],
            ['person_id' => $person_id],
            ['%d'],
            ['%d']
        );

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $sanitized_callsign, get_userdata($user_id));
        error_log("New user created and logged in: ID $user_id, $sanitized_callsign");
    } else {
        $user_id = $user->ID;
        update_user_meta($user_id, 'first_name', $firstname);
        update_user_meta($user_id, 'last_name', $lastname);

        if (!$person['wp_id']) {
            $wpdb->update(
                "{$wpdb->prefix}eme_people",
                ['wp_id' => $user_id],
                ['person_id' => $person_id],
                ['%d'],
                ['%d']
            );
        }

        if (!is_user_logged_in()) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            do_action('wp_login', $user->user_login, $user);
            error_log("Existing user logged in: $user->user_login, ID $user_id");
        }
    }

    $form_page_id = get_option('eme_membership_form_page_id');
    if (!$form_page_id || !($redirect_url = get_permalink($form_page_id))) {
        set_transient('eme_membership_error', 'Membership form page not found.', 30);
        error_log("Form page not found: form_page_id=$form_page_id");
        return;
    }

    $redirect_url = add_query_arg(
        [
            'membership_id' => $membership_id,
            'email' => urlencode($email),
            'callsign' => urlencode($callsign),
            'firstname' => urlencode($firstname),
            'lastname' => urlencode($lastname)
        ],
        $redirect_url
    );

    if (!headers_sent()) {
        wp_safe_redirect($redirect_url);
        exit;
    } else {
        $file = $line = '';
        headers_sent($file, $line);
        set_transient('eme_membership_error', "Redirect failed: headers sent in $file on line $line", 30);
        error_log("Headers already sent in $file on line $line");
    }
}
add_action('init', 'eme_handle_membership_submission');

/**
 * Register shortcodes for the front-end member check functionality
 */
function eme_register_member_check_shortcodes() {
    static $registered = false;

    if ($registered) {
        error_log('Shortcodes already registered in member-check.php, skipping at ' . date('Y-m-d H:i:s'));
        return;
    }

    error_log('Registering shortcodes for EME Member Check at ' . date('Y-m-d H:i:s'));
    add_shortcode('eme_member_frontend_check', 'eme_member_frontend_check_page');
    add_shortcode('eme_membership_form', 'eme_membership_form_page');
    $registered = true;
}

/**
 * Render the front-end member check page
 */
function eme_member_frontend_check_page() {
    global $wpdb;

    if (!function_exists('wp_nonce_field') || !isset($wpdb)) {
        error_log('Missing WordPress dependencies in eme_member_frontend_check_page');
        return '<p>' . esc_html__('Error: Required WordPress components are missing.', 'events-made-easy') . '</p>';
    }

    $email = sanitize_email($_POST['email'] ?? '');
    $callsign = sanitize_text_field(trim($_POST['callsign'] ?? ''));
    $message = get_transient('eme_membership_error') ? '<div class="eme-message-error"><p>' . esc_html(get_transient('eme_membership_error')) . '</p></div>' : '';
    delete_transient('eme_membership_error');

    $data = [
        'show_options' => false,
        'memberships' => [],
        'email' => $email,
        'callsign' => $callsign,
    ];

    if (isset($_POST['eme_frontend_submit']) && check_admin_referer('eme_frontend_nonce', 'eme_frontend_nonce')) {
        $message = eme_process_member_check($wpdb, $data);
    }

    return eme_render_member_check_form($data, $message);
}

/**
 * Process member check submission
 */
function eme_process_member_check($wpdb, &$data) {
    $email = $data['email'];
    $callsign = $data['callsign'];

    if (empty($email) || empty($callsign)) {
        return '<div class="eme-message-error"><p>' . esc_html__('Please enter both an email address and a callsign.', 'events-made-easy') . '</p></div>';
    }

    if (!is_email($email)) {
        return '<div class="eme-message-error"><p>' . esc_html__('Please enter a valid email address.', 'events-made-easy') . '</p></div>';
    }

    if (!preg_match('/^[A-Za-z0-9]{3,7}$/', $callsign)) {
        return '<div class="eme-message-error"><p>' . esc_html__('Please enter a valid callsign (e.g., W9MDM).', 'events-made-easy') . '</p></div>';
    }

    $callsign = strtoupper($callsign);
    $person = eme_get_person_by_callsign($wpdb, $callsign);
    $display_data = [];

    if ($person) {
        $person_id = $person['person_id'];
        $membership = eme_get_active_membership($wpdb, $person_id);
        $current_date = current_time('Y-m-d');

        $person_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT firstname, lastname, email, address1, city, state_code, zip, country_code 
                 FROM {$wpdb->prefix}eme_people 
                 WHERE person_id = %d",
                $person_id
            ),
            ARRAY_A
        );
        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT field_id, answer 
                 FROM {$wpdb->prefix}eme_answers 
                 WHERE related_id = %d AND type = 'person'",
                $person_id
            ),
            ARRAY_A
        );
        foreach ($answers as $answer) {
            $display_data[$answer['field_id']] = $answer['answer'];
        }
        $display_data = array_merge($person_data ?: [], $display_data);

        if ($membership && $membership['end_date'] >= $current_date) {
            return '<div class="eme-message-success"><p>' . 
                sprintf(esc_html__('You are an active member. Membership expires on %s.', 'events-made-easy'), esc_html($membership['end_date'])) . 
                '</p></div>';
        } else {
            $data['show_options'] = true;
            $message = '<div class="eme-message-error"><p>' . 
                ($membership ? 
                    sprintf(esc_html__('Your membership expired on %s.', 'events-made-easy'), esc_html($membership['end_date'])) : 
                    esc_html__('No active membership found.', 'events-made-easy')) . 
                '</p></div>';
            return $message . eme_render_hamdb_data($display_data, $callsign);
        }
    } else {
        $result = eme_add_new_person($wpdb, $email, $callsign, $data);
        if (strpos($result, 'eme-message-success') !== false) {
            $api_url = "https://api.hamdb.org/v1/{$callsign}/xml/legacy-api";
            $response = wp_remote_get($api_url, ['timeout' => 10]);
            if (!is_wp_error($response)) {
                $xml = @simplexml_load_string(wp_remote_retrieve_body($response));
                if ($xml && !isset($xml->callsign->message)) {
                    $display_data = [
                        'firstname' => (string) $xml->callsign->fname ?? '',
                        'lastname' => (string) $xml->callsign->name ?? '',
                        'email' => $email,
                        'address1' => (string) $xml->callsign->addr1 ?? '',
                        'city' => (string) $xml->callsign->addr2 ?? '',
                        'state_code' => (string) $xml->callsign->state ?? '',
                        'zip' => (string) $xml->callsign->zip ?? '',
                        'country_code' => ((string) $xml->callsign->country === 'United States') ? 'US' : (string) $xml->callsign->country ?? '',
                        2 => $callsign,
                        4 => ($xml->callsign->expires ? DateTime::createFromFormat('m/d/Y', (string) $xml->callsign->expires)->format('Y-m-d') : ''),
                        6 => isset($xml->callsign->class) ? ['T' => 'Technician', 'G' => 'General', 'E' => 'Extra'][(string) $xml->callsign->class] ?? '' : ''
                    ];
                }
            }
            return $result . eme_render_hamdb_data($display_data, $callsign);
        }
        return $result;
    }
}

/**
 * Get person by callsign
 */
function eme_get_person_by_callsign($wpdb, $callsign) {
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.person_id, p.email, p.wp_id 
             FROM {$wpdb->prefix}eme_people p
             INNER JOIN {$wpdb->prefix}eme_answers a ON p.person_id = a.related_id
             WHERE a.type = 'person' AND a.field_id = 2 AND a.answer = %s",
            $callsign
        ),
        ARRAY_A
    );
}

/**
 * Get active membership for a person
 */
function eme_get_active_membership($wpdb, $person_id) {
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT start_date, end_date 
             FROM {$wpdb->prefix}eme_members 
             WHERE person_id = %d AND status = 1",
            $person_id
        ),
        ARRAY_A
    );
}

/**
 * Add a new person via HamDB API
 */
function eme_add_new_person($wpdb, $email, $callsign, &$data) {
    $api_url = "https://api.hamdb.org/v1/{$callsign}/xml/legacy-api";
    $response = wp_remote_get($api_url, ['timeout' => 10]);

    if (is_wp_error($response)) {
        return '<div class="eme-message-error"><p>' . esc_html__('Failed to connect to HamDB API: ', 'events-made-easy') . esc_html($response->get_error_message()) . '</p></div>';
    }

    $xml = @simplexml_load_string(wp_remote_retrieve_body($response));
    if (!$xml || isset($xml->callsign->message)) {
        return '<div class="eme-message-error"><p>' . esc_html__('Invalid callsign or HamDB API error.', 'events-made-easy') . '</p></div>';
    }

    $people_data = [
        'firstname' => (string) $xml->callsign->fname ?? '',
        'lastname' => (string) $xml->callsign->name ?? '',
        'email' => $email,
        'address1' => (string) $xml->callsign->addr1 ?? '',
        'city' => (string) $xml->callsign->addr2 ?? '',
        'state_code' => (string) $xml->callsign->state ?? '',
        'zip' => (string) $xml->callsign->zip ?? '',
        'country_code' => ((string) $xml->callsign->country === 'United States') ? 'US' : (string) $xml->callsign->country ?? ''
    ];

    $answers = [2 => $callsign];
    if ($expires = (string) $xml->callsign->expires) {
        $expires_date = DateTime::createFromFormat('m/d/Y', $expires);
        $answers[4] = $expires_date ? $expires_date->format('Y-m-d') : '';
    }
    if ($class = (string) $xml->callsign->class) {
        $class_map = ['T' => 'Technician', 'G' => 'General', 'E' => 'Extra'];
        $answers[6] = $class_map[$class] ?? '';
    }

    $existing_person = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.person_id FROM {$wpdb->prefix}eme_people p
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
            error_log("Failed to update person ID $person_id: " . $wpdb->last_error);
            return '<div class="eme-message-error"><p>' . esc_html__('Failed to update your information.', 'events-made-easy') . '</p></div>';
        }
    } else {
        $person_id = eme_db_insert_person($people_data);
        if (!$person_id) {
            error_log("Failed to add person for callsign $callsign: " . $wpdb->last_error);
            return '<div class="eme-message-error"><p>' . esc_html__('Failed to add you to the database.', 'events-made-easy') . '</p></div>';
        }
    }

    if ($person_id && !empty($answers)) {
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
                $wpdb->update(
                    "{$wpdb->prefix}eme_answers",
                    ['answer' => $answer],
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
                        'field_id' => $field_id,
                        'answer' => $answer
                    ],
                    ['%d', '%s', '%d', '%s']
                );
            }
        }
    }

    $data['show_options'] = true;
    return '<div class="eme-message-success"><p>' . esc_html__('You have been added to our database.', 'events-made-easy') . '</p></div>';
}

/**
 * Render HamDB data in a detailed format
 */
function eme_render_hamdb_data($data, $callsign) {
    $fields = [
        'firstname' => 'First Name',
        'lastname' => 'Last Name',
        'email' => 'Email',
        'address1' => 'Address',
        'city' => 'City',
        'state_code' => 'State',
        'zip' => 'Zip',
        'country_code' => 'Country',
        2 => 'Callsign',
        4 => 'License Expires',
        6 => 'License Class'
    ];

    $output = '<div class="eme-message-success"><p>' . esc_html__('Your information:', 'events-made-easy') . '</p><ul>';
    foreach ($fields as $key => $label) {
        $value = $data[$key] ?? '';
        $output .= '<li>' . esc_html__($label . ': ', 'events-made-easy') . 
            ($value ? esc_html($value) : esc_html__('Not provided', 'events-made-easy')) . '</li>';
    }
    $output .= '</ul></div>';
    return $output;
}

/**
 * Load membership options
 */
function eme_load_membership_options($wpdb) {
    $options = $wpdb->get_results(
        "SELECT membership_id, name, description, properties 
         FROM {$wpdb->prefix}eme_memberships 
         WHERE status = 1 AND type = 'fixed'",
        ARRAY_A
    );

    if ($options === false) {
        error_log("Failed to fetch memberships: " . $wpdb->last_error);
        return [];
    }

    return array_map(function ($option) {
        $properties = json_decode($option['properties'], true);
        $option['price'] = $properties['price'] ?? 'N/A';
        $option['currency'] = $properties['currency'] ?? 'USD';
        return $option;
    }, $options);
}

/**
 * Render the member check form
 */
function eme_render_member_check_form($data, $message) {
    global $wpdb;

    $style = '<style>
        .eme-message-error { color: red; padding: 10px; border: 1px solid red; margin-bottom: 10px; }
        .eme-message-success { color: green; padding: 10px; border: 1px solid green; margin-bottom: 10px; }
        .eme-form { max-width: 600px; margin: 20px auto; }
        .eme-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        .eme-form input[type="email"], .eme-form input[type="text"] { width: 100%; padding: 8px; margin-bottom: 10px; }
        .eme-form input[type="submit"] { background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .eme-form input[type="submit"]:hover { background: #005177; }
        .membership-options { margin-top: 20px; }
        .membership-option { margin-bottom: 10px; }
        .eme-message-success ul { list-style-type: disc; padding-left: 20px; }
    </style>';

    ob_start();
    ?>
    <div class="eme-form">
        <form method="post" action="">
            <?php wp_nonce_field('eme_frontend_nonce', 'eme_frontend_nonce'); ?>
            <label for="email"><?php esc_html_e('Email Address', 'events-made-easy'); ?></label>
            <input type="email" name="email" id="email" value="<?php echo esc_attr($data['email']); ?>" required>
            <label for="callsign"><?php esc_html_e('Callsign', 'events-made-easy'); ?></label>
            <input type="text" name="callsign" id="callsign" value="<?php echo esc_attr($data['callsign']); ?>" required>
            <input type="submit" name="eme_frontend_submit" value="<?php esc_attr_e('Check Membership', 'events-made-easy'); ?>">
        </form>

        <?php if ($message) echo $message; ?>

        <?php if ($data['show_options']): ?>
            <?php $data['memberships'] = eme_load_membership_options($wpdb); ?>
            <?php if (!empty($data['memberships'])): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('eme_membership_nonce', 'eme_membership_nonce'); ?>
                    <input type="hidden" name="email" value="<?php echo esc_attr($data['email']); ?>">
                    <input type="hidden" name="callsign" value="<?php echo esc_attr($data['callsign']); ?>">
                    <div class="membership-options">
                        <?php foreach ($data['memberships'] as $option): ?>
                            <div class="membership-option">
                                <label>
                                    <input type="radio" name="membership_id" value="<?php echo esc_attr($option['membership_id']); ?>" required>
                                    <?php 
                                    echo esc_html($option['name']);
                                    if ($option['description']) {
                                        echo ' - ' . esc_html($option['description']);
                                    }
                                    echo ' (' . esc_html($option['price']) . ' ' . esc_html($option['currency']) . ')';
                                    ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <input type="submit" name="eme_membership_submit" value="<?php esc_attr_e('Select Membership', 'events-made-easy'); ?>">
                    </div>
                </form>
            <?php else: ?>
                <div class="eme-message-error"><p><?php esc_html_e('Error loading membership options.', 'events-made-easy'); ?></p></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return $style . ob_get_clean();
}

/**
 * Render the membership form page with autofill
 */
function eme_membership_form_page() {
    $membership_id = intval($_GET['membership_id'] ?? 0);
    $email = sanitize_email($_GET['email'] ?? '');
    $callsign = sanitize_text_field(trim($_GET['callsign'] ?? ''));
    $firstname = sanitize_text_field($_GET['firstname'] ?? '');
    $lastname = sanitize_text_field($_GET['lastname'] ?? '');

    error_log("Membership form page: membership_id=$membership_id, email=$email, callsign=$callsign, firstname=$firstname, lastname=$lastname");

    if ($membership_id <= 0) {
        return '<p>' . esc_html__('Invalid membership selection. Please try again.', 'events-made-easy') . '</p>';
    }

    $shortcode = sprintf('[eme_add_member_form id="%d"]', $membership_id);
    $output = do_shortcode($shortcode);

    if (empty($output)) {
        error_log("eme_add_member_form shortcode failed for ID $membership_id");
        return '<p>' . esc_html__('Error: Membership form could not be loaded.', 'events-made-easy') . '</p>';
    }

    if ($email || $callsign || $firstname || $lastname) {
        $script = "
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                let form = document.querySelector('form');
                if (form) {
                    let fields = {
                        'email': '" . esc_js($email) . "',
                        'callsign': '" . esc_js($callsign) . "',
                        'firstname': '" . esc_js($firstname) . "',
                        'lastname': '" . esc_js($lastname) . "'
                    };
                    for (let [key, value] of Object.entries(fields)) {
                        if (value) {
                            let input = form.querySelector(`input[name*='${key}'], input[id*='${key}']`);
                            if (input) input.value = value;
                        }
                    }
                }
            });
            </script>";
        $output .= $script;
    }

    return $output;
}

/**
 * Setup function for plugin activation
 */
function eme_member_check_setup() {
    error_log('eme_member_check_setup started at ' . date('Y-m-d H:i:s'));

    if (!get_page_by_path('member-check')) {
        $page_id = wp_insert_post([
            'post_title' => 'Member Check',
            'post_content' => '[eme_member_frontend_check]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'member-check',
        ]);
        if (is_wp_error($page_id)) {
            error_log('wp_insert_post error for member-check: ' . $page_id->get_error_message());
        } elseif ($page_id) {
            update_option('eme_member_check_page_id', $page_id);
        }
    }

    if (!get_page_by_path('membership-signup')) {
        $form_page_id = wp_insert_post([
            'post_title' => 'Membership Signup',
            'post_content' => '[eme_membership_form]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'membership-signup',
        ]);
        if ($form_page_id) {
            update_option('eme_membership_form_page_id', $form_page_id);
        }
    }
}