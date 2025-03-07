# Changelog for EME ARC Suite

## [1.0.2] - 2025-03-07
- **Enhancements**
  - Added `eme-member-checkin-front.php` for improved front-end member check-in functionality.
  - Explicitly included `member-check.php` for front-end shortcode support.
  - Added shortcode registration via `eme_register_member_check_shortcodes()` for member check-in features.
  - Enhanced dependency checks to include `EME_ARC_CORE_VERSION` for stricter core plugin validation.
  - Improved error logging for missing utility, admin, and front-end files.

## [1.0.0] - 2025-03-01
- **Initial Release**
  - Combined features from multiple plugins into a single suite:
    - EME ARC Membership Addon - Admin (v2.6.1)
    - EME Event Check-In Dashboard (v1.0.1)
    - ARC Propagation Forecast Widget (v1.3)
    - ARC Attendance Cards (v1.3)
    - EME Member Check-In Dashboard (v1.3.0)
    - EME Membership Renewal Dashboard (v1.0.15)
    - EME Force Callsign Usernames (v1.1.1)
  - Modular structure with `includes/` subdirectories.
  - Added shortcodes: `[eme_event_checkin]`, `[eme_member_checkin]`, `[eme_membership_renewal]`, `[arc_propagation]`.
  - Unified dependency checks and database setup.