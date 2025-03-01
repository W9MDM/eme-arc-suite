# Installation Guide for EME ARC Suite

This guide provides step-by-step instructions to install and configure the EME ARC Suite plugin.

## Prerequisites
- WordPress 5.0 or higher installed.
- [Events Made Easy](https://wordpress.org/plugins/events-made-easy/) plugin installed and activated.
- [EME ARC Suite - Core](https://github.com/W9MDM/eme-arc-suite-core) plugin installed and activated (see below).
- PHP 7.4+ (required for DomPDF compatibility in attendance cards).
- Write permissions in `wp-content/plugins/` and `eme-arc-suite/assets/` directories.

## Steps
1. **Install and Activate EME ARC Suite - Core**
   - Download `eme-arc-suite-core` from [GitHub](https://github.com/W9MDM/eme-arc-suite-core).
   - Upload the `eme-arc-suite-core` folder to `wp-content/plugins/` via FTP/SFTP or WordPress admin upload.
   - Path should be `wp-content/plugins/eme-arc-suite-core/eme-arc-core.php`.
   - Activate "EME ARC Suite - Core" in Plugins > Installed Plugins.
   - Note: This plugin must be installed and activated first, as it’s a required dependency for EME ARC Suite and must be active before proceeding to avoid activation errors.

2. **Download EME ARC Suite**
   - Download the `eme-arc-suite` folder from [GitHub](https://github.com/W9MDM/eme-arc-suite).

3. **Upload to WordPress**
   - Copy the `eme-arc-suite` folder to `wp-content/plugins/` via FTP/SFTP or WordPress admin upload.
   - Path should be `wp-content/plugins/eme-arc-suite/`.

4. **Install Assets**
   - Ensure the following files are in `eme-arc-suite/assets/css/`:
     - `eme-arc-tabs.css`
     - `eme-checkin.css`
     - `eme-member-checkin.css`
     - `eme-membership.css`
     - `arc-propagation.css`
   - Ensure the following files are in `eme-arc-suite/assets/js/`:
     - `eme-arc-tabs.js`
     - `eme-checkin.js`
     - `eme-member-checkin.js`
     - `eme-membership.js`
     - `arc-propagation.js`
   - See `assets-list.txt` for descriptions and sources if missing.

5. **Activate the Plugin**
   - Log in to WordPress admin.
   - Navigate to Plugins > Installed Plugins.
   - Find "EME ARC Suite" and click "Activate".
   - Note: Ensure "EME ARC Suite - Core" and "Events Made Easy" are already active, as EME ARC Suite depends on them. Failure to activate "EME ARC Suite - Core" first may result in errors.

6. **Verify Dependencies**
   - Ensure "Events Made Easy" and "EME ARC Suite - Core" are active. The plugin will deactivate with an error message if these are missing.
   - Check for DomPDF (`wp-content/plugins/events-made-easy/dompdf/vendor/autoload.php`) and QR code library (`wp-content/plugins/events-made-easy/class-qrcode.php`) for attendance cards.

7. **Configure Shortcodes**
   - Create pages/posts and add shortcodes (e.g., `[eme_event_checkin]`, `[eme_member_checkin]`, `[eme_membership_renewal]`, `[arc_propagation]`).
   - See `shortcodes.txt` for details on each shortcode.

8. **Test Functionality**
   - Visit a page with `[eme_event_checkin]` and check in with a known callsign.
   - Access "EME ARC Management" in the admin menu to verify submenus (e.g., Callsign Lookup, Member Check).

## Troubleshooting
- **Plugin Deactivates**: Check the error message or `debug.log` for missing dependencies (e.g., "Events Made Easy" or "EME ARC Suite - Core"). Ensure "EME ARC Suite - Core" is activated before "EME ARC Suite."
- **Assets Not Loading**: Verify file permissions and paths in `assets/`.
- **API Errors**: Ensure internet access for HamDB and propagation APIs.
- **PDF Generation Fails**: Confirm DomPDF and QR code library paths.

## Notes
- Initial configuration assumes default EME settings. Adjust via admin submenus as needed.
- Membership renewal pages are auto-created; edit them in Pages if customization is required.
- The core plugin (`eme-arc-core.php`) must be installed and activated before `eme-arc-suite` to avoid activation errors and ensure proper functionality.