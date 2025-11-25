<?php
require_once __DIR__ . '/user_helpers.php';

function is_mobile_beta_enabled() {
    return defined('MOBILE_BETA_ENABLED') ? (bool) MOBILE_BETA_ENABLED : false;
}

function is_mobile_beta_user_allowed() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    if (function_exists('is_super_admin') && is_super_admin()) {
        return true;
    }

    if (function_exists('is_admin') && is_admin()) {
        return true;
    }

    $allowed_ids = defined('MOBILE_BETA_USER_IDS') && is_array(MOBILE_BETA_USER_IDS)
        ? array_map('intval', MOBILE_BETA_USER_IDS)
        : [];

    $current_user_id = (int) $_SESSION['user_id'];
    if (in_array($current_user_id, $allowed_ids, true)) {
        return true;
    }

    $session_role = isset($_SESSION['user_role']) ? strtolower(str_replace(' ', '_', $_SESSION['user_role'])) : '';
    $allowed_roles = defined('MOBILE_BETA_ROLES') && is_array(MOBILE_BETA_ROLES)
        ? array_map('strtolower', MOBILE_BETA_ROLES)
        : [];

    return $session_role && in_array($session_role, $allowed_roles, true);
}

function enforce_mobile_beta_access() {
    if (!is_mobile_beta_enabled() || !is_mobile_beta_user_allowed()) {
        http_response_code(403);
        echo '<!DOCTYPE html>';
        echo '<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Beta Access Required</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}';
        echo '.card{background:rgba(15,23,42,0.9);border:1px solid #1f2937;border-radius:12px;padding:18px 16px;max-width:420px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,0.35);}';
        echo 'h1{margin-bottom:8px;font-size:20px;color:#cbd5e1;}p{margin:0;line-height:1.5;color:#94a3b8;}</style></head><body>';
        echo '<div class="card"><h1>Mobile beta</h1><p>Access is limited to invited testers. Please use the desktop site or request beta access.</p></div>';
        echo '</body></html>';
        exit;
    }
}
?>
