<?php
if (!defined('ABSPATH')) {
    exit;
}

class ARC_Propagation_Utils {
    private $bands = ['160m', '80m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m', '2m', '70cm'];
    private $refresh_interval = 3600; // 1 hour in seconds
    private $map_sources = [
        'https://www.hamqsl.com/solar.html',
        'https://prop.kc2g.com/render/grayline.png',
        'https://www.voacap.com/hf/render.php' // Will append ?lat=X&lon=Y dynamically
    ];

    public function __construct() {
        $settings = get_option('arc_propagation_settings', []);
        $this->refresh_interval = $settings['refresh_interval'] ?? $this->refresh_interval;
        $this->bands = $settings['bands'] ?? $this->bands;
    }

    public function get_bands() {
        return $this->bands;
    }

    public function get_location() {
        $settings = get_option('arc_propagation_settings', []);
        return [
            'lat' => $settings['latitude'] ?? 41.6, // Default to Porter County, IN
            'lon' => $settings['longitude'] ?? -87.1,
            'timezone' => $settings['timezone'] ?? 'America/Chicago'
        ];
    }

    public function get_refresh_interval() {
        return $this->refresh_interval;
    }

    public function get_map_sources() {
        $location = $this->get_location();
        $dynamic_map_sources = $this->map_sources;
        // Append lat/lon to VOACAP map
        $dynamic_map_sources[2] .= "?lat={$location['lat']}&lon={$location['lon']}";
        return $dynamic_map_sources;
    }

    public function set_refresh_interval($interval) {
        $this->refresh_interval = $interval;
    }

    public function set_bands($bands) {
        $this->bands = $bands;
    }

    public function set_location($latitude, $longitude, $timezone) {
        $settings = get_option('arc_propagation_settings', []);
        $settings['latitude'] = $latitude;
        $settings['longitude'] = $longitude;
        $settings['timezone'] = $timezone;
        update_option('arc_propagation_settings', $settings);
    }

    public function fetch_propagation_data() {
        $transient_key = 'arc_propagation_data';
        $data = get_transient($transient_key);
        if (false === $data) {
            $noaa_response = wp_remote_get('https://services.swpc.noaa.gov/products/noaa-planetary-k-index.json');
            $hamqsl_response = wp_remote_get('https://www.hamqsl.com/solarxml/');
            $data = ['sfi' => 'N/A', 'sunspots' => 'N/A', 'a_index' => 'N/A', 'k_index' => 'N/A', 'xray' => 'N/A'];

            if (!is_wp_error($noaa_response) && wp_remote_retrieve_response_code($noaa_response) === 200) {
                $noaa_data = json_decode(wp_remote_retrieve_body($noaa_response), true);
                $data['k_index'] = $noaa_data[1][1] ?? 'N/A';
                $data['a_index'] = $noaa_data[1][2] ?? 'N/A';
            }

            if (!is_wp_error($hamqsl_response) && wp_remote_retrieve_response_code($hamqsl_response) === 200) {
                $xml = simplexml_load_string(wp_remote_retrieve_body($hamqsl_response));
                if ($xml) {
                    $data['sfi'] = (string)($xml->solardata->solarflux ?? 'N/A');
                    $data['sunspots'] = (string)($xml->solardata->sunspots ?? 'N/A');
                    $data['xray'] = (string)($xml->solardata->xray ?? 'N/A');
                }
            }

            set_transient($transient_key, $data, $this->refresh_interval);
            error_log('ARC Propagation: Fetched new data - ' . print_r($data, true));
        }
        return $data;
    }

    public function is_daytime() {
        $location = $this->get_location();
        $timezone = new DateTimeZone($location['timezone']);
        $now = new DateTime('now', $timezone);
        $hour = (int)$now->format('H');
        return $hour >= 6 && $hour < 18;
    }

    public function get_band_conditions($sfi, $k_index) {
        $conditions = [];
        $sfi = is_numeric($sfi) ? (int)$sfi : 0;
        $k_index = is_numeric($k_index) ? (int)$k_index : 99;
        $is_daytime = $this->is_daytime();

        foreach ($this->bands as $band) {
            $freq_mhz = $this->band_to_freq($band);
            $condition = 'Closed';

            if ($sfi > 150 && $k_index < 3) {
                $condition = $freq_mhz > 20 ? ($is_daytime ? 'Excellent' : 'Good') : 
                            ($freq_mhz > 10 ? ($is_daytime ? 'Good' : 'Fair') : ($is_daytime ? 'Fair' : 'Good'));
            } elseif ($sfi > 100 && $k_index < 4) {
                $condition = $freq_mhz > 20 ? ($is_daytime ? 'Good' : 'Fair') : 
                            ($freq_mhz > 10 ? 'Fair' : ($is_daytime ? 'Poor' : 'Fair'));
            } elseif ($sfi > 70 && $k_index < 6) {
                $condition = $freq_mhz > 20 ? 'Fair' : ($is_daytime ? 'Poor' : 'Fair');
            } elseif ($k_index >= 6) {
                $condition = 'Poor';
            }

            if (in_array($band, ['6m', '2m', '70cm'])) {
                $condition = $k_index < 4 ? 'Good' : ($k_index < 6 ? 'Fair' : 'Poor');
                if ($band === '6m' && $sfi > 150) $condition = 'Excellent';
            }

            $conditions[$band] = $condition;
        }
        return $conditions;
    }

    private function band_to_freq($band) {
        $freqs = [
            '160m' => 1.8, '80m' => 3.5, '40m' => 7.0, '30m' => 10.1, '20m' => 14.0,
            '17m' => 18.1, '15m' => 21.0, '12m' => 24.9, '10m' => 28.0, '6m' => 50.0,
            '2m' => 144.0, '70cm' => 430.0
        ];
        return $freqs[$band] ?? 0;
    }

    public function condition_color($status) {
        $colors = [
            'Excellent' => '#006400', 'Good' => '#008000', 'Fair' => '#FFA500',
            'Poor' => '#FF4500', 'Closed' => '#FF0000'
        ];
        return $colors[$status] ?? '#000000';
    }
}