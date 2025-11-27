<?php
/**
 * Mobile Post Detail
 * Slimmed mobile view with inline PDF previews.
 */
$login_path = '/mobile/login.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/department_helpers.php';
require_once __DIR__ . '/../includes/mobile_beta_gate.php';
require_once __DIR__ . '/../includes/pdf_extraction.php';

// Load training helpers when available
if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

enforce_mobile_beta_access();

/**
 * Backfill extracted PDF content for legacy uploads missing HTML/images.
 */
function ensure_pdf_extracted(PDO $pdo, array $file): array
{
    $has_extracted_html = trim($file['extracted_html'] ?? '') !== '';
    $has_extracted_images = !empty($file['extracted_images_json']);

    if ($has_extracted_html || $has_extracted_images) {
        return $file;
    }

    $file_type = $file['file_type'] ?? '';
    $original_filename = $file['original_filename'] ?? '';

    if (!is_pdf_upload($file_type, $original_filename)) {
        return $file;
    }

    $relative_path = $file['file_path'] ?? '';
    $stored_filename = $file['stored_filename'] ?? basename($relative_path);

    $extracted = extract_pdf_content($file_type, $original_filename, $relative_path, $stored_filename);

    if (empty($extracted['extracted_html']) && empty($extracted['extracted_images_json'])) {
        return $file;
    }

    $file['extracted_html'] = $extracted['extracted_html'];
    $file['extracted_images_json'] = $extracted['extracted_images_json'];

    try {
        $update = $pdo->prepare("UPDATE files SET extracted_html = ?, extracted_images_json = ? WHERE id = ?");
        $update->execute([
            $file['extracted_html'],
            $file['extracted_images_json'],
            intval($file['id'] ?? 0),
        ]);
    } catch (PDOException $e) {
        error_log('Failed to backfill PDF extraction for file ID ' . ($file['id'] ?? 'unknown') . ': ' . $e->getMessage());
    }

    return $file;
}

// Get post ID first (needed for training progress tracking)
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_role = $_SESSION['user_role'] ?? 'guest';
$debug_reference = null;
$debug_payload = [];
$has_training_access = false;

// Add test debug log entry
if (function_exists('log_debug')) {
    log_debug("mobile/post.php accessed - Post ID: $post_id, User ID: " . ($_SESSION['user_id'] ?? 'none') . ", Time: " . date('Y-m-d H:i:s'));
} else {
    error_log("mobile/post.php accessed - Post ID: $post_id, User ID: " . ($_SESSION['user_id'] ?? 'none') . ", Time: " . date('Y-m-d H:i:s'));
}

