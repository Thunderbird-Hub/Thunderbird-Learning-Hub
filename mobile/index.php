<?php
$login_path = '/mobile/login.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/search_widget.php';
require_once __DIR__ . '/../includes/mobile_beta_gate.php';

// Load training helpers when available without duplicating logic
if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

enforce_mobile_beta_access();

$page_title = 'Mobile Hub';
$display_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$role_display = function_exists('get_user_role_display') ? get_user_role_display() : 'User';
$is_training_user = function_exists('is_training_user') ? is_training_user() : false;
$mobile_active_page = 'index';
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
    <link rel="preload" href="/assets/css/style.css?v=20251121" as="style">
    <link rel="stylesheet" href="/assets/css/style.css?v=20251121" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="/assets/css/style.css?v=20251121"></noscript>
    <style id="mobile-critical-style">
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
        .mobile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
        }
        .mobile-card {
            background: white;
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
        }
        .mobile-card h2 {
            margin: 0 0 8px;
            font-size: 17px;
            color: #1a202c;
        }
        .mobile-card p {
            margin: 0 0 12px;
            color: #4a5568;
            font-size: 14px;
        }
        .mobile-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .mobile-links a {
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 12px;
            background: #edf2f7;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .mobile-links a.primary-link {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .mobile-section {
            margin-bottom: 16px;
        }
        .mobile-section .card {
            margin-bottom: 12px;
        }
        @media (max-width: 640px) {
            .mobile-shell {
                padding: 14px 12px 90px;
            }
            .mobile-card {
                padding: 14px;
            }
        }
        .mobile-beta-banner {
            background: #fff7ed;
            color: #9c4221;
            border: 1px solid #fbd38d;
            border-radius: 12px;
            padding: 10px 12px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            font-weight: 600;
        }

        @media (max-width: 768px) {
            /* Keep the Quick Search input usable on mobile */
            #searchForm {
                align-items: stretch;
            }

            #searchForm .form-input {
                flex: 1 1 auto;
                min-width: 0;
            }

            #searchForm .btn {
                width: auto;
                flex: 0 0 auto;
            }
        }
    </style>
</head>
<body class="mobile-body">
    <div class="mobile-shell">
        <?php if (defined('MOBILE_BETA_BANNER') && MOBILE_BETA_BANNER): ?>
            <div class="mobile-beta-banner" role="alert" aria-live="polite">
                <span>🧪</span>
                <span><?php echo htmlspecialchars(MOBILE_BETA_BANNER); ?></span>
            </div>
        <?php endif; ?>
        <div class="mobile-hero" id="home">
            <h1>Hi <?php echo htmlspecialchars($display_name); ?> 👋</h1>
            <p>
                <?php echo htmlspecialchars(SITE_NAME); ?> · <?php echo htmlspecialchars($role_display); ?>
                <?php if ($is_training_user): ?>
                    <span style="margin-left: 8px; padding: 4px 8px; background: rgba(255,255,255,0.18); border-radius: 10px; font-size: 12px;">In Training</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="mobile-section">
            <div class="mobile-card">
                <h2>Quick Search</h2>
                <p>Find posts, categories, or training content fast.</p>
                <?php if (function_exists('render_search_bar')) { render_search_bar('/search/search.php'); } ?>
            </div>
        </div>

        <div class="mobile-grid">
            <div class="mobile-card" id="categories">
                <h2>Categories</h2>
                <p>Browse the knowledge base categories and subcategories.</p>
                <div class="mobile-links">
                    <a class="primary-link" href="/mobile/categories.php">📱 Mobile Categories</a>
                    <a href="/mobile/categories.php#all">📂 Browse Categories</a>
                </div>
            </div>

            <div class="mobile-card" id="training">
                <h2>Training</h2>
                <p>Stay on top of your training assignments and progress.</p>
                <div class="mobile-links">
                    <a class="primary-link" href="/mobile/training.php">📱 Mobile Training</a>
                    <a class="primary-link" href="/training/training_dashboard.php">🎓 Training Dashboard</a>
                </div>
            </div>

            <div class="mobile-card" id="quizzes">
                <h2>Quizzes</h2>
                <p>Jump straight into your assigned quizzes from training.</p>
                <div class="mobile-links">
                    <a class="primary-link" href="/training/training_dashboard.php#quizzes">📝 View Quizzes</a>
                </div>
            </div>

            <div class="mobile-card" id="profile">
                <h2>Profile</h2>
                <p>View your role, training status, and manage your session.</p>
                <div class="mobile-links">
                    <span style="background: #edf2f7; padding: 10px 12px; border-radius: 12px; font-weight: 600; color: #2d3748;">👤 <?php echo htmlspecialchars($role_display); ?></span>
                    <?php if ($is_training_user): ?>
                        <span style="background: #ebf4ff; padding: 10px 12px; border-radius: 12px; font-weight: 600; color: #4c51bf;">🎯 Training Active</span>
                    <?php endif; ?>
                    <a href="/logout.php">🚪 Logout</a>
                </div>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/mobile_nav.php'; ?>

    <script src="/assets/js/mobile-shell.js" defer></script>
</body>
</html>
