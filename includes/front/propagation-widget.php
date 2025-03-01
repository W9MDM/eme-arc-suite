<?php
if (!defined('ABSPATH')) {
    exit;
}

class ARC_Propagation_Front {
    private $propagation_utils;

    public function __construct() {
        $this->propagation_utils = new ARC_Propagation_Utils();
        add_shortcode('arc_propagation', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('arc-propagation-css', EME_ARC_SUITE_URL . 'assets/css/arc-propagation.css');
        wp_enqueue_script('arc-propagation-js', EME_ARC_SUITE_URL . 'assets/js/arc-propagation.js', ['jquery'], '1.3', true);
        wp_localize_script('arc-propagation-js', 'arcPropagation', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('arc_propagation_refresh'),
            'settings_nonce' => wp_create_nonce('arc_save_settings'),
            'interval' => $this->propagation_utils->get_refresh_interval() * 1000,
            'maps' => $this->propagation_utils->get_map_sources()
        ]);
    }

    public function render_shortcode() {
        $data = $this->propagation_utils->fetch_propagation_data();
        $conditions = $this->propagation_utils->get_band_conditions($data['sfi'], $data['k_index']);
        $location = $this->propagation_utils->get_location();
        $location_name = get_option('arc_propagation_location_name', 'Custom Location');
        ob_start();
        ?>
        <div class="arc-propagation-dashboard" style="font-family: Arial, sans-serif; padding: 10px; background: #f5f5f5; border: 1px solid #ddd;">
            <h3 style="margin: 0 0 10px; color: #0000FF;"><?php echo esc_html($location_name); ?> Propagation</h3>
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
        </div>
        <?php
        return ob_get_clean();
    }
}

new ARC_Propagation_Front();