// Check for quiz availability and store result for later display
$quiz_banner_html = '';
if (function_exists('is_training_user') && is_training_user() && $post_id > 0) {
    try {
        if (function_exists('log_debug')) {
            log_debug("Quiz detection (mobile) - Post ID: $post_id, User ID: " . $_SESSION['user_id']);
        }

        $quiz_check = $pdo->prepare("SELECT id, quiz_title, content_type FROM training_quizzes WHERE content_id = ? AND is_active = TRUE AND (content_type = 'post' OR content_type = '' OR content_type IS NULL)");
        $quiz_check->execute([$post_id]);
        $available_quizzes = $quiz_check->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('log_debug')) {
            log_debug("Available quizzes for post $post_id: " . json_encode($available_quizzes));
        }

        $assignment_check = $pdo->prepare(
            "SELECT uta.id, uta.course_id, tc.name as course_name
             FROM user_training_assignments uta
             JOIN training_courses tc ON uta.course_id = tc.id
             JOIN training_course_content tcc ON uta.course_id = tcc.course_id
             WHERE uta.user_id = ?
               AND tcc.content_type = 'post'
               AND tcc.content_id = ?
               AND uta.status != 'completed'
               AND tc.is_active = 1"
        );
        $assignment_check->execute([$_SESSION['user_id'], $post_id]);
        $user_assignments = $assignment_check->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('log_debug')) {
            log_debug("User assignments for post $post_id: " . json_encode($user_assignments));
        }

        $stmt = $pdo->prepare(
            "SELECT tq.id as quiz_id, tq.quiz_title, tp.quiz_completed, tp.last_quiz_attempt_id,
                    CASE WHEN uta.user_id IS NOT NULL THEN 'assigned' ELSE 'unassigned' END as training_status
             FROM training_quizzes tq
             LEFT JOIN training_progress tp ON tq.content_id = ? AND (tq.content_type = 'post' OR tq.content_type = '' OR tq.content_type IS NULL)
                 AND tp.user_id = ? AND (tp.content_type = 'post' OR tp.content_type = '' OR tp.content_type IS NULL) AND tp.content_id = ?
             LEFT JOIN user_training_assignments uta ON uta.course_id = (
                 SELECT tcc.course_id FROM training_course_content tcc
                 WHERE tcc.content_type = 'post' AND tcc.content_id = ?
                 LIMIT 1
             ) AND uta.user_id = ? AND uta.status != 'completed'
             WHERE tq.content_id = ? AND (tq.content_type = 'post' OR tq.content_type = '' OR tq.content_type IS NULL) AND tq.is_active = TRUE
             LIMIT 1"
        );
        $stmt->execute([$post_id, $_SESSION['user_id'], $post_id, $post_id, $_SESSION['user_id'], $post_id]);
        $training_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (function_exists('log_debug')) {
            log_debug("Final training data result: " . json_encode($training_data));
        }

        if ($training_data && $training_data['quiz_id']) {
            $stmt = $pdo->prepare(
                "INSERT INTO training_progress (user_id, course_id, content_type, content_id, status)
                 VALUES (?, 0, 'post', ?, 'in_progress')
                 ON DUPLICATE KEY UPDATE
                 status = 'in_progress',
                 updated_at = NOW()"
            );
            $stmt->execute([$_SESSION['user_id'], $post_id]);

            if ($training_data['training_status'] === 'assigned') {
                $quiz_completed = $training_data['quiz_completed'] ?? false;
                $quiz_url = "/training/take_quiz.php?quiz_id=" . $training_data['quiz_id'] . "&content_type=post&content_id=" . $post_id;

                if ($quiz_completed) {
                    $quiz_banner_html .= "<div class='training-quiz-banner' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px; border-radius: 12px; margin: 12px 0; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15);'>";
                    $quiz_banner_html .= "<div style='display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap;'>";
                    $quiz_banner_html .= "<span style='font-size: 22px;'>✅</span>";
                    $quiz_banner_html .= "<div><h3 style='margin:0 0 4px 0; font-size:16px;'>Quiz Completed!</h3><p style='margin:0; opacity:0.9;'>You have successfully completed the quiz for this content.</p></div>";
                    $quiz_banner_html .= "<a href='/mobile/quiz_results.php?attempt_id=" . ($training_data['last_quiz_attempt_id'] ?? '') . "' class='btn' style='background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding:8px 12px; border-radius:10px;'>View Results</a>";
                    $quiz_banner_html .= "</div></div>";
                } else {
                    $quiz_banner_html .= "<div class='training-quiz-banner' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px; border-radius: 12px; margin: 12px 0; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15);'>";
                    $quiz_banner_html .= "<div style='display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap;'>";
                    $quiz_banner_html .= "<span style='font-size: 22px;'>📝</span>";
                    $quiz_banner_html .= "<div><h3 style='margin:0 0 4px 0; font-size:16px;'>Quiz Available: " . htmlspecialchars($training_data['quiz_title']) . "</h3><p style='margin:0; opacity:0.9;'>After reading this content, take the quiz to mark it as complete.</p></div>";
                    $quiz_banner_html .= "<a href='" . $quiz_url . "' class='btn' style='background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding:8px 12px; border-radius:10px;'>Take Quiz</a>";
                    $quiz_banner_html .= "</div></div>";
                }
            } else {
                $quiz_banner_html .= "<div class='training-quiz-banner' style='background: #17a2b8; color: white; padding: 12px; border-radius: 10px; margin: 12px 0; text-align: center;'>";
                $quiz_banner_html .= "<p style='margin: 0;'><strong>📝 Quiz Available:</strong> This content has a quiz, but it's not currently assigned to your training.</p>";
                $quiz_banner_html .= "</div>";
            }
        } else {
            $quiz_banner_html .= "<div class='training-quiz-banner' style='background: #6c757d; color: white; padding: 12px; border-radius: 10px; margin: 12px 0; text-align: center;'>";
            $quiz_banner_html .= "<p style='margin: 0;'><strong>📚 Training Content:</strong> This is part of your training materials. No quiz is available for this content yet.</p>";
            $quiz_banner_html .= "</div>";
        }
    } catch (PDOException $e) {
        error_log("Error checking training quiz availability (mobile): " . $e->getMessage());
    }
}

