<?php
if (!defined('ABSPATH')) {
    exit;
}

function eme_check_callsign($callsign) {
    global $wpdb;
    
    if (empty($callsign)) {
        return 0;
    }
    
    $callsign = strtoupper(trim($callsign));
    $member_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT m.member_id 
             FROM {$wpdb->prefix}eme_members m
             INNER JOIN {$wpdb->prefix}eme_answers a ON m.person_id = a.related_id
             WHERE a.type = 'person' AND a.field_id = 2 AND a.answer = %s AND m.status = 1",
            $callsign
        )
    );
    
    return $member_id ? intval($member_id) : 0;
}