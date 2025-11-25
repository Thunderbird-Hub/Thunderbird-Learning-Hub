<?php
// Mobile-specific login entrypoint that routes users into the mobile hub
$post_login_redirect = '/mobile/index.php';
$page_title = 'Mobile Login';

require_once __DIR__ . '/../login.php';