$error_message = '';
$post = null;
$files = [];
$replies = [];

if ($post_id <= 0) {
    header('Location: /mobile/categories.php');
    exit;
}

$subcategory_visibility_columns_exist = false;
$category_visibility_columns_exist = false;
$subcategory_departments_supported = false;
$category_departments_supported = false;
$shared_departments_supported = false;
$user_department_ids = [];
try {
    $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
    $subcategory_visibility_columns_exist = true;
} catch (PDOException $e) {}

try {
    $pdo->query("SELECT visibility FROM categories LIMIT 1");
    $category_visibility_columns_exist = true;
} catch (PDOException $e) {}

try {
    $pdo->query("SELECT allowed_departments FROM subcategories LIMIT 1");
    $subcategory_departments_supported = true;
} catch (PDOException $e) {}

try {
    $pdo->query("SELECT allowed_departments FROM categories LIMIT 1");
    $category_departments_supported = true;
} catch (PDOException $e) {}

try {
    $pdo->query("SELECT shared_departments FROM posts LIMIT 1");
    $shared_departments_supported = true;
} catch (PDOException $e) {}

if ($subcategory_departments_supported || $category_departments_supported || $shared_departments_supported) {
    $user_departments = get_user_departments($pdo, $_SESSION['user_id']);
    $user_department_ids = array_map('intval', array_column($user_departments, 'id'));
}

