<?php
$login_path = '/mobile/login.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/mobile_beta_gate.php';

// Load training helpers when available
if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

enforce_mobile_beta_access();

$page_title = 'Mobile Profile';
$mobile_active_page = 'profile';
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$is_training_user = function_exists('is_training_user') ? is_training_user() : false;
$role_display = function_exists('get_user_role_display') ? get_user_role_display() : ($_SESSION['user_role'] ?? 'User');

$user_details = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'role' => $role_display,
    'color' => $_SESSION['user_color'] ?? '#667eea',
    'last_login' => null,
];

if ($user_id > 0) {
    try {
        $user_stmt = $pdo->prepare("SELECT name, role, color, last_login FROM users WHERE id = ? LIMIT 1");
        $user_stmt->execute([$user_id]);
        $row = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $user_details['name'] = $row['name'] ?? $user_details['name'];
            $user_details['role'] = $row['role'] ?? $user_details['role'];
            $user_details['color'] = $row['color'] ?? $user_details['color'];
            $user_details['last_login'] = $row['last_login'] ?? null;
        }
    } catch (Exception $e) {
        // Keep session values on error
    }
}

function format_mobile_datetime($value) {
    if (!$value) {
        return 'N/A';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, Y g:i a', $timestamp) : htmlspecialchars((string) $value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME); ?></title>
    <link rel="manifest" href="/assets/pwa/manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
    <script src="/assets/pwa/install-helper.js" defer></script>
    <link rel="preload" href="/assets/css/style.css?v=20260205" as="style">
    <link rel="stylesheet" href="/assets/css/style.css?v=20260205" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="/assets/css/style.css?v=20260205"></noscript>
    <style>
        body.mobile-body {
            background: #f7fafc;
            padding: 0;
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .mobile-shell {
            max-width: 960px;
            margin: 0 auto;
            padding: 16px 16px 90px;
        }
        .mobile-hero {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.22);
            margin-bottom: 16px;
        }
        .mobile-hero h1 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 700;
        }
        .mobile-hero p {
            margin: 0;
            opacity: 0.92;
            font-size: 14px;
        }
        .profile-card {
            background: white;
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
        }
        .profile-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #edf2f7;
            font-weight: 600;
            color: #2d3748;
        }
        .profile-row:last-child {
            border-bottom: none;
        }
        .profile-label {
            color: #718096;
            font-weight: 500;
            font-size: 14px;
        }
        .profile-value {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
        }
        .badge-primary {
            background: #ebf4ff;
            color: #4c51bf;
        }
        .badge-accent {
            background: #f0fff4;
            color: #2f855a;
        }
        .swatch {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
        }
        .profile-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .profile-actions a {
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 700;
            text-align: center;
            border: 1px solid #e2e8f0;
            color: #2d3748;
            background: #f7fafc;
        }
        .profile-actions a.primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }
        @media (max-width: 640px) {
            .mobile-shell {
                padding: 14px 12px 90px;
            }
            .profile-card {
                padding: 14px;
            }
        }
    </style>
</head>
<body class="mobile-body">
    <div class="mobile-shell">
        <div class="mobile-hero">
            <h1>Profile</h1>
            <p>Signed in as <?php echo htmlspecialchars($user_details['name']); ?></p>
        </div>

        <div class="profile-card" aria-labelledby="profile-details-heading">
            <h2 id="profile-details-heading" style="margin: 0 0 8px; color: #1a202c;">Your Details</h2>
            <div class="profile-row">
                <div class="profile-label">Name</div>
                <div class="profile-value"><?php echo htmlspecialchars($user_details['name']); ?></div>
            </div>
            <div class="profile-row">
                <div class="profile-label">Role</div>
                <div class="profile-value">
                    <span class="badge badge-primary"><?php echo htmlspecialchars($role_display); ?></span>
                </div>
            </div>
            <div class="profile-row">
                <div class="profile-label">Training</div>
                <div class="profile-value">
                    <?php if ($is_training_user): ?>
                        <span class="badge badge-accent">In Training</span>
                    <?php else: ?>
                        <span class="badge" style="background:#edf2f7; color:#2d3748;">Standard Access</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-row">
                <div class="profile-label">Last Login</div>
                <div class="profile-value"><?php echo format_mobile_datetime($user_details['last_login']); ?></div>
            </div>
            <div class="profile-row">
                <div class="profile-label">Color</div>
                <div class="profile-value">
                    <span class="swatch" style="background: <?php echo htmlspecialchars($user_details['color']); ?>;"></span>
                    <span><?php echo htmlspecialchars($user_details['color']); ?></span>
                </div>
            </div>
        </div>

        <div class="profile-card" aria-labelledby="profile-actions-heading">
            <h2 id="profile-actions-heading" style="margin: 0 0 8px; color: #1a202c;">Actions</h2>
            <div class="profile-actions">
                <a class="primary" href="/mobile/index.php">Return to Home</a>
                <a href="/logout.php?redirect=/mobile/login.php">Sign out</a>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/mobile_nav.php'; ?>

    <script src="/assets/js/mobile-shell.js" defer></script>
</body>
</html>
