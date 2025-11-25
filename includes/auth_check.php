<?php
// Allow callers to override the login redirect target (e.g., mobile experience)
if (!isset($login_path) || !is_string($login_path) || strpos($login_path, '/') !== 0) {
    $login_path = '/login.php';
}

// Load configuration early so SESSION_TIMEOUT and other constants are available
$config_path = __DIR__ . '/../config.php';
$system_config_path = __DIR__ . '/../system/config.php';

if (file_exists($config_path)) {
    require_once $config_path;
} elseif (file_exists($system_config_path)) {
    // Fallback in case the top-level config.php is missing from deployment
    require_once $system_config_path;
} else {
    error_log('Configuration file missing at ' . $config_path . ' and ' . $system_config_path);
    exit('Configuration error. Please contact support.');
}

// Always have a timeout value available even if the session is already active
$session_timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 7200;

if (session_status() === PHP_SESSION_NONE) {
    $cookie_options = [
        'lifetime' => $session_timeout,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $cookie_options['secure'] = true;
    }

    session_set_cookie_params($cookie_options);
    session_start();
}

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    session_destroy();
    header('Location: ' . $login_path);
    exit;
}

if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time'] > $session_timeout)) {
    session_destroy();
    $separator = strpos($login_path, '?') === false ? '?' : '&';
    header('Location: ' . $login_path . $separator . 'expired=1');
    exit;
}

/**
 * Ensure automatic role management executes on every authenticated request.
 * training_helpers.php will self-load db_connect.php if $pdo isn’t set and
 * runs auto_manage_user_roles() for the current user.
 */
$th = __DIR__ . '/training_helpers.php';
if (file_exists($th)) {
    require_once $th;
}
?>