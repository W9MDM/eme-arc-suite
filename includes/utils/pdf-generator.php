<?php
if (!defined('ABSPATH')) {
    exit;
}

class ARC_Attendance_PDF {
    private $pdf;

    public function __construct() {
        $dompdf_path = WP_PLUGIN_DIR . '/events-made-easy/dompdf/vendor/autoload.php';
        if (!file_exists($dompdf_path)) {
            add_action('admin_notices', [$this, 'dompdf_missing_notice']);
            $this->pdf = null;
            return;
        }
        require_once $dompdf_path;
        $this->pdf = new \Dompdf\Dompdf();
        $this->pdf->set_option('isRemoteEnabled', true);
        $this->pdf->set_option('isHtml5ParserEnabled', true);
    }

    public function dompdf_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php echo esc_html('DomPDF is missing or not accessible at wp-content/plugins/events-made-easy/dompdf/vendor/autoload.php. Please ensure Events Made Easy is installed and includes DomPDF.'); ?></p>
        </div>
        <?php
    }

    public function generate_pdf($people, $file_path = null) {
        if (is_null($this->pdf)) {
            wp_die('PDF generator not initialized due to missing DomPDF. Check the admin notices for more details.');
        }

        $qr_lib_path = WP_PLUGIN_DIR . '/events-made-easy/class-qrcode.php';
        if (!file_exists($qr_lib_path)) {
            wp_die('QR code library not found at wp-content/plugins/events-made-easy/class-qrcode.php.');
        }
        require_once $qr_lib_path;

        $html = '<!DOCTYPE html><html><head><style>';
        $html .= '
            @page { size: 54mm 85.6mm; margin: 0; }
            body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
            .card { width: 54mm; height: 85.6mm; position: relative; box-sizing: border-box; }
            .header { background-color: #0000FF; color: white; text-align: center; padding: 1mm 0; width: 54mm; font-size: 10pt; line-height: 1; }
            .header-line { margin: 0.5mm 0; }
            .content { text-align: center; padding: 1mm 0; color: black; width: 54mm; }
            .qr-code { display: block; margin: 1mm auto; width: 48mm; height: 48mm; }
            .name { font-size: 11pt; margin: 1mm 0; line-height: 1.2; }
            .callsign { font-size: 11pt; margin: 1mm 0; line-height: 1.2; }
            .footer { background-color: #0000FF; color: white; text-align: center; position: absolute; bottom: 0; width: 54mm; height: 6mm; padding-top: 1mm; font-size: 8pt; line-height: 1; }
        ';
        $html .= '</style></head><body>';

        foreach ($people as $person) {
            $name = trim(($person->firstname ?? '') . ' ' . ($person->lastname ?? '')) ?: 'Unknown Name';
            $callsign = $person->callsign ?? 'No Callsign';

            $qr = new QRCode();
            $qr->addData($callsign);
            $qr->make();
            $image = $qr->createImage(12, 0);
            if ($image) {
                ob_start();
                imagepng($image);
                $image_data = ob_get_clean();
                $qr_base64 = 'data:image/png;base64,' . base64_encode($image_data);
                imagedestroy($image);
                error_log("QR code generated in base64 for $callsign");
            } else {
                $qr_base64 = '';
                error_log("Failed to generate QR code image for $callsign");
            }

            $html .= '<div class="card">';
            $html .= '<div class="header">';
            $html .= '<div class="header-line">PORTER COUNTY</div>';
            $html .= '<div class="header-line">AMATEUR RADIO CLUB</div>';
            $html .= '</div>';
            $html .= '<div class="content">';
            if ($qr_base64) {
                $html .= '<img class="qr-code" src="' . $qr_base64 . '" alt="QR Code" />';
            } else {
                $html .= '<div class="qr-code">[QR Code Failed]</div>';
            }
            $html .= '<div class="name">' . htmlspecialchars($name) . '</div>';
            $html .= '<div class="callsign">' . htmlspecialchars($callsign) . '</div>';
            $html .= '</div>';
            $html .= '<div class="footer">MEETING SIGN IN</div>';
            $html .= '</div>';
        }

        $html .= '</body></html>';

        error_log("Generated HTML: " . substr($html, 0, 500));
        $this->pdf->loadHtml($html);
        $this->pdf->setPaper(array(0, 0, 54 * 2.83464567, 85.6 * 2.83464567), 'portrait');
        $this->pdf->render();

        if ($file_path) {
            $output = $this->pdf->output();
            file_put_contents($file_path, $output);
            error_log("PDF saved to $file_path, size: " . strlen($output) . " bytes");
        } else {
            $output = $this->pdf->output();
            error_log("PDF output size: " . strlen($output) . " bytes");
            $this->pdf->stream('arc_attendance_cards.pdf', array('Attachment' => true));
            exit;
        }
    }
}