// Fetch post with subcategory and category info and visibility checks
try {
    $is_super_user = is_super_admin();
    $is_admin = is_admin();

    if (function_exists('is_in_training') && is_in_training() && function_exists('is_assigned_training_content')) {
        $has_training_access = is_assigned_training_content($pdo, $current_user_id, $post_id, 'post');
    }

    if ($is_super_user && $subcategory_visibility_columns_exist) {
        $stmt = $pdo->prepare(
            "SELECT
                p.*,
                s.name AS subcategory_name,
                s.id AS subcategory_id,
                s.visibility AS subcategory_visibility,
                s.allowed_users AS subcategory_allowed_users,
                s.allowed_departments AS subcategory_allowed_departments,
                c.name AS category_name,
                c.visibility AS category_visibility,
                c.allowed_users AS category_allowed_users,
                c.allowed_departments AS category_allowed_departments
            FROM posts p
            JOIN subcategories s ON p.subcategory_id = s.id
            JOIN categories c ON s.category_id = c.id
            WHERE p.id = ?"
        );
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
    } elseif ($is_admin && $subcategory_visibility_columns_exist) {
        $stmt = $pdo->prepare(
            "SELECT
                p.*,
                s.name AS subcategory_name,
                s.id AS subcategory_id,
                s.visibility AS subcategory_visibility,
                s.allowed_users AS subcategory_allowed_users,
                s.allowed_departments AS subcategory_allowed_departments,
                c.name AS category_name,
                c.visibility AS category_visibility,
                c.allowed_users AS category_allowed_users,
                c.allowed_departments AS category_allowed_departments
            FROM posts p
            JOIN subcategories s ON p.subcategory_id = s.id
            JOIN categories c ON s.category_id = c.id
            WHERE p.id = ?
            AND s.visibility != 'it_only'
            AND c.visibility != 'it_only'"
        );
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
    } elseif ($subcategory_visibility_columns_exist && $category_visibility_columns_exist) {
        $params = [$post_id];

        $categoryVisibilityClause = "(c.visibility = 'public'"
            . " OR (c.visibility = 'restricted' AND (c.allowed_users LIKE ? OR c.allowed_users IS NULL))"
            . " OR (c.visibility = 'restricted' AND c.allowed_departments IS NULL))";
        $subcategoryVisibilityClause = "(s.visibility = 'public'"
            . " OR (s.visibility = 'restricted' AND (s.allowed_users LIKE ? OR s.allowed_users IS NULL))"
            . " OR (s.visibility = 'restricted' AND s.allowed_departments IS NULL))";

        $visibilityClause = "{$categoryVisibilityClause} AND {$subcategoryVisibilityClause}";

        $privacyClause = "(p.privacy = 'public'"
            . " OR p.user_id = ?"
            . " OR (p.privacy = 'shared' AND p.shared_with LIKE ?)"
            . " OR (p.privacy = 'shared' AND p.shared_with IS NULL AND p.shared_departments IS NULL)";
        if ($shared_departments_supported) {
            $privacyClause .= " OR (p.shared_departments IS NOT NULL AND p.shared_departments != '')";
        }
        $privacyClause .= ')';

        $params[] = '%"' . $current_user_id . '"%';
        $params[] = '%"' . $current_user_id . '"%';
        $params[] = $current_user_id;
        $params[] = '%"' . $current_user_id . '"%';

        $orConditions = [];
        $orConditions[] = "({$visibilityClause} AND {$privacyClause})";

        if ($has_training_access) {
            $orConditions[] = "EXISTS (
                SELECT 1
                FROM training_course_content tcc
                JOIN user_training_assignments uta ON tcc.course_id = uta.course_id
                WHERE uta.user_id = ?
                  AND uta.status != 'completed'
                  AND tcc.content_type = 'post'
                  AND tcc.content_id = p.id
            )";
            $params[] = $current_user_id;
        }

        $combinedClause = implode(' OR ', array_map(function ($clause) {
            return "({$clause})";
        }, $orConditions));

        $stmt = $pdo->prepare(
            "SELECT
                p.*,
                s.name AS subcategory_name,
                s.id AS subcategory_id,
                s.visibility AS subcategory_visibility,
                s.allowed_users AS subcategory_allowed_users,
                s.allowed_departments AS subcategory_allowed_departments,
                c.name AS category_name,
                c.visibility AS category_visibility,
                c.allowed_users AS category_allowed_users,
                c.allowed_departments AS category_allowed_departments
            FROM posts p
            JOIN subcategories s ON p.subcategory_id = s.id
            JOIN categories c ON s.category_id = c.id
            WHERE p.id = ?
              AND ({$combinedClause})"
        );
        $stmt->execute($params);
        $post = $stmt->fetch();

        if ($post && $shared_departments_supported && !empty($post['shared_departments'])) {
            $shared_departments = json_decode($post['shared_departments'], true);
            $shared_department_ids = array_map('intval', $shared_departments ?? []);
            $has_shared_access = array_intersect($user_department_ids, $shared_department_ids);
            if (empty($has_shared_access) && $post['privacy'] === 'shared') {
                $post = null;
            }
        }
    } else {
        $trainingAccessClause = '';
        $params = [$post_id, $current_user_id, '%"' . $current_user_id . '"%'];

        if ($has_training_access) {
            $trainingAccessClause = " OR EXISTS (
                SELECT 1
                FROM training_course_content tcc
                JOIN user_training_assignments uta ON tcc.course_id = uta.course_id
                WHERE uta.user_id = ?
                  AND uta.status != 'completed'
                  AND tcc.content_type = 'post'
                  AND tcc.content_id = p.id
            )";
            $params[] = $current_user_id;
        }

        $stmt = $pdo->prepare(
            "SELECT
                p.*,
                s.name AS subcategory_name,
                s.id AS subcategory_id,
                c.name AS category_name
            FROM posts p
            JOIN subcategories s ON p.subcategory_id = s.id
            JOIN categories c ON s.category_id = c.id
            WHERE p.id = ?
            AND ((p.privacy = 'public'
                 OR p.user_id = ?
                 OR (p.privacy = 'shared' AND p.shared_with LIKE ?)){$trainingAccessClause})"
        );
        $stmt->execute($params);
        $post = $stmt->fetch();
    }

    if (!$post) {
        $error_message = 'Post not found or you do not have permission to access it.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM files WHERE post_id = ? ORDER BY uploaded_at ASC");
        $stmt->execute([$post_id]);
        $files = $stmt->fetchAll();

        foreach ($files as &$file) {
            if (isset($file['file_path'])) {
                $file['file_path'] = normalize_file_path($file['file_path']);
            }
            $file = ensure_pdf_extracted($pdo, $file);
        }
        unset($file);

        $stmt = $pdo->prepare("SELECT * FROM replies WHERE post_id = ? ORDER BY created_at ASC");
        $stmt->execute([$post_id]);
        $replies = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $debug_reference = uniqid('mob_post_', true);
    $debug_payload = [
        'reference' => $debug_reference,
        'post_id' => $post_id,
        'user_id' => $current_user_id,
        'user_role' => $current_user_role,
        'has_training_access' => $has_training_access,
        'visibility_columns' => [
            'subcategory_visibility' => $subcategory_visibility_columns_exist,
            'category_visibility' => $category_visibility_columns_exist,
            'subcategory_departments' => $subcategory_departments_supported,
            'category_departments' => $category_departments_supported,
            'shared_departments' => $shared_departments_supported,
        ],
        'exception' => $e->getMessage(),
        'timestamp' => date('c'),
    ];

    if (function_exists('log_debug')) {
        log_debug('Mobile post DB Error [' . $debug_reference . ']: ' . json_encode($debug_payload));
    } else {
        error_log('Mobile post DB Error [' . $debug_reference . ']: ' . json_encode($debug_payload));
    }

    $error_message = "Database error occurred. Please try again. (Ref: {$debug_reference})";
}

