<?php
if (!defined('ABSPATH')) {
    exit;
}

function arc_attendance_cards_activate() {
    global $wpdb;
    $people_table = $wpdb->prefix . 'eme_people';
    $answers_table = $wpdb->prefix . 'eme_answers';
    if ($wpdb->get_var("SHOW TABLES LIKE '$people_table'") != $people_table || $wpdb->get_var("SHOW TABLES LIKE '$answers_table'") != $answers_table) {
        deactivate_plugins(plugin_basename(EME_ARC_SUITE_DIR . 'eme-arc-suite.php'));
        wp_die('This plugin requires the Events Made Easy plugin with wp_eme_people and wp_eme_answers tables.');
    }
    if (!file_exists(WP_PLUGIN_DIR . '/events-made-easy/dompdf/vendor/autoload.php')) {
        deactivate_plugins(plugin_basename(EME_ARC_SUITE_DIR . 'eme-arc-suite.php'));
        wp_die('This plugin requires DomPDF from Events Made Easy.');
    }
    if (!file_exists(WP_PLUGIN_DIR . '/events-made-easy/class-qrcode.php')) {
        deactivate_plugins(plugin_basename(EME_ARC_SUITE_DIR . 'eme-arc-suite.php'));
        wp_die('This plugin requires class-qrcode.php from Events Made Easy.');
    }
}

class ARC_Attendance_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_arc_generate_pdf', [$this, 'handle_pdf_generation']);
    }

    public function add_admin_menu() {
        $hook = add_submenu_page(
            'eme-arc-manage',
            'ARC Attendance Cards',
            'Attendance Cards',
            'manage_options',
            'arc-attendance-cards',
            [$this, 'render_admin_page']
        );
        
        if (!$hook) {
            error_log('ARC Attendance Cards: Failed to register submenu under eme-arc-manage.');
        } else {
            error_log('ARC Attendance Cards: Submenu registered successfully under eme-arc-manage.');
        }
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php if (isset($_GET['status']) && $_GET['status'] === 'no_people'): ?>
                <div class="notice notice-warning"><p>No matching people found.</p></div>
            <?php elseif (isset($_GET['status']) && $_GET['status'] === 'email_sent'): ?>
                <div class="notice notice-success"><p>Emails sent successfully!</p></div>
            <?php elseif (isset($_GET['status']) && $_GET['status'] === 'email_failed'): ?>
                <div class="notice notice-error"><p>Failed to send some emails.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="arc_generate_pdf">
                <?php wp_nonce_field('arc_attendance_generate_cards', 'arc_attendance_nonce'); ?>
                <label for="filter_name">Filter by Name:</label>
                <input type="text" name="filter_name" id="filter_name" />
                <label for="filter_callsign">Filter by Callsign:</label>
                <input type="text" name="filter_callsign" id="filter_callsign" />
                <input type="submit" name="generate_cards" value="Generate Cards" class="button button-primary" />
                <input type="submit" name="email_cards" value="Email Cards" class="button button-secondary" />
            </form>
        </div>
        <?php
    }

    public function handle_pdf_generation() {
        if (!current_user_can('manage_options') || !check_admin_referer('arc_attendance_generate_cards', 'arc_attendance_nonce')) {
            wp_die('Unauthorized access.');
        }

        $filter_name = sanitize_text_field($_POST['filter_name'] ?? '');
        $filter_callsign = sanitize_text_field($_POST['filter_callsign'] ?? '');
        $is_email_request = isset($_POST['email_cards']);

        global $wpdb;
        $people_table = $wpdb->prefix . 'eme_people';
        $answers_table = $wpdb->prefix . 'eme_answers';
        $query = "
            SELECT DISTINCT p.person_id, p.firstname, p.lastname, p.email, a.answer AS callsign
            FROM $people_table p
            LEFT JOIN $answers_table a ON p.person_id = a.related_id AND a.field_id = 2
            WHERE 1=1";
        if (!empty($filter_name)) {
            $query .= " AND (p.lastname LIKE '%" . $wpdb->esc_like($filter_name) . "%' OR p.firstname LIKE '%" . $wpdb->esc_like($filter_name) . "%')";
        }
        if (!empty($filter_callsign)) {
            $query .= " AND a.answer LIKE '%" . $wpdb->esc_like($filter_callsign) . "%'";
        }
        $people = $wpdb->get_results($query);

        error_log('Query: ' . $query);
        error_log('People count: ' . count($people));
        foreach ($people as $index => $person) {
            error_log("Person $index: " . print_r($person, true));
        }

        if ($people) {
            $pdf_generator = new ARC_Attendance_PDF();
            if ($is_email_request) {
                $this->email_pdf($people, $pdf_generator);
            } else {
                $pdf_generator->generate_pdf($people);
            }
        } else {
            wp_redirect(add_query_arg('status', 'no_people', admin_url('admin.php?page=arc-attendance-cards')));
            exit;
        }
    }

    private function email_pdf($people, $pdf_generator) {
        $temp_dir = sys_get_temp_dir();
        $success = true;

        foreach ($people as $person) {
            $email = sanitize_email($person->email);
            if (empty($email) || !is_email($email)) {
                error_log("No valid email for {$person->firstname} {$person->lastname} ({$person->callsign})");
                $success = false;
                continue;
            }

            $single_person = array($person);
            $pdf_file = $temp_dir . '/arc_attendance_card_' . $person->person_id . '.pdf';
            $pdf_generator->generate_pdf($single_person, $pdf_file);

            $subject = 'Your ARC Attendance Card';
            $message = "Hello {$person->firstname},\n\nAttached is your attendance card for the Porter County Amateur Radio Club.\n\n73,\nARC Team";
            $headers = array('Content-Type: text/plain; charset=UTF-8');
            $attachments = array($pdf_file);

            $sent = wp_mail($email, $subject, $message, $headers, $attachments);
            if ($sent) {
                error_log("Email sent to $email for {$person->callsign}");
            } else {
                error_log("Failed to send email to $email for {$person->callsign}");
                $success = false;
            }

            if (file_exists($pdf_file)) {
                unlink($pdf_file);
            }
        }

        wp_redirect(add_query_arg('status', $success ? 'email_sent' : 'email_failed', admin_url('admin.php?page=arc-attendance-cards')));
        exit;
    }
}

new ARC_Attendance_Admin();