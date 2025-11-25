<?php
$login_path = '/mobile/login.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/search_widget.php';
require_once __DIR__ . '/../includes/mobile_beta_gate.php';

enforce_mobile_beta_access();

$page_title = 'Mobile Search';
$is_super_user = is_super_admin();
$current_user_id = $_SESSION['user_id'];

// Search parameters (mobile keeps defaults simple)
$search_query = trim($_GET['q'] ?? '');
$search_in = 'all';
$date_range = 'all';
$sort_by = 'relevance';
$content_type = 'all';
$author_id = 'all';
$exact_match = isset($_GET['exact_match']);
$include_content = true;

$results = [];
$search_performed = false;
$error_message = '';
$use_fulltext = true;

// Check if FULLTEXT search is supported; fall back to LIKE when unavailable
try {
    $pdo->query("SELECT MATCH(name) AGAINST('test') as relevance FROM categories LIMIT 1");
} catch (PDOException $e) {
    $use_fulltext = false;
}

// Build date filter (kept for parity with desktop search)
$date_filter = '';
switch ($date_range) {
    case 'today':
        $date_filter = 'AND DATE(created_at) = CURDATE()';
        break;
    case 'week':
        $date_filter = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        break;
    case 'month':
        $date_filter = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        break;
    case 'year':
        $date_filter = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
        break;
}

// Build author filter
$author_filter = '';
$author_filter_params = [];
if ($author_id !== 'all' && is_numeric($author_id)) {
    $author_filter = 'AND p.user_id = ?';
    $author_filter_params = [$author_id];
}

// Build content type filter
$content_type_filter = '';
switch ($content_type) {
    case 'posts':
        $content_type_filter = "AND type = 'post'";
        break;
    case 'replies':
        $content_type_filter = "AND type = 'reply'";
        break;
    case 'categories':
        $content_type_filter = "AND type = 'category'";
        break;
    case 'subcategories':
        $content_type_filter = "AND type = 'subcategory'";
        break;
}

// Build sort order
$sort_order = 'ORDER BY relevance DESC, created_at DESC';
switch ($sort_by) {
    case 'date_newest':
        $sort_order = 'ORDER BY created_at DESC';
        break;
    case 'date_oldest':
        $sort_order = 'ORDER BY created_at ASC';
        break;
    case 'title_alphabetical':
        $sort_order = 'ORDER BY title ASC';
        break;
}

// Modify search query for exact match if needed
$search_term = $search_query;
if ($exact_match) {
    $search_term = '"' . $search_query . '"';
}

