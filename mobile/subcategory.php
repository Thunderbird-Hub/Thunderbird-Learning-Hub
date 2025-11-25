<?php
$login_path = '/mobile/login.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/mobile_beta_gate.php';

if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

enforce_mobile_beta_access();

$page_title = 'Mobile Posts';
$mobile_active_page = 'categories';
$error_message = '';
$subcategory = null;
$posts = [];

$subcategory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($subcategory_id <= 0) {
    header('Location: /mobile/categories.php');
    exit;
}

// Visibility support checks
$subcategory_visibility_columns_exist = false;
$category_visibility_columns_exist = false;
try { $pdo->query("SELECT visibility FROM subcategories LIMIT 1"); $subcategory_visibility_columns_exist = true; } catch (PDOException $e) {}
try { $pdo->query("SELECT visibility FROM categories LIMIT 1"); $category_visibility_columns_exist = true; } catch (PDOException $e) {}

try {
    $current_user_id = $_SESSION['user_id'];
    $is_super_user = is_super_admin();
    $is_admin = is_admin();

    // Subcategory fetch with visibility
    if ($is_super_user && $subcategory_visibility_columns_exist) {
        $stmt = $pdo->prepare(
            "SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
             FROM subcategories s
             JOIN categories c ON s.category_id = c.id
             WHERE s.id = ?"
        );
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    } elseif ($is_admin && $subcategory_visibility_columns_exist) {
        $stmt = $pdo->prepare(
            "SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
             FROM subcategories s
             JOIN categories c ON s.category_id = c.id
             WHERE s.id = ? AND s.visibility != 'it_only' AND c.visibility != 'it_only'"
        );
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    } elseif ($subcategory_visibility_columns_exist && $category_visibility_columns_exist) {
        $stmt = $pdo->prepare(
            "SELECT s.*, c.name AS category_name, c.id AS category_id, c.visibility as category_visibility, c.allowed_users as category_allowed_users
             FROM subcategories s
             JOIN categories c ON s.category_id = c.id
             WHERE s.id = ?
               AND (c.visibility = 'public' OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
               AND (s.visibility = 'public' OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))"
        );
        $stmt->execute([$subcategory_id, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%']);
        $subcategory = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare(
            "SELECT s.*, c.name AS category_name, c.id AS category_id
             FROM subcategories s
             JOIN categories c ON s.category_id = c.id
             WHERE s.id = ?"
        );
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
    }

    if (!$subcategory) {
        $error_message = 'Subcategory not found or you do not have permission to access it.';
    } else {
        // Posts with privacy filtering
        if ($is_super_user) {
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare(
                    "SELECT p.id, p.title, p.content, p.created_at, p.user_id, p.privacy, p.shared_with,
                            u.name AS author_name, u.color AS author_color, COUNT(r.id) AS reply_count
                     FROM posts p
                     LEFT JOIN replies r ON p.id = r.post_id
                     LEFT JOIN users u ON p.user_id = u.id
                     WHERE p.subcategory_id = ?
                     GROUP BY p.id
                     ORDER BY p.created_at DESC"
                );
            } else {
                $stmt = $pdo->prepare(
                    "SELECT p.id, p.title, p.content, p.created_at, p.user_id, p.privacy, p.shared_with, COUNT(r.id) AS reply_count
                     FROM posts p
                     LEFT JOIN replies r ON p.id = r.post_id
                     WHERE p.subcategory_id = ?
                     GROUP BY p.id
                     ORDER BY p.created_at DESC"
                );
            }
            $stmt->execute([$subcategory_id]);
        } elseif ($is_admin) {
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare(
                    "SELECT p.id, p.title, p.content, p.created_at, p.user_id, p.privacy, p.shared_with,
                            u.name AS author_name, u.color AS author_color, COUNT(r.id) AS reply_count
                     FROM posts p
                     LEFT JOIN replies r ON p.id = r.post_id
                     LEFT JOIN users u ON p.user_id = u.id
                     WHERE p.subcategory_id = ? AND p.privacy != 'it_only'
                     GROUP BY p.id
                     ORDER BY p.created_at DESC"
                );
            } else {
                $stmt = $pdo->prepare(
                    "SELECT p.id, p.title, p.content, p.created_at, p.user_id, p.privacy, p.shared_with, COUNT(r.id) AS reply_count
                     FROM posts p
                     LEFT JOIN replies r ON p.id = r.post_id
                     WHERE p.subcategory_id = ? AND p.privacy != 'it_only'
                     GROUP BY p.id
                     ORDER BY p.created_at DESC"
                );
            }
            $stmt->execute([$subcategory_id]);
        } else {
            if (users_table_exists($pdo)) {
                $stmt = $pdo->prepare(
                    "SELECT p.id, p.title, p.content, p.created_at, p.user_id, p.privacy, p.shared_with,
                            u.name AS author_name, u.color AS author_color, COUNT(r.id) AS reply_count
                     FROM posts p
                     LEFT JOIN replies r ON p.id = r.post_id
                     LEFT JOIN users u ON p.user_id = u.id
                     WHERE p.subcategory_id = ?
                       AND (p.privacy = 'public' OR p.user_id = ? OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                     GROUP BY p.id
                     ORDER BY p.created_at DESC"
                );
                $stmt->execute([$subcategory_id, $current_user_id, '%"' . $current_user_id . '"%']);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT p.id, p.title, p.content, p.created_at, p.user_id, p.privacy, p.shared_with, COUNT(r.id) AS reply_count
                     FROM posts p
                     LEFT JOIN replies r ON p.id = r.post_id
                     WHERE p.subcategory_id = ?
                       AND (p.privacy = 'public' OR p.user_id = ? OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                     GROUP BY p.id
                     ORDER BY p.created_at DESC"
                );
                $stmt->execute([$subcategory_id, $current_user_id, '%"' . $current_user_id . '"%']);
            }
        }

        $posts = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Mobile subcategory error: ' . $e->getMessage());
    $error_message = 'Unable to load posts right now.';
}

function mobile_excerpt($html_content, $length = 180) {
    $plain = trim(strip_tags($html_content));
    if (mb_strlen($plain) <= $length) {
        return $plain;
    }
    return mb_substr($plain, 0, $length) . '…';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=20251121">
    <style>
        body.mobile-body { background: #f7fafc; padding: 0; margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .mobile-shell { max-width: 960px; margin: 0 auto; padding: 16px 16px 90px; }
        .mobile-hero { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 18px; padding: 20px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.22); margin-bottom: 16px; }
        .mobile-hero h1 { margin: 0 0 6px; font-size: 22px; font-weight: 700; }
        .mobile-hero p { margin: 0; opacity: 0.92; font-size: 14px; }
        .mobile-card { background: white; border-radius: 14px; padding: 14px; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; margin-bottom: 14px; }
        .post-tile { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #f8fafc; margin-bottom: 10px; text-decoration: none; color: #1a202c; display: block; }
        .post-tile h3 { margin: 0 0 6px; font-size: 16px; font-weight: 800; }
        .post-tile p { margin: 0 0 6px; color: #4a5568; font-size: 14px; }
        .post-meta { display: flex; gap: 10px; flex-wrap: wrap; font-size: 12px; color: #718096; }
        .empty-state { padding: 20px; text-align: center; color: #718096; background: #fff; border: 1px dashed #cbd5e0; border-radius: 12px; }
        @media (max-width: 640px) { .mobile-shell { padding: 14px 12px 90px; } }
    </style>
</head>
<body class="mobile-body">
    <div class="mobile-shell">
        <div class="mobile-hero">
            <h1><?php echo $subcategory ? htmlspecialchars($subcategory['name']) : 'Posts'; ?></h1>
            <p><?php echo $subcategory ? htmlspecialchars($subcategory['category_name']) : 'Subcategory'; ?></p>
        </div>

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
            <a href="/mobile/categories.php" style="text-decoration:none; color:#4c51bf; font-weight:700;">← Back to categories</a>
        </div>

        <?php if ($error_message): ?>
            <div class="mobile-card" style="border-color:#fed7d7; background:#fff5f5; color:#c53030;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php elseif (empty($posts)): ?>
            <div class="empty-state">No posts available in this subcategory.</div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <a class="post-tile" href="/mobile/post.php?id=<?php echo $post['id']; ?>">
                    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p><?php echo htmlspecialchars(mobile_excerpt($post['content'])); ?></p>
                    <div class="post-meta">
                        <span>🕒 <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                        <span>💬 <?php echo (int)$post['reply_count']; ?> updates</span>
                        <?php if (!empty($post['author_name'])): ?>
                            <span>✍️ <?php echo htmlspecialchars($post['author_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/mobile_nav.php'; ?>
</body>
</html>
