<?php
if (!defined('ABSPATH')) {
    exit;
}

// Include dashboard files
require_once dirname(__FILE__) . '/dashboards/membership-renewals.php';
require_once dirname(__FILE__) . '/dashboards/accounting-summary.php';
require_once dirname(__FILE__) . '/dashboards/pending-payments.php';
require_once dirname(__FILE__) . '/dashboards/discounts-used.php';
require_once dirname(__FILE__) . '/dashboards/checkbook-log.php';