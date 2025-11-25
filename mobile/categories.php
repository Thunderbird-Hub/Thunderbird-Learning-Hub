<?php
$login_path = '/mobile/login.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/search_widget.php';
require_once __DIR__ . '/../includes/mobile_beta_gate.php';

// Load training helpers when available
if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

enforce_mobile_beta_access();

$page_title = 'Mobile Categories';
$mobile_active_page = 'categories';
$error_message = '';
$categories = [];
$assigned_categories = [];
$other_categories = [];
$assigned_category_ids = [];
$has_training_categories = false;

try {
    $current_user_id = $_SESSION['user_id'];
    $is_super_user = is_super_admin();
    $is_training = function_exists('is_training_user')
        ? is_training_user()
        : (isset($_SESSION['user_is_in_training']) && $_SESSION['user_is_in_training'] == 1);

    // Check visibility columns
    $visibility_columns_exist = false;
    $subcategory_visibility_columns_exist = false;
    try {
        $pdo->query("SELECT visibility FROM categories LIMIT 1");
        $visibility_columns_exist = true;
    } catch (PDOException $e) {
        $visibility_columns_exist = false;
    }

    try {
        $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
        $subcategory_visibility_columns_exist = true;
    } catch (PDOException $e) {
        $subcategory_visibility_columns_exist = false;
    }

    // Fetch categories with visibility rules
    if ($is_super_user) {
        $categories_query = $visibility_columns_exist
            ? "SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note FROM categories ORDER BY name ASC"
            : "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
        $raw_categories = $pdo->query($categories_query)->fetchAll();
    } elseif (is_admin()) {
        $categories_query = $visibility_columns_exist
            ? "SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note FROM categories WHERE visibility != 'it_only' ORDER BY name ASC"
            : "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
        $raw_categories = $pdo->query($categories_query)->fetchAll();
    } else {
        if ($visibility_columns_exist) {
            $categories_stmt = $pdo->prepare(
                "SELECT id, name, icon, user_id AS category_creator_id, visibility, allowed_users, visibility_note
                 FROM categories
                 WHERE visibility = 'public'
                    OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL))
                 ORDER BY name ASC"
            );
            $categories_stmt->execute(['%"' . $current_user_id . '"%']);
            $raw_categories = $categories_stmt->fetchAll();
        } else {
            $categories_query = "SELECT id, name, icon, user_id AS category_creator_id FROM categories ORDER BY name ASC";
            $raw_categories = $pdo->query($categories_query)->fetchAll();
        }
    }

    // Build category list and load subcategories
    $categories = [];
    $seen_ids = [];
    foreach ($raw_categories as $category_row) {
        $category_id = $category_row['id'];
        if (in_array($category_id, $seen_ids, true)) {
            continue;
        }
        $seen_ids[] = $category_id;

        // Load subcategories per category
        if ($is_super_user && $subcategory_visibility_columns_exist) {
            $sub_stmt = $pdo->prepare(
                "SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count,
                        s.visibility, s.allowed_users, s.visibility_note
                 FROM subcategories s
                 LEFT JOIN posts p ON s.id = p.subcategory_id
                 WHERE s.category_id = ?
                 GROUP BY s.id
                 ORDER BY CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END, s.name ASC"
            );
            $sub_stmt->execute([$category_id]);
            $subcategories = $sub_stmt->fetchAll();
        } elseif (is_admin() && $subcategory_visibility_columns_exist) {
            $sub_stmt = $pdo->prepare(
                "SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count,
                        s.visibility, s.allowed_users, s.visibility_note
                 FROM subcategories s
                 LEFT JOIN posts p ON s.id = p.subcategory_id
                 WHERE s.category_id = ? AND s.visibility != 'it_only'
                 GROUP BY s.id
                 ORDER BY CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END, s.name ASC"
            );
            $sub_stmt->execute([$category_id]);
            $subcategories = $sub_stmt->fetchAll();
        } elseif ($subcategory_visibility_columns_exist) {
            $sub_stmt = $pdo->prepare(
                "SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count,
                        s.visibility, s.allowed_users, s.visibility_note
                 FROM subcategories s
                 LEFT JOIN posts p ON s.id = p.subcategory_id
                 WHERE s.category_id = ?
                   AND (s.visibility = 'public'
                        OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                 GROUP BY s.id
                 ORDER BY CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END, s.name ASC"
            );
            $sub_stmt->execute([$category_id, '%"' . $current_user_id . '"%']);
            $subcategories = $sub_stmt->fetchAll();
        } else {
            $sub_stmt = $pdo->prepare(
                "SELECT s.id, s.name, s.user_id AS subcategory_creator_id, COUNT(p.id) AS post_count
                 FROM subcategories s
                 LEFT JOIN posts p ON s.id = p.subcategory_id
                 WHERE s.category_id = ?
                 GROUP BY s.id
                 ORDER BY CASE WHEN s.name = 'Misc' THEN 2 ELSE 1 END, s.name ASC"
            );
            $sub_stmt->execute([$category_id]);
            $subcategories = $sub_stmt->fetchAll();
        }

        $categories[] = [
            'id' => $category_id,
            'name' => $category_row['name'],
            'icon' => $category_row['icon'],
            'creator_id' => $category_row['category_creator_id'],
            'visibility' => $category_row['visibility'] ?? 'public',
            'allowed_users' => $category_row['allowed_users'] ?? null,
            'visibility_note' => $category_row['visibility_note'] ?? null,
            'subcategories' => $subcategories,
        ];
    }

    // Training users: restrict to assigned categories/subcategories containing posts
    if ($is_training && function_exists('is_training_user') && is_training_user()) {
        $pairStmt = $pdo->prepare(
            "SELECT DISTINCT c.id AS category_id, s.id AS subcategory_id
             FROM user_training_assignments uta
             JOIN training_courses tc ON uta.course_id = tc.id AND tc.is_active = 1
             JOIN training_course_content tcc ON uta.course_id = tcc.course_id
             JOIN posts p ON tcc.content_type = 'post' AND p.id = tcc.content_id
             JOIN subcategories s ON p.subcategory_id = s.id
             JOIN categories c ON s.category_id = c.id
             WHERE uta.user_id = ?"
        );
        $pairStmt->execute([$current_user_id]);
        $pairs = $pairStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pairs)) {
            $categories = [];
        } else {
            $assignedCategoryIds = array_values(array_unique(array_map(fn($r) => (int)$r['category_id'], $pairs)));
            $assignedSubsByCat = [];
            foreach ($pairs as $r) {
                $cid = (int)$r['category_id'];
                $sid = (int)$r['subcategory_id'];
                if (!isset($assignedSubsByCat[$cid])) {
                    $assignedSubsByCat[$cid] = [];
                }
                $assignedSubsByCat[$cid][$sid] = true;
            }

            $categories = array_values(array_filter($categories, function ($cat) use ($assignedCategoryIds) {
                return in_array((int)$cat['id'], $assignedCategoryIds, true);
            }));

            foreach ($categories as &$catRef) {
                $cid = (int)$catRef['id'];
                $allowed = $assignedSubsByCat[$cid] ?? [];
                if (!empty($catRef['subcategories'])) {
                    $catRef['subcategories'] = array_values(array_filter(
                        $catRef['subcategories'],
                        function ($sc) use ($allowed) {
                            return isset($allowed[(int)$sc['id']]);
                        }
                    ));
                }
            }
            unset($catRef);
        }
    }

    // Sort categories alphabetically
    uasort($categories, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $categories = array_values($categories);

    // Build training-highlight buckets for admins
    if (($is_super_user || is_admin()) && function_exists('get_user_assigned_courses')) {
        $assignedCatStmt = $pdo->prepare(
            "SELECT DISTINCT c.id, c.name, COUNT(DISTINCT p.id) as course_count
             FROM user_training_assignments uta
             JOIN training_courses tc ON uta.course_id = tc.id AND tc.is_active = 1
             JOIN training_course_content tcc ON uta.course_id = tcc.course_id
             JOIN posts p ON tcc.content_type = 'post' AND p.id = tcc.content_id
             JOIN subcategories s ON p.subcategory_id = s.id
             JOIN categories c ON s.category_id = c.id
             WHERE uta.user_id = ?
             GROUP BY c.id, c.name
             HAVING course_count > 0
             ORDER BY course_count DESC, c.name ASC"
        );
        $assignedCatStmt->execute([$current_user_id]);
        $assigned_categories_data = $assignedCatStmt->fetchAll(PDO::FETCH_ASSOC);
        $assigned_category_ids = array_map('intval', array_column($assigned_categories_data, 'id'));
        $has_training_categories = !empty($assigned_category_ids);

        $assigned_categories = array_values(array_filter($categories, function ($cat) use ($assigned_category_ids) {
            return in_array((int)$cat['id'], $assigned_category_ids, true);
        }));

        $other_categories = array_values(array_filter($categories, function ($cat) use ($assigned_category_ids) {
            return !in_array((int)$cat['id'], $assigned_category_ids, true);
        }));
    } else {
        $other_categories = $categories;
    }
} catch (PDOException $e) {
    error_log('Mobile categories error: ' . $e->getMessage());
    $error_message = 'Unable to load categories right now.';
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
        .category-chip { display: inline-flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 12px; background: #f1f5f9; color: #1a202c; font-weight: 700; text-decoration: none; }
        .subcategory-tag { display: inline-flex; align-items: center; gap: 6px; padding: 8px 10px; border-radius: 10px; background: #edf2f7; color: #2d3748; font-size: 13px; text-decoration: none; }
        .subcategory-tag .count { background: #e2e8f0; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .subcategory-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .section-title { font-size: 16px; font-weight: 800; color: #1a202c; margin: 0 0 8px; }
        .empty-state { padding: 20px; text-align: center; color: #718096; background: #fff; border: 1px dashed #cbd5e0; border-radius: 12px; }
        @media (max-width: 640px) { .mobile-shell { padding: 14px 12px 90px; } }
    </style>
</head>
<body class="mobile-body">
    <div class="mobile-shell">
        <div class="mobile-hero">
            <h1>Browse categories</h1>
            <p>Quick mobile-first access to knowledge base content.</p>
        </div>

        <div class="mobile-card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <h2 class="section-title" style="margin:0;">Search</h2>
                <a href="/mobile/index.php" style="color:#e2e8f0; font-size:12px; text-decoration:none;">Back to mobile home</a>
            </div>
            <?php if (function_exists('render_search_bar')) { render_search_bar('/search/search.php'); } ?>
        </div>

        <?php if ($error_message): ?>
            <div class="mobile-card" style="border-color:#fed7d7; background:#fff5f5; color:#c53030;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php elseif (empty($categories)): ?>
            <div class="empty-state">No categories available for your account.</div>
        <?php else: ?>
            <?php if ($has_training_categories): ?>
                <div class="mobile-card" id="assigned">
                    <h2 class="section-title">Training categories</h2>
                    <?php foreach ($assigned_categories as $category): ?>
                        <div class="mobile-card" style="margin-bottom:10px;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <a class="category-chip" href="/mobile/subcategory.php?id=<?php echo $category['subcategories'][0]['id'] ?? 0; ?>">
                                    <span><?php echo htmlspecialchars($category['icon'] ?: '📂'); ?></span>
                                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                                </a>
                                <span class="subcategory-tag" style="background:#ebf4ff; color:#4c51bf;">Training focus</span>
                            </div>
                            <?php if (!empty($category['subcategories'])): ?>
                                <div class="subcategory-row">
                                    <?php foreach ($category['subcategories'] as $sub): ?>
                                        <a class="subcategory-tag" href="/mobile/subcategory.php?id=<?php echo $sub['id']; ?>">
                                            <span>📑 <?php echo htmlspecialchars($sub['name']); ?></span>
                                            <span class="count"><?php echo (int)$sub['post_count']; ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mobile-card" id="all">
                <h2 class="section-title">All categories</h2>
                <?php foreach ($other_categories as $category): ?>
                    <div class="mobile-card" style="margin-bottom:10px;">
                        <a class="category-chip" href="/mobile/subcategory.php?id=<?php echo $category['subcategories'][0]['id'] ?? 0; ?>">
                            <span><?php echo htmlspecialchars($category['icon'] ?: '📂'); ?></span>
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                        </a>
                        <?php if (!empty($category['subcategories'])): ?>
                            <div class="subcategory-row">
                                <?php foreach ($category['subcategories'] as $sub): ?>
                                    <a class="subcategory-tag" href="/mobile/subcategory.php?id=<?php echo $sub['id']; ?>">
                                        <span>📑 <?php echo htmlspecialchars($sub['name']); ?></span>
                                        <span class="count"><?php echo (int)$sub['post_count']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="color:#718096; font-size:13px; margin-top:8px;">No subcategories.</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/mobile_nav.php'; ?>
</body>
</html>
