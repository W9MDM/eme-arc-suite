<?php
if (!defined('ABSPATH')) {
    exit;
}

class ARC_Propagation_Admin {
    private $propagation_utils;

    public function __construct() {
        $this->propagation_utils = new ARC_Propagation_Utils();
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_arc_propagation_refresh', [$this, 'ajax_refresh']);
        add_action('wp_ajax_arc_save_settings', [$this, 'ajax_save_settings']);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('arc-propagation-css', EME_ARC_SUITE_URL . 'assets/css/arc-propagation.css');
        wp_enqueue_script('arc-propagation-js', EME_ARC_SUITE_URL . 'assets/js/arc-propagation.js', [], false, true);
        wp_localize_script('arc-propagation-js', 'arcPropagation', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('arc_propagation_refresh'),
            'settings_nonce' => wp_create_nonce('arc_save_settings'),
            'interval' => $this->propagation_utils->get_refresh_interval() * 1000,
            'maps' => $this->propagation_utils->get_map_sources()
        ]);
    }

    public function add_dashboard_widget() {
        if (current_user_can('read')) {
            wp_add_dashboard_widget(
                'arc_propagation_widget',
                'Propagation Forecast (Custom Location)',
                [$this, 'render_admin_widget']
            );
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Propagation Admin',
            'Propagation Admin',
            'manage_options',
            'arc-propagation-admin',
            [$this, 'render_portal_page'],
            'dashicons-cloud',
            81
        );
    }

    public function render_admin_widget() {
        $data = $this->propagation_utils->fetch_propagation_data();
        $conditions = $this->propagation_utils->get_band_conditions($data['sfi'], $data['k_index']);
        $location = $this->propagation_utils->get_location();
        $location_name = get_option('arc_propagation_location_name', 'Custom Location');
        ?>
        <div class="arc-propagation-dashboard" style="font-family: Arial, sans-serif;">
            <h4 style="margin: 0 0 5px;"><?php echo esc_html($location_name); ?> Propagation Dashboard</h4>
            <div class="metrics-tiles">
                <div class="tile">SFI: <?php echo esc_html($data['sfi']); ?></div>
                <div class="tile">Sunspots: <?php echo esc_html($data['sunspots']); ?></div>
                <div class="tile">A-index: <?php echo esc_html($data['a_index']); ?></div>
                <div class="tile">K-index: <?php echo esc_html($data['k_index']); ?></div>
                <div class="tile">X-ray: <?php echo esc_html($data['xray']); ?></div>
                <div class="tile">Lat: <?php echo esc_html($location['lat']); ?>, Lon: <?php echo esc_html($location['lon']); ?></div>
            </div>
            <h4 style="margin: 10px 0 5px;">Band Conditions</h4>
            <div class="band-tiles">
                <?php foreach ($conditions as $band => $status): ?>
                    <div class="tile band-tile" data-band="<?php echo esc_attr($band); ?>" style="background-color: <?php echo $this->propagation_utils->condition_color($status); ?>;">
                        <span><?php echo esc_html($band); ?></span>
                        <span><?php echo esc_html($status); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <h4 style="margin: 10px 0 5px;">Propagation Maps</h4>
            <div class="tile map-tile" id="map-rotate"></div>
            <div class="menu-right">
                <button class="menu-item" data-action="settings">Settings</button>
                <button class="menu-item" data-action="update">Check Update</button>
            </div>
            <div id="settings-widget" style="display: none; margin-top: 10px;">
                <h4>Settings</h4>
                <label>Refresh Interval (minutes):</label>
                <input type="number" id="refresh-interval" value="<?php echo esc_attr($this->propagation_utils->get_refresh_interval() / 60); ?>" min="1">
                <button onclick="saveSettings()">Save</button>
            </div>
            <div id="version-widget" style="margin-top: 10px;">Version: 1.3 (Checking for updates...)</div>
        </div>
        <?php
    }

    public function render_portal_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access.');
        }

        $data = $this->propagation_utils->fetch_propagation_data();
        $conditions = $this->propagation_utils->get_band_conditions($data['sfi'], $data['k_index']);
        $location = $this->propagation_utils->get_location();
        $location_name = get_option('arc_propagation_location_name', 'Custom Location');
        $timezone = get_option('arc_propagation_timezone', 'America/Chicago');
        $timezones = timezone_identifiers_list();
        ?>
        <div class="wrap">
            <h1>Propagation Admin</h1>
            <div id="arc-portal-messages"></div>
            <h2>Current Propagation Data</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>SFI</td><td><?php echo esc_html($data['sfi']); ?></td></tr>
                    <tr><td>Sunspots</td><td><?php echo esc_html($data['sunspots']); ?></td></tr>
                    <tr><td>A-index</td><td><?php echo esc_html($data['a_index']); ?></td></tr>
                    <tr><td>K-index</td><td><?php echo esc_html($data['k_index']); ?></td></tr>
                    <tr><td>X-ray Status</td><td><?php echo esc_html($data['xray']); ?></td></tr>
                    <tr><td>Location</td><td>Lat <?php echo esc_html($location['lat']); ?>, Lon <?php echo esc_html($location['lon']); ?></td></tr>
                </tbody>
            </table>
            <h2>Band Conditions</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Band</th>
                        <th>Condition</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conditions as $band => $status): ?>
                        <tr>
                            <td><?php echo esc_html($band); ?></td>
                            <td style="color: <?php echo $this->propagation_utils->condition_color($status); ?>;"><?php echo esc_html($status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h2>Settings</h2>
            <form id="arc-settings-form">
                <table class="form-table">
                    <tr>
                        <th><label for="location-name">Location Name</label></th>
                        <td>
                            <input type="text" id="location-name" name="location_name" value="<?php echo esc_attr($location_name); ?>" />
                            <p class="description">Enter a name for this location (e.g., "New York City").</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="latitude">Latitude</label></th>
                        <td>
                            <input type="number" id="latitude" name="latitude" value="<?php echo esc_attr($location['lat']); ?>" step="0.1" min="-90" max="90" required />
                            <p class="description">Enter latitude (-90 to 90, e.g., 40.7 for New York City).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="longitude">Longitude</label></th>
                        <td>
                            <input type="number" id="longitude" name="longitude" value="<?php echo esc_attr($location['lon']); ?>" step="0.1" min="-180" max="180" required />
                            <p class="description">Enter longitude (-180 to 180, e.g., -74.0 for New York City).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="timezone">Timezone</label></th>
                        <td>
                            <select id="timezone" name="timezone">
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?php echo esc_attr($tz); ?>" <?php selected($timezone, $tz); ?>><?php echo esc_html($tz); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the timezone for daytime calculations (e.g., America/New_York).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="refresh-interval">Refresh Interval (minutes)</label></th>
                        <td><input type="number" id="refresh-interval" name="refresh_interval" value="<?php echo esc_attr($this->propagation_utils->get_refresh_interval() / 60); ?>" min="1"></td>
                    </tr>
                    <tr>
                        <th>Displayed Bands</th>
                        <td>
                            <?php foreach ($this->propagation_utils->get_bands() as $band): ?>
                                <label><input type="checkbox" name="bands[]" value="<?php echo esc_attr($band); ?>" checked> <?php echo esc_html($band); ?></label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" class="button button-primary" value="Save Changes"></p>
            </form>
        </div>
        <?php
    }

    public function ajax_refresh() {
        check_ajax_referer('arc_propagation_refresh', 'nonce');
        $this->render_admin_widget();
        wp_die();
    }

    public function ajax_save_settings() {
        check_ajax_referer('arc_save_settings', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $refresh_interval = isset($_POST['refresh_interval']) ? (int)$_POST['refresh_interval'] * 60 : $this->propagation_utils->get_refresh_interval();
        $bands = isset($_POST['bands']) && is_array($_POST['bands']) ? array_map('sanitize_text_field', $_POST['bands']) : $this->propagation_utils->get_bands();
        $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : $this->propagation_utils->get_location()['lat'];
        $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : $this->propagation_utils->get_location()['lon'];
        $location_name = isset($_POST['location_name']) ? sanitize_text_field($_POST['location_name']) : 'Custom Location';
        $timezone = isset($_POST['timezone']) ? sanitize_text_field($_POST['timezone']) : 'America/Chicago';

        // Validate latitude and longitude
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            wp_send_json_error('Invalid latitude or longitude values. Latitude must be -90 to 90, longitude -180 to 180.');
        }

        // Validate timezone
        if (!in_array($timezone, timezone_identifiers_list())) {
            wp_send_json_error('Invalid timezone selected.');
        }

        $settings = [
            'refresh_interval' => $refresh_interval,
            'bands' => $bands,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone
        ];
        update_option('arc_propagation_settings', $settings);
        update_option('arc_propagation_location_name', $location_name);

        $this->propagation_utils->set_refresh_interval($refresh_interval);
        $this->propagation_utils->set_bands($bands);
        $this->propagation_utils->set_location($latitude, $longitude, $timezone);

        wp_send_json_success('Settings saved');
    }
}

new ARC_Propagation_Admin();