if (!empty($search_query)) {
    $search_performed = true;

    try {
        $search_categories = ($search_in === 'all' || $search_in === 'categories');
        $like_query = '%' . $search_query . '%';

        // Category search
        if ($search_categories) {
            if ($is_super_user) {
                if ($use_fulltext) {
                    $category_search = $pdo->prepare(
                        "SELECT id, name, icon, 'category' as type, visibility,
                                MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM categories
                         WHERE MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                         ORDER BY relevance DESC"
                    );
                    $category_search->execute([$search_query, $search_query]);
                } else {
                    $category_search = $pdo->prepare(
                        "SELECT id, name, icon, 'category' as type, visibility,
                                (CASE WHEN name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM categories
                         WHERE name LIKE ?
                         ORDER BY name ASC"
                    );
                    $category_search->execute([$like_query, $like_query]);
                }
            } elseif (is_admin()) {
                if ($use_fulltext) {
                    $category_search = $pdo->prepare(
                        "SELECT id, name, icon, 'category' as type, visibility,
                                MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM categories
                         WHERE visibility != 'it_only'
                         AND MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                         ORDER BY relevance DESC"
                    );
                    $category_search->execute([$search_query, $search_query]);
                } else {
                    $category_search = $pdo->prepare(
                        "SELECT id, name, icon, 'category' as type, visibility,
                                (CASE WHEN name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM categories
                         WHERE visibility != 'it_only'
                         AND name LIKE ?
                         ORDER BY name ASC"
                    );
                    $category_search->execute([$like_query, $like_query]);
                }
            } else {
                if ($use_fulltext) {
                    $category_search = $pdo->prepare(
                        "SELECT id, name, icon, 'category' as type, visibility,
                                MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM categories
                         WHERE (visibility = 'public'
                                OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL)))
                         AND MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)
                         ORDER BY relevance DESC"
                    );
                    $category_search->execute([$search_query, '%"' . $current_user_id . '"%', $search_query]);
                } else {
                    $category_search = $pdo->prepare(
                        "SELECT id, name, icon, 'category' as type, visibility,
                                (CASE WHEN name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM categories
                         WHERE (visibility = 'public'
                                OR (visibility = 'restricted' AND (allowed_users LIKE ? OR allowed_users IS NULL)))
                         AND name LIKE ?
                         ORDER BY name ASC"
                    );
                    $category_search->execute([$like_query, '%"' . $current_user_id . '"%', $like_query]);
                }
            }
            $categories = $category_search->fetchAll();
        } else {
            $categories = [];
        }

        // Check subcategory visibility columns
        $subcategory_visibility_columns_exist = false;
        try {
            $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
            $subcategory_visibility_columns_exist = true;
        } catch (PDOException $e) {
            $subcategory_visibility_columns_exist = false;
        }

        // Subcategory search
        if ($search_in === 'all' || $search_in === 'subcategories') {
            if ($is_super_user) {
                if ($subcategory_visibility_columns_exist && $use_fulltext) {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                         GROUP BY s.id
                         ORDER BY relevance DESC"
                    );
                    $subcategory_search->execute([$search_query, $search_query]);
                } elseif ($subcategory_visibility_columns_exist) {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                (CASE WHEN s.name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE s.name LIKE ?
                         GROUP BY s.id
                         ORDER BY s.name ASC"
                    );
                    $subcategory_search->execute([$like_query, $like_query]);
                } else {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                (CASE WHEN s.name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE s.name LIKE ?
                         GROUP BY s.id
                         ORDER BY s.name ASC"
                    );
                    $subcategory_search->execute([$like_query, $like_query]);
                }
            } elseif (is_admin()) {
                if ($subcategory_visibility_columns_exist && $use_fulltext) {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE s.visibility != 'it_only' AND c.visibility != 'it_only'
                         AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                         GROUP BY s.id
                         ORDER BY relevance DESC"
                    );
                    $subcategory_search->execute([$search_query, $search_query]);
                } elseif ($subcategory_visibility_columns_exist) {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                (CASE WHEN s.name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE s.visibility != 'it_only' AND c.visibility != 'it_only'
                         AND s.name LIKE ?
                         GROUP BY s.id
                         ORDER BY s.name ASC"
                    );
                    $subcategory_search->execute([$like_query, $like_query]);
                } else {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                (CASE WHEN s.name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE s.name LIKE ?
                         GROUP BY s.id
                         ORDER BY s.name ASC"
                    );
                    $subcategory_search->execute([$like_query, $like_query]);
                }
            } else {
                if ($subcategory_visibility_columns_exist && $use_fulltext) {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND (s.visibility = 'public'
                              OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                         AND MATCH(s.name) AGAINST(? IN NATURAL LANGUAGE MODE)
                         GROUP BY s.id
                         ORDER BY relevance DESC"
                    );
                    $subcategory_search->execute([$search_query, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%', $search_query]);
                } elseif ($subcategory_visibility_columns_exist) {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                (CASE WHEN s.name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND (s.visibility = 'public'
                              OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                         AND s.name LIKE ?
                         GROUP BY s.id
                         ORDER BY s.name ASC"
                    );
                    $subcategory_search->execute([$like_query, '%"' . $current_user_id . '"%', '%"' . $current_user_id . '"%', $like_query]);
                } else {
                    $subcategory_search = $pdo->prepare(
                        "SELECT s.id, s.name, s.category_id, c.name as category_name, c.icon as category_icon,
                                'subcategory' as type, COUNT(DISTINCT p.id) as post_count,
                                (CASE WHEN s.name LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM subcategories s
                         JOIN categories c ON s.category_id = c.id
                         LEFT JOIN posts p ON s.id = p.subcategory_id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND s.name LIKE ?
                         GROUP BY s.id
                         ORDER BY s.name ASC"
                    );
                    $subcategory_search->execute([$like_query, '%"' . $current_user_id . '"%', $like_query]);
                }
            }
            $subcategories = $subcategory_search->fetchAll();
        } else {
            $subcategories = [];
        }

        // Posts
        if ($search_in === 'all' || $search_in === 'posts') {
            if ($is_super_user) {
                if ($use_fulltext) {
                    $post_search = $pdo->prepare(
                        "SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                                s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'post' as type,
                                (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                                MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) +
                                MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM posts p
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE)
                                OR MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                         ORDER BY relevance DESC, p.created_at DESC
                         LIMIT 50"
                    );
                    $post_search->execute([$search_query, $search_query, $search_query, $search_query, $search_query]);
                } else {
                    $post_search = $pdo->prepare(
                        "SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                                s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'post' as type,
                                (CASE WHEN p.title LIKE ? OR p.content LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM posts p
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE (p.title LIKE ? OR p.content LIKE ?)
                         ORDER BY relevance DESC, p.created_at DESC
                         LIMIT 50"
                    );
                    $post_search->execute([$like_query, $like_query, $like_query, $like_query]);
                }
                $posts = $post_search->fetchAll();
            } elseif (is_admin()) {
                if ($use_fulltext) {
                    $post_search = $pdo->prepare(
                        "SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                                s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'post' as type,
                                (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                                MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) +
                                MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM posts p
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE p.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only'
                         AND (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE)
                              OR MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                         ORDER BY relevance DESC, p.created_at DESC
                         LIMIT 50"
                    );
                    $post_search->execute([$search_query, $search_query, $search_query, $search_query, $search_query]);
                } else {
                    $post_search = $pdo->prepare(
                        "SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                                s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'post' as type,
                                (CASE WHEN p.title LIKE ? OR p.content LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM posts p
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE p.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only'
                         AND (p.title LIKE ? OR p.content LIKE ?)
                         ORDER BY relevance DESC, p.created_at DESC
                         LIMIT 50"
                    );
                    $post_search->execute([$like_query, $like_query, $like_query, $like_query]);
                }
                $posts = $post_search->fetchAll();
            } else {
                if ($subcategory_visibility_columns_exist && $use_fulltext) {
                    $post_search = $pdo->prepare(
                        "SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                                s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'post' as type,
                                (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                                MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE) +
                                MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM posts p
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND (s.visibility = 'public'
                              OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                         AND (p.privacy = 'public'
                              OR p.user_id = ?
                              OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                         AND (MATCH(p.title) AGAINST(? IN NATURAL LANGUAGE MODE)
                              OR MATCH(p.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                         ORDER BY relevance DESC, p.created_at DESC
                         LIMIT 50"
                    );
                    $post_search->execute([
                        $search_query, $search_query, $search_query,
                        '%"' . $current_user_id . '"%',
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $search_query, $search_query
                    ]);
                } elseif ($subcategory_visibility_columns_exist) {
                    $post_search = $pdo->prepare(
                        "SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                                s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'post' as type,
                                (CASE WHEN p.title LIKE ? OR p.content LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM posts p
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND (s.visibility = 'public'
                              OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                         AND (p.privacy = 'public'
                              OR p.user_id = ?
                              OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                         AND (p.title LIKE ? OR p.content LIKE ?)
                         ORDER BY relevance DESC, p.created_at DESC
                         LIMIT 50"
                    );
                    $post_search->execute([
                        $like_query, $like_query,
                        '%"' . $current_user_id . '"%',
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $like_query, $like_query
                    ]);
                } else {
                    $post_search = $pdo->prepare(
                        "SELECT p.id, p.title, p.subcategory_id, p.user_id, p.created_at, p.privacy,
                                s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'post' as type,
                                (CASE WHEN p.title LIKE ? OR p.content LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM posts p
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND (p.privacy = 'public'
                              OR p.user_id = ?
                              OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                         AND (p.title LIKE ? OR p.content LIKE ?)
                         ORDER BY relevance DESC, p.created_at DESC
                         LIMIT 50"
                    );
                    $post_search->execute([
                        $like_query, $like_query,
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $like_query, $like_query
                    ]);
                }
                $posts = $post_search->fetchAll();
            }
        } else {
            $posts = [];
        }

        // Replies
        if ($search_in === 'all' || $search_in === 'replies') {
            if ($is_super_user) {
                if ($use_fulltext) {
                    $reply_search = $pdo->prepare(
                        "SELECT r.id, r.post_id, r.user_id, r.created_at, r.content,
                                p.title as post_title, s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'reply' as type,
                                (MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                                MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM replies r
                         JOIN posts p ON r.post_id = p.id
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE)
                         ORDER BY relevance DESC, r.created_at DESC
                         LIMIT 50"
                    );
                    $reply_search->execute([$search_query, $search_query]);
                } else {
                    $reply_search = $pdo->prepare(
                        "SELECT r.id, r.post_id, r.user_id, r.created_at, r.content,
                                p.title as post_title, s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'reply' as type,
                                (CASE WHEN r.content LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM replies r
                         JOIN posts p ON r.post_id = p.id
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE r.content LIKE ?
                         ORDER BY r.created_at DESC
                         LIMIT 50"
                    );
                    $reply_search->execute([$like_query, $like_query]);
                }
                $replies = $reply_search->fetchAll();
            } elseif (is_admin()) {
                if ($use_fulltext) {
                    $reply_search = $pdo->prepare(
                        "SELECT r.id, r.post_id, r.user_id, r.created_at, r.content,
                                p.title as post_title, s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'reply' as type,
                                (MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                                MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM replies r
                         JOIN posts p ON r.post_id = p.id
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE r.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only'
                         AND MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE)
                         ORDER BY relevance DESC, r.created_at DESC
                         LIMIT 50"
                    );
                    $reply_search->execute([$search_query, $search_query]);
                } else {
                    $reply_search = $pdo->prepare(
                        "SELECT r.id, r.post_id, r.user_id, r.created_at, r.content,
                                p.title as post_title, s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'reply' as type,
                                (CASE WHEN r.content LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM replies r
                         JOIN posts p ON r.post_id = p.id
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE r.privacy != 'it_only' AND s.visibility != 'it_only' AND c.visibility != 'it_only'
                         AND r.content LIKE ?
                         ORDER BY r.created_at DESC
                         LIMIT 50"
                    );
                    $reply_search->execute([$like_query, $like_query]);
                }
                $replies = $reply_search->fetchAll();
            } else {
                if ($subcategory_visibility_columns_exist && $use_fulltext) {
                    $reply_search = $pdo->prepare(
                        "SELECT r.id, r.post_id, r.user_id, r.created_at, r.content,
                                p.title as post_title, s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'reply' as type,
                                (MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) * 2) +
                                MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                         FROM replies r
                         JOIN posts p ON r.post_id = p.id
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND (s.visibility = 'public'
                              OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                         AND (p.privacy = 'public'
                              OR p.user_id = ?
                              OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                         AND (r.privacy IS NULL OR r.privacy = 'public'
                              OR r.user_id = ?
                              OR (r.privacy = 'shared' AND r.shared_with LIKE ?))
                         AND (MATCH(r.content) AGAINST(? IN NATURAL LANGUAGE MODE))
                         ORDER BY relevance DESC, r.created_at DESC
                         LIMIT 50"
                    );
                    $reply_search->execute([
                        $search_query,
                        '%"' . $current_user_id . '"%',
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $search_query
                    ]);
                } elseif ($subcategory_visibility_columns_exist) {
                    $reply_search = $pdo->prepare(
                        "SELECT r.id, r.post_id, r.user_id, r.created_at, r.content,
                                p.title as post_title, s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'reply' as type,
                                (CASE WHEN r.content LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM replies r
                         JOIN posts p ON r.post_id = p.id
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND (s.visibility = 'public'
                              OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL)))
                         AND (p.privacy = 'public'
                              OR p.user_id = ?
                              OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                         AND (r.privacy IS NULL OR r.privacy = 'public'
                              OR r.user_id = ?
                              OR (r.privacy = 'shared' AND r.shared_with LIKE ?))
                         AND r.content LIKE ?
                         ORDER BY r.created_at DESC
                         LIMIT 50"
                    );
                    $reply_search->execute([
                        $like_query,
                        '%"' . $current_user_id . '"%',
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $like_query
                    ]);
                } else {
                    $reply_search = $pdo->prepare(
                        "SELECT r.id, r.post_id, r.user_id, r.created_at, r.content,
                                p.title as post_title, s.name as subcategory_name, c.name as category_name, c.icon as category_icon,
                                'reply' as type,
                                (CASE WHEN r.content LIKE ? THEN 1 ELSE 0 END) as relevance
                         FROM replies r
                         JOIN posts p ON r.post_id = p.id
                         JOIN subcategories s ON p.subcategory_id = s.id
                         JOIN categories c ON s.category_id = c.id
                         WHERE (c.visibility = 'public'
                                OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL)))
                         AND (p.privacy = 'public'
                              OR p.user_id = ?
                              OR (p.privacy = 'shared' AND p.shared_with LIKE ?))
                         AND (r.privacy IS NULL OR r.privacy = 'public'
                              OR r.user_id = ?
                              OR (r.privacy = 'shared' AND r.shared_with LIKE ?))
                         AND r.content LIKE ?
                         ORDER BY r.created_at DESC
                         LIMIT 50"
                    );
                    $reply_search->execute([
                        $like_query,
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $current_user_id,
                        '%"' . $current_user_id . '"%',
                        $like_query
                    ]);
                }
                $replies = $reply_search->fetchAll();
            }
        } else {
            $replies = [];
        }

        $all_results = array_merge($categories, $subcategories, $posts, $replies);
        usort($all_results, function ($a, $b) {
            $relevance_a = $a['relevance'] ?? 0;
            $relevance_b = $b['relevance'] ?? 0;
            if ($relevance_a == $relevance_b) {
                return 0;
            }
            return ($relevance_a > $relevance_b) ? -1 : 1;
        });

        $results = $all_results;
    } catch (PDOException $e) {
        error_log('Mobile Search Error: ' . $e->getMessage());
        $error_message = 'Search error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME); ?></title>
    <link rel="preload" href="/assets/css/style.css?v=20251121" as="style">
    <link rel="stylesheet" href="/assets/css/style.css?v=20251121" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="/assets/css/style.css?v=20251121"></noscript>
    <style>
        body.mobile-body {
            background: #f7fafc;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .mobile-shell {
            max-width: 900px;
            margin: 0 auto;
            padding: 16px 16px 90px;
        }
        .mobile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .mobile-hero {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 10px 24px rgba(102, 126, 234, 0.18);
            margin-bottom: 14px;
        }
        .mobile-hero h1 {
            margin: 0 0 6px;
            font-size: 20px;
        }
        .mobile-hero p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .result-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .result-card {
            background: #fff;
            border-radius: 14px;
            padding: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }
        .result-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: #1a202c;
            background: #edf2f7;
            text-transform: capitalize;
            margin-bottom: 8px;
        }
        .result-title {
            margin: 0 0 6px;
            font-size: 16px;
            color: #1a202c;
        }
        .result-meta {
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #4a5568;
        }
        .back-link {
            color: #4c51bf;
            text-decoration: none;
            font-weight: 700;
        }
        @media (max-width: 640px) {
            .mobile-shell { padding: 12px 12px 90px; }
        }
    </style>
</head>
<body class="mobile-body">
<div class="mobile-shell">
    <div class="mobile-header">
        <a class="back-link" href="/mobile/index.php">← Back</a>
        <span style="color:#718096; font-weight:600;">Search</span>
    </div>

    <div class="mobile-hero">
        <h1>Find answers faster</h1>
        <p>Search categories, subcategories, posts, and replies.</p>
    </div>

    <?php if (function_exists('render_search_bar')) { render_search_bar('/mobile/search.php', '/mobile/search_autocomplete.php'); } ?>

    <?php if ($error_message): ?>
        <div class="result-card" style="border-left: 4px solid #f6ad55;"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif (empty($search_query)): ?>
        <div class="empty-state">
            <div style="font-size: 28px; margin-bottom: 8px;">🔍</div>
            <div>Enter a search term to see results.</div>
        </div>
    <?php elseif ($search_performed && empty($results)): ?>
        <div class="empty-state">
            <div style="font-size: 28px; margin-bottom: 8px;">🤔</div>
            <div>No results found for "<?php echo htmlspecialchars($search_query); ?>".</div>
        </div>
    <?php else: ?>
        <div style="margin: 8px 0 12px; color: #4a5568; font-size: 14px;">
            Showing <?php echo count($results); ?> result<?php echo count($results) === 1 ? '' : 's'; ?> for "<?php echo htmlspecialchars($search_query); ?>".
        </div>
        <div class="result-list">
            <?php foreach ($results as $result): ?>
                <div class="result-card">
                    <div class="result-type">
                        <?php if ($result['type'] === 'category'): ?>📁<?php elseif ($result['type'] === 'subcategory'): ?>📂<?php elseif ($result['type'] === 'post'): ?>📄<?php else: ?>💬<?php endif; ?>
                        <span><?php echo htmlspecialchars($result['type']); ?></span>
                    </div>
                    <h3 class="result-title">
                        <?php if ($result['type'] === 'category'): ?>
                            <?php echo htmlspecialchars($result['name']); ?>
                        <?php elseif ($result['type'] === 'subcategory'): ?>
                            <?php echo htmlspecialchars($result['name']); ?>
                        <?php elseif ($result['type'] === 'post'): ?>
                            <?php echo htmlspecialchars($result['title']); ?>
                        <?php else: ?>
                            Reply in <?php echo htmlspecialchars($result['post_title']); ?>
                        <?php endif; ?>
                    </h3>
                    <div class="result-meta">
                        <?php if ($result['type'] === 'category'): ?>
                            Category
                        <?php elseif ($result['type'] === 'subcategory'): ?>
                            In <?php echo htmlspecialchars($result['category_name']); ?> · <?php echo $result['post_count']; ?> post(s)
                        <?php elseif ($result['type'] === 'post'): ?>
                            In <?php echo htmlspecialchars($result['category_name']); ?> / <?php echo htmlspecialchars($result['subcategory_name']); ?> · <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                        <?php else: ?>
                            In <?php echo htmlspecialchars($result['category_name']); ?> / <?php echo htmlspecialchars($result['subcategory_name']); ?> · <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <?php if ($result['type'] === 'category'): ?>
                            <a class="btn btn-primary btn-small" href="/index.php">Open category</a>
                        <?php elseif ($result['type'] === 'subcategory'): ?>
                            <a class="btn btn-primary btn-small" href="/categories/subcategory.php?id=<?php echo $result['id']; ?>">View subcategory</a>
                        <?php elseif ($result['type'] === 'post'): ?>
                            <a class="btn btn-primary btn-small" href="/posts/post.php?id=<?php echo $result['id']; ?>">Read post</a>
                        <?php else: ?>
                            <a class="btn btn-primary btn-small" href="/posts/post.php?id=<?php echo $result['post_id']; ?>#reply-<?php echo $result['id']; ?>">View reply</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/mobile_nav.php'; ?>
</body>
</html>
