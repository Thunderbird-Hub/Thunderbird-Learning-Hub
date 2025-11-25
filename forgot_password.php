<?php
// Basic reset request capture page for users who forget their PIN/password.
$config_path = __DIR__ . '/config.php';
$system_config_path = __DIR__ . '/system/config.php';

if (file_exists($config_path)) {
    require_once $config_path;
} elseif (file_exists($system_config_path)) {
    require_once $system_config_path;
} else {
    error_log('Configuration file missing at ' . $config_path . ' and ' . $system_config_path);
    exit('Configuration error. Please contact support.');
}

if (session_status() === PHP_SESSION_NONE) {
    $cookie_options = [
        'lifetime' => SESSION_TIMEOUT,
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

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address so we can help you.';
    } else {
        $sanitized_email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $sanitized_notes = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');
        $log_message = sprintf('Forgot password request from %s. Notes: %s', $sanitized_email, $sanitized_notes ?: 'None provided');
        error_log($log_message);
        $success_message = 'Request received. A team member will contact you with reset instructions.';
    }
}

$page_title = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="/assets/pwa/manifest.json">
    <meta name="theme-color" content="#667eea">
    <script src="/assets/pwa/install-helper.js" defer></script>
</head>
<body>
    <div class="login-container">
        <div class="auth-card">
            <div class="auth-header">
                <div>
                    <p class="auth-kicker">Trouble signing in?</p>
                    <h1 class="login-title"><?php echo SITE_NAME; ?></h1>
                </div>
                <div class="auth-avatar" aria-hidden="true">🛠️</div>
            </div>

            <div class="auth-intro">
                <p>Enter your work email and any helpful notes. We will verify your account and share reset steps.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message touch-alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="success-message touch-alert"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php" class="auth-form">
                <div class="form-group">
                    <label for="email" class="form-label">Work email</label>
                    <input type="email" id="email" name="email" class="auth-input" placeholder="name@example.com" required inputmode="email" autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="notes" class="form-label">Extra details (optional)</label>
                    <textarea id="notes" name="notes" rows="3" class="auth-input" placeholder="Tell us if you changed devices or recently reset your PIN"></textarea>
                </div>
                <div class="auth-actions between">
                    <a class="auth-link" href="/login.php">Back to login</a>
                    <button type="submit" class="btn btn-primary touch-button">Send request</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