function format_timestamp($timestamp) {
    return date('M j, Y \a\t g:i A', strtotime($timestamp));
}

function is_edited($created_at, $updated_at) {
    return strtotime($updated_at) > (strtotime($created_at) + 60);
}

function format_filesize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

function is_image($file_path) {
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($ext, IMAGE_EXTENSIONS);
}

function normalize_file_path($path) {
    if (empty($path)) {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return '/' . ltrim($path, '/');
}

$page_title = $post ? htmlspecialchars($post['title']) : 'Post';
$mobile_active_page = 'categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=20251121">
    <link rel="stylesheet" href="/assets/css/style.css?v=20260205">
    <style>
        body.mobile-body { background: #f7fafc; padding: 0; margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .mobile-shell { max-width: 960px; margin: 0 auto; padding: 16px 16px 90px; }
        .mobile-hero { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 18px; padding: 20px; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.22); margin-bottom: 16px; }
        .mobile-hero h1 { margin: 0 0 6px; font-size: 22px; font-weight: 700; }
        .mobile-hero p { margin: 0; opacity: 0.92; font-size: 14px; }
        .mobile-card { background: white; border-radius: 14px; padding: 14px; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0; margin-bottom: 14px; }
        .post-body { font-size: 16px; line-height: 1.6; color: #2d3748; }
        .attachment-group { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #f8fafc; margin-bottom: 10px; }
        .attachments-title { font-weight: 800; font-size: 16px; margin: 0 0 8px; }
        .attachment-file { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .attachment-file .file-info { display: flex; flex-direction: column; }
        .reply-bubble { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #fff; margin-bottom: 10px; }
        .reply-meta { font-size: 12px; color: #718096; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .empty-state { padding: 20px; text-align: center; color: #718096; background: #fff; border: 1px dashed #cbd5e0; border-radius: 12px; }
        .training-pdf-body { font-size: 15px; line-height: 1.6; color: #2d3748; }
        .training-pdf-body p { margin: 0 0 12px; }
        .pdf-scroll-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; max-height: 420px; overflow-y: auto; padding: 10px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.04); }
        .pdf-scroll-box img { display: block; width: 100%; margin-bottom: 10px; border-radius: 10px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
        .pdf-scroll-hint { font-size: 12px; color: #718096; margin: 6px 0 10px; display: flex; align-items: center; gap: 6px; }
        .pdf-fallback-message { padding: 12px; background: #fff; border: 1px dashed #e2e8f0; border-radius: 10px; color: #4a5568; }
    </style>
</head>
<body class="mobile-body">
    <div class="mobile-shell">
        <div class="mobile-hero">
            <h1><?php echo $post ? htmlspecialchars($post['title']) : 'Post'; ?></h1>
            <p><?php echo $post ? htmlspecialchars($post['category_name'] . ' / ' . $post['subcategory_name']) : 'Post detail'; ?></p>
        </div>

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
            <a href="/mobile/subcategory.php?id=<?php echo $post['subcategory_id'] ?? 0; ?>" style="text-decoration:none; color:#4c51bf; font-weight:700;">← Back to posts</a>
            <a href="/mobile/categories.php" style="text-decoration:none; color:#4c51bf; font-weight:700;">Categories</a>
        </div>

        <?php if ($error_message): ?>
            <div class="mobile-card" style="border-color:#fed7d7; background:#fff5f5; color:#c53030;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php if (!empty($debug_payload)): ?>
                <div class="mobile-card" style="border-color:#fbd38d; background:#fffaf0; color:#744210;">
                    <details>
                        <summary style="font-weight:700; cursor:pointer;">Debug details (safe to share with support)</summary>
                        <pre style="white-space:pre-wrap; word-break:break-word; margin-top:8px;">
<?php echo htmlspecialchars(json_encode($debug_payload, JSON_PRETTY_PRINT)); ?>
                        </pre>
                    </details>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="mobile-card">
                <div class="post-body"><?php echo $post['content']; ?></div>
                <div style="margin-top:12px; font-size:12px; color:#718096;">Posted on <?php echo format_timestamp($post['created_at']); ?></div>
                <?php echo $quiz_banner_html; ?>
            </div>

            <?php
            $has_file_type_category = false;
            try {
                $pdo->query("SELECT file_type_category FROM files LIMIT 1");
                $has_file_type_category = true;
            } catch (PDOException $e) {
                $has_file_type_category = false;
            }
            $preview_files = $has_file_type_category
                ? array_filter($files, function($f) { return isset($f['file_type_category']) && $f['file_type_category'] === 'preview'; })
                : array_filter($files, function($f) { return strtolower(pathinfo($f['original_filename'], PATHINFO_EXTENSION)) === 'pdf'; });
            ?>

            <?php if (!empty($preview_files)): ?>
                <div class="mobile-card">
                    <h3 class="attachments-title">📄 Document Preview</h3>
                    <?php foreach ($preview_files as $file): ?>
                        <?php
                            $file_ext = strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION));
                            $is_pdf = ($file_ext === 'pdf');
                            $initial_display = $is_pdf ? 'block' : 'none';
                            $initial_arrow   = $is_pdf ? '▲' : '▼';

                            $image_urls = [];
                            if (!empty($file['extracted_images_json'])) {
                                $decoded = json_decode($file['extracted_images_json'], true);
                                if (is_array($decoded)) {
                                    $image_urls = array_map(function($src) {
                                        return normalize_file_path($src);
                                    }, $decoded);
                                }
                            }
                            $has_extracted_images = !empty($image_urls);
                        ?>
                        <div class="attachment-group">
                            <div class="preview-file-header" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="togglePreview('preview_<?php echo $file['id']; ?>')">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span>📄</span>
                                    <div>
                                        <div style="font-weight:600;">Document preview</div>
                                        <div style="font-size:12px; color:#718096;">Tap to expand or collapse</div>
                                    </div>
                                </div>
                                <span id="preview_<?php echo $file['id']; ?>_arrow" style="font-size:14px; color:#666;"><?php echo $initial_arrow; ?></span>
                            </div>
                            <div id="preview_<?php echo $file['id']; ?>_content" style="display: <?php echo $initial_display; ?>; margin-top:10px;">
                                <?php if ($is_pdf): ?>
                                    <?php if ($has_extracted_images): ?>
                                        <div class="pdf-scroll-hint">⬆️⬇️ Scroll to view all pages</div>
                                        <div class="pdf-scroll-box">
                                            <?php foreach ($image_urls as $img): ?>
                                                <img src="<?php echo htmlspecialchars($img); ?>" alt="PDF page preview">
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="margin-top:8px;">
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" rel="noopener" style="font-weight:700; color:#4c51bf; text-decoration:none;">Open PDF</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="pdf-fallback-message">Preview unavailable. Please download and open the PDF.</div>
                                        <div style="margin-top:8px;">
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" rel="noopener" style="font-weight:700; color:#4c51bf; text-decoration:none;">Download PDF</a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="padding: 12px; background:#fff; border:1px solid #e2e8f0; border-radius:10px;">This file type cannot be previewed inline. Please download to view.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            $download_files = array_filter($files, function($f) use ($has_file_type_category) {
                if ($has_file_type_category) {
                    return !isset($f['file_type_category']) || $f['file_type_category'] !== 'preview';
                }
                return !is_image($f['file_path']);
            });
            $download_files = array_filter($download_files, function($f) {
                if (function_exists('is_training_user') && is_training_user()) {
                    $ext = strtolower(pathinfo($f['original_filename'] ?? '', PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        return false;
                    }
                }
                return true;
            });
            ?>
            <?php if (!empty($download_files)): ?>
                <div class="mobile-card">
                    <h3 class="attachments-title">📎 Files for Download</h3>
                    <?php foreach ($download_files as $file): ?>
                        <?php
                            $download_ext = strtolower(pathinfo($file['original_filename'] ?? '', PATHINFO_EXTENSION));
                            $force_download = $download_ext !== 'pdf';
                        ?>
                        <div class="attachment-file">
                            <span>📄</span>
                            <div class="file-info">
                                <a href="<?php echo htmlspecialchars($file['file_path']); ?>" <?php echo $force_download ? 'download' : ''; ?>><?php echo htmlspecialchars($file['original_filename']); ?></a>
                                <small style="color:#718096;"><?php echo format_filesize($file['file_size']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            $images = array_filter($files, function($f) { return is_image($f['file_path']); });
            ?>
            <?php if (!empty($images)): ?>
                <div class="mobile-card">
                    <h3 class="attachments-title">🖼️ Images</h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px,1fr)); gap:10px;">
                        <?php foreach ($images as $file): ?>
                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                <img src="<?php echo htmlspecialchars($file['file_path']); ?>" alt="<?php echo htmlspecialchars($file['original_filename']); ?>" style="width:100%; border-radius:10px;">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mobile-card">
                <h3 class="attachments-title">Updates (<?php echo count($replies); ?>)</h3>
                <?php if (empty($replies)): ?>
                    <div class="empty-state">No updates yet.</div>
                <?php else: ?>
                    <?php foreach ($replies as $reply): ?>
                        <?php
                            $stmt = $pdo->prepare("SELECT * FROM files WHERE reply_id = ? ORDER BY uploaded_at ASC");
                            $stmt->execute([$reply['id']]);
                            $reply_files = $stmt->fetchAll();
                            foreach ($reply_files as &$file) {
                                if (isset($file['file_path'])) {
                                    $file['file_path'] = normalize_file_path($file['file_path']);
                                }
                                $file = ensure_pdf_extracted($pdo, $file);
                            }
                            unset($file);
                        ?>
                        <div class="reply-bubble">
                            <div class="post-body" style="font-size:15px;"><?php echo $reply['content']; ?></div>
                            <?php if (!empty($reply_files)): ?>
                                <div style="margin-top:10px;">
                                    <?php foreach ($reply_files as $file): ?>
                                        <?php if (is_image($file['file_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                                <img src="<?php echo htmlspecialchars($file['file_path']); ?>" alt="<?php echo htmlspecialchars($file['original_filename']); ?>" style="max-width: 150px; border-radius: 6px; margin: 5px;">
                                            </a>
                                        <?php else: ?>
                                            <?php
                                                $reply_ext = strtolower(pathinfo($file['original_filename'] ?? '', PATHINFO_EXTENSION));
                                                $reply_force_download = $reply_ext !== 'pdf';
                                            ?>
                                            <div style="font-size: 12px; margin: 5px 0;">
                                                📎 <a href="<?php echo htmlspecialchars($file['file_path']); ?>" <?php echo $reply_force_download ? 'download' : ''; ?>><?php echo htmlspecialchars($file['original_filename']); ?></a>
                                                (<?php echo format_filesize($file['file_size']); ?>)
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="reply-meta">
                                <span><?php echo format_timestamp($reply['created_at']); ?><?php if (is_edited($reply['created_at'], $reply['updated_at']) || $reply['edited'] == 1) { echo ' · edited'; } ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
    function togglePreview(id) {
        var content = document.getElementById(id + '_content');
        var arrow = document.getElementById(id + '_arrow');
        if (!content || !arrow) return;
        var isHidden = content.style.display === 'none';
        content.style.display = isHidden ? 'block' : 'none';
        arrow.textContent = isHidden ? '▲' : '▼';
    }
    </script>
    <?php require __DIR__ . '/mobile_nav.php'; ?>
</body>
</html>
