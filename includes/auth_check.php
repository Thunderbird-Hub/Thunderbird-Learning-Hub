<?php
// Load configuration only if SESSION_TIMEOUT isn't defined yet so we avoid
// touching global state multiple times while still guaranteeing a timeout.
if (!defined('SESSION_TIMEOUT')) {
    $config_path = __DIR__ . '/../config.php';
    $system_config_path = __DIR__ . '/../system/config.php';

    if (file_exists($config_path)) {
        require_once $config_path;
    } elseif (file_exists($system_config_path)) {
        // Fallback in case the top-level config.php is missing from deployment
        require_once $system_config_path;
    }
}

// Always have a timeout value available even if the config file was missing
// (helps keep styling/assets untouched in failure scenarios).
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 7200);
}

$session_timeout = SESSION_TIMEOUT;

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
    header('Location: /login.php');
    exit;
}

if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time'] > $session_timeout)) {
    session_destroy();
    header('Location: /login.php?expired=1');
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