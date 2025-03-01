# Developer Notes for EME ARC Suite

## Structure
- **Root**: `eme-arc-suite.php` (entry point, includes all files).
- **includes/admin/**: Admin pages and functionality.
- **includes/front/**: Front-end shortcodes and AJAX handlers.
- **includes/utils/**: Shared utilities (database, callsign, PDF, propagation).
- **includes/user/**: User-related hooks (e.g., callsign usernames).
- **assets/**: CSS (`css/`) and JS (`js/`) files.

## Key Functions
- **Database**: `eme_arc_create_email_tracking_table()`, `eme_checkin_activate()`, `eme_member_checkin_activate()` in `utils/database.php`.
- **Callsign**: `eme_check_callsign()` in `utils/callsign-utils.php`.
- **PDF**: `ARC_Attendance_PDF` class in `utils/pdf-generator.php`.
- **Propagation**: `ARC_Propagation_Utils` class in `utils/propagation-utils.php`.
- **User**: `eme_force_callsign_*` hooks in `user/force-callsign.php`.

## Extension Points
- **Add New Shortcodes**: Create a new file in `includes/front/`, define a shortcode, and include it in `eme-arc-suite.php`.
- **Enhance Admin**: Add submenus in `includes/admin/` files under `eme-arc-manage` slug.
- **Custom Utilities**: Extend `includes/utils/` with new helpers, ensuring they’re included in the main file.
- **Hooks**: Use WordPress hooks (e.g., `admin_menu`, `wp_enqueue_scripts`) for integration.

## Tips
- **Debugging**: Enable `WP_DEBUG` and check `debug.log` for logs (extensive logging included).
- **Dependencies**: Ensure EME functions (`eme_get_events`, `eme_db_insert_person`, etc.) are available.
- **Assets**: Add new CSS/JS in `assets/`, enqueue via appropriate files.
- **API Calls**: Handle HamDB and propagation API failures gracefully (see `member-checkin.php`).

## Known Issues
- **GitHub URL**: Update `[YOUR_GITHUB_USERNAME]` in `arc-propagation.js` for update checks.
- **Dynamic JS**: Removed in favor of static files; revert if dynamic generation is preferred.