<?php
// Mobile-friendly alias for the search autocomplete endpoint
// Flag the underlying endpoint to return mobile-friendly URLs
if (!defined('SVS_MOBILE_AUTOCOMPLETE')) {
    define('SVS_MOBILE_AUTOCOMPLETE', true);
}

require_once __DIR__ . '/../search/search_autocomplete.php';
exit;
