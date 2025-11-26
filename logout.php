<?php
session_start();
session_unset();
session_destroy();

$redirect = '/login.php';

if (isset($_GET['redirect'])) {
    $candidate = $_GET['redirect'];

    if (is_string($candidate) && strpos($candidate, '/') === 0 && strpos($candidate, '://') === false) {
        $redirect = $candidate;
    }
}

$separator = strpos($redirect, '?') === false ? '?' : '&';
header('Location: ' . $redirect . $separator . 'logged_out=1');
exit;
?>
