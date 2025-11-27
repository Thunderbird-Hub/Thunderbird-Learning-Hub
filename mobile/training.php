<?php
$login_path = '/mobile/login.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/search_widget.php';
require_once __DIR__ . '/../includes/mobile_beta_gate.php';

// Load training helpers for progress + auto role management
if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

enforce_mobile_beta_access();

$page_title = 'Mobile Training';
$mobile_active_page = 'training';
$display_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

$overall_progress = [
    'percentage' => 0,
    'completed_items' => 0,
    'total_items' => 0,
    'completed_courses' => 0,
    'total_courses' => 0,
];
$courses = [];
$selected_course = null;
$course_items = [];
$upcoming_retests = [];
$available_retests = [];
$show_progress = $user_id && function_exists('should_show_training_progress') ? should_show_training_progress($pdo, $user_id) : false;

if ($user_id && function_exists('get_overall_training_progress')) {
    try {
        $overall_progress = get_overall_training_progress($pdo, $user_id);
    } catch (Exception $e) {
        $overall_progress = [
            'percentage' => 0,
            'completed_items' => 0,
            'total_items' => 0,
            'completed_courses' => 0,
            'total_courses' => 0,
        ];
    }
}

if ($user_id && function_exists('get_user_assigned_courses')) {
    $courses = get_user_assigned_courses($pdo, $user_id);
}

if ($user_id && function_exists('get_retestable_quizzes')) {
    try {
        $retestable_quizzes = get_retestable_quizzes($pdo, $user_id);
        foreach ($retestable_quizzes as $quiz) {
            $has_retest_period = !empty($quiz['retest_period_months']);
            if (!$has_retest_period) {
                continue;
            }

            $retest_eligible = !empty($quiz['retest_eligible']);
            $days_until = isset($quiz['days_until_retest']) ? (int) $quiz['days_until_retest'] : null;

            if ($days_until === null && !empty($quiz['next_retest_date'])) {
                $diff_seconds = strtotime($quiz['next_retest_date']) - time();
                $days_until = $diff_seconds > 0 ? (int) ceil($diff_seconds / 86400) : 0;
            }

            if ($retest_eligible) {
                $available_retests[] = $quiz;
            } elseif (!$retest_eligible && !empty($quiz['next_retest_date']) && $days_until !== null && $days_until >= 0) {
                $upcoming_retests[] = $quiz;
            }
        }
    } catch (Exception $e) {
        $upcoming_retests = [];
        $available_retests = [];
    }
}

$selected_course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : null;
if ($selected_course_id) {
    foreach ($courses as $course) {
        if ((int) $course['id'] === $selected_course_id) {
            $selected_course = $course;
            break;
        }
    }
}

if ($selected_course) {
    try {
        $items_stmt = $pdo->prepare("
            SELECT
                p.id  AS post_id,
                p.title AS post_title,
                COALESCE(tp.status, 'not_started') AS progress_status,
                COALESCE(tp.quiz_completed, uqa.passed, 0) AS quiz_done,
                tq.id AS quiz_id
            FROM training_course_content tcc
            LEFT JOIN posts p
                   ON tcc.content_id = p.id
            LEFT JOIN training_progress tp
                   ON tp.user_id = ?
                  AND tp.course_id = tcc.course_id
                  AND tp.content_type IN ('post','')
                  AND tp.content_id = p.id
            LEFT JOIN training_quizzes tq
                   ON tq.content_id = p.id
                  AND LOWER(COALESCE(tq.content_type,'')) IN ('post','')
            LEFT JOIN (
                SELECT
                    quiz_id,
                    MAX(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) AS passed
                FROM user_quiz_attempts
                WHERE user_id = ?
                GROUP BY quiz_id
            ) uqa
                   ON uqa.quiz_id = tq.id
            WHERE tcc.course_id = ?
              AND LOWER(COALESCE(tcc.content_type,'')) IN ('post','')
            ORDER BY tcc.training_order, p.title
        ");
        $items_stmt->execute([$user_id, $user_id, $selected_course_id]);
        $course_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize status so quiz completions also show as completed
        foreach ($course_items as &$item) {
            if (!empty($item['quiz_id']) && (int) $item['quiz_done'] === 1) {
                $item['progress_status'] = 'completed';
            }
        }
        unset($item);
    } catch (Exception $e) {
        $course_items = [];
    }
}

function format_mobile_date($date_value) {
    if (!$date_value) {
        return null;
    }
    $timestamp = strtotime($date_value);
    return $timestamp ? date('M j, Y', $timestamp) : null;
}

function format_retest_countdown($next_date) {
    if (!$next_date) {
        return 'Soon';
    }
    $diff = strtotime($next_date) - time();
    if ($diff <= 0) {
        return 'Available now';
    }
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);

    if ($days > 0) {
        $label = $days . ' day' . ($days === 1 ? '' : 's');
        if ($hours > 0) {
            $label .= ' ' . $hours . ' hr' . ($hours === 1 ? '' : 's');
        }
        return $label;
    }

    if ($hours > 0) {
        return $hours . ' hr' . ($hours === 1 ? '' : 's');
    }

    return 'Less than 1 hour';
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
    <link rel="stylesheet" href="/assets/css/style.css?v=20260205">
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
        .mobile-hero p { margin: 0; opacity: 0.92; font-size: 14px; }
        .progress-shell { background: white; border-radius: 14px; padding: 16px; box-shadow: 0 8px 18px rgba(15,23,42,0.08); border: 1px solid #e2e8f0; margin-bottom: 14px; }
        .progress-label { font-weight: 700; color: #2d3748; margin-bottom: 6px; }
        .progress-track { width: 100%; background: #edf2f7; border-radius: 999px; overflow: hidden; height: 12px; }
        .progress-fill { height: 12px; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 999px; transition: width 0.25s ease; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-top: 10px; }
        .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; box-shadow: 0 5px 14px rgba(15,23,42,0.05); }
        .stat-card .label { color: #4a5568; font-size: 13px; margin-bottom: 4px; }
        .stat-card .value { color: #1a202c; font-weight: 700; font-size: 18px; }
        .course-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px; }
        .course-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; box-shadow: 0 8px 18px rgba(15,23,42,0.06); position: relative; }
        .course-title { font-size: 16px; font-weight: 700; margin: 0 0 6px; color: #1a202c; }
        .course-meta { font-size: 12px; color: #4a5568; display: flex; gap: 8px; flex-wrap: wrap; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge.in-progress { background: #fffbeb; color: #b7791f; border: 1px solid #fbd38d; }
        .badge.completed { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge.not-started { background: #edf2f7; color: #2d3748; border: 1px solid #e2e8f0; }
        .pill { font-size: 12px; padding: 4px 8px; border-radius: 10px; background: #edf2f7; color: #2d3748; }
        .course-footer { margin-top: 10px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .cta { text-decoration: none; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 9px 12px; border-radius: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .content-list { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
        .content-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; box-shadow: 0 5px 14px rgba(15,23,42,0.05); display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .content-main { display: flex; gap: 10px; align-items: center; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; }
        .status-dot.completed { background: #10b981; }
        .status-dot.in-progress { background: #f59e0b; }
        .status-dot.not-started { background: #cbd5e0; }
        .content-title { margin: 0; font-weight: 700; color: #1a202c; }
        .content-meta { font-size: 12px; color: #4a5568; }
        .toggle-btn { border: 1px solid #e2e8f0; background: #f7fafc; color: #1a202c; border-radius: 12px; padding: 8px 10px; font-weight: 700; cursor: pointer; }
        .toggle-btn.completed { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; cursor: default; }
        .section-title { margin: 12px 0 8px; color: #2d3748; font-size: 16px; font-weight: 800; }
        .empty { padding: 12px; border: 1px dashed #cbd5e0; border-radius: 12px; text-align: center; color: #4a5568; background: #fff; }
        .meta-row { display: flex; gap: 8px; flex-wrap: wrap; }
        @media (max-width: 640px) {
            .mobile-shell { padding: 14px 12px 90px; }
            .course-card { padding: 12px; }
        }
    </style>
</head>
<body class="mobile-body">
    <div class="mobile-shell">
        <?php if (defined('MOBILE_BETA_BANNER') && MOBILE_BETA_BANNER): ?>
            <div style="background:#fff7ed;color:#9c4221;border:1px solid #fbd38d;border-radius:12px;padding:10px 12px;margin-bottom:14px;display:flex;align-items:center;gap:10px;box-shadow:0 8px 18px rgba(15,23,42,0.08);font-weight:600;">
                <span>🧪</span>
                <span><?php echo htmlspecialchars(MOBILE_BETA_BANNER); ?></span>
            </div>
        <?php endif; ?>
        <div class="mobile-hero" id="home">
            <h1>Training, <?php echo htmlspecialchars($display_name); ?> 📱</h1>
            <p><?php echo htmlspecialchars(SITE_NAME); ?> · Mobile dashboard</p>
        </div>

        <div class="progress-shell">
            <div class="progress-label">Overall progress</div>
            <div class="progress-track" aria-label="Overall training progress">
                <div class="progress-fill" style="width: <?php echo (int) $overall_progress['percentage']; ?>%;"></div>
            </div>
            <div class="meta-row" style="margin-top:8px;">
                <span class="pill"><?php echo (int) $overall_progress['percentage']; ?>% complete</span>
                <span class="pill"><?php echo (int) $overall_progress['completed_items']; ?> of <?php echo (int) $overall_progress['total_items']; ?> items</span>
                <span class="pill"><?php echo (int) $overall_progress['completed_courses']; ?> of <?php echo (int) $overall_progress['total_courses']; ?> courses</span>
            </div>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="label">Assignments</div>
                    <div class="value"><?php echo count($courses); ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Status</div>
                    <div class="value"><?php echo $show_progress ? 'In Training' : 'No Active Training'; ?></div>
                </div>
            </div>
        </div>

        <div class="mobile-card">
            <h2 class="section-title">Search</h2>
            <p>Search for training content and posts.</p>
            <?php if (function_exists('render_search_bar')) { render_search_bar('/mobile/search.php', '/mobile/search_autocomplete.php', 'mobile'); } ?>
        </div>

        <?php if (!empty($available_retests)) : ?>
            <div class="mobile-card" style="border:1px solid #fcd34d; background:#fffbeb;">
                <h2 class="section-title" style="margin-top:0;">🔄 Retests available</h2>
                <p style="color:#92400e; margin-top:0;">You can retake these quizzes now. Retaking will reset your quiz progress.</p>
                <div class="content-list">
                    <?php foreach ($available_retests as $quiz) :
                        $next_date = isset($quiz['next_retest_date']) ? $quiz['next_retest_date'] : null;
                        $reopen_date = $next_date ? format_mobile_date($next_date) : 'Available now';
                        $last_attempt = isset($quiz['last_attempt_date']) ? format_mobile_date($quiz['last_attempt_date']) : 'Unknown';
                    ?>
                        <div class="content-item" style="align-items:flex-start;">
                            <div>
                                <p class="content-title" style="margin:0 0 4px;">
                                    <?php echo htmlspecialchars($quiz['title'] ?? 'Quiz'); ?>
                                </p>
                                <div class="content-meta">
                                    <span class="pill" style="background:#fef3c7; color:#92400e;">Retest every <?php echo (int) ($quiz['retest_period_months'] ?? 0); ?> month(s)</span>
                                    <span class="pill">Last completed <?php echo htmlspecialchars($last_attempt); ?></span>
                                    <span class="pill">Reopened <?php echo htmlspecialchars($reopen_date); ?></span>
                                </div>
                            </div>
                            <div class="pill" style="background:#ecfccb; color:#15803d;">Ready to retake</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="mobile-card" style="border:1px solid #fcd34d; background:#fffbeb;">
            <h2 class="section-title" style="margin-top:0;">⏳ Upcoming training</h2>
            <p style="color:#92400e; margin-top:0;">These quizzes will reopen soon. Plan to retake them once available.</p>
            <?php if (!empty($upcoming_retests)) : ?>
                <div class="content-list">
                    <?php foreach ($upcoming_retests as $quiz) :
                        $next_date = isset($quiz['next_retest_date']) ? $quiz['next_retest_date'] : null;
                        $countdown = format_retest_countdown($next_date);
                        $reopen_date = $next_date ? format_mobile_date($next_date) : null;
                    ?>
                        <div class="content-item" style="align-items:flex-start;">
                            <div>
                                <p class="content-title" style="margin:0 0 4px;">
                                    <?php echo htmlspecialchars($quiz['title'] ?? 'Quiz'); ?>
                                </p>
                                <div class="content-meta">
                                    <span class="pill" style="background:#fef3c7; color:#92400e;">Retest every <?php echo (int)($quiz['retest_period_months'] ?? 0); ?> month(s)</span>
                                    <?php if ($reopen_date) : ?>
                                        <span class="pill">Reopens <?php echo htmlspecialchars($reopen_date); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="pill" style="background:#fefcbf; color:#92400e;"><?php echo htmlspecialchars($countdown); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="empty">No retests scheduled yet.</div>
            <?php endif; ?>
        </div>

        <h2 class="section-title" id="assignments">Assigned courses</h2>
        <?php if (empty($courses)) : ?>
            <div class="empty">No training assignments yet.</div>
        <?php else : ?>
            <div class="course-list">
                <?php foreach ($courses as $course) :
                    $progress = isset($course['progress_percentage']) ? (int) $course['progress_percentage'] : 0;
                    $status = strtolower((string) $course['assignment_status']);
                    $status_class = 'not-started';
                    $status_label = 'Not started';
                    if ($progress >= 100) {
                        $status_class = 'completed';
                        $status_label = 'Completed';
                    } elseif ($progress > 0 || $status === 'in_progress') {
                        $status_class = 'in-progress';
                        $status_label = 'In progress';
                    }
                    $due_date = format_mobile_date($course['due_date'] ?? null);
                    $assigned_date = format_mobile_date($course['assigned_date'] ?? null);
                ?>
                    <div class="course-card">
                        <div class="course-meta">
                            <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label); ?></span>
                            <?php if (!empty($course['department_name'])) : ?>
                                <span class="pill">Dept: <?php echo htmlspecialchars($course['department_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="course-title"><?php echo htmlspecialchars($course['name']); ?></p>
                        <div class="course-meta">
                            <span>Assigned <?php echo $assigned_date ? $assigned_date : 'Recently'; ?></span>
                            <span>Due <?php echo $due_date ? $due_date : 'No due date'; ?></span>
                        </div>
                        <div class="progress-track" style="margin: 10px 0 6px;">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                        <div class="course-footer">
                            <span class="pill"><?php echo $progress; ?>% complete</span>
                            <a class="cta" href="/mobile/training.php?course_id=<?php echo (int) $course['id']; ?>#course-detail">View</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="course-detail" style="margin-top: 18px;">
            <h2 class="section-title">Course detail</h2>
            <?php if (!$selected_course) : ?>
                <div class="empty">Select a course to see its content and completion toggles.</div>
            <?php else :
                $course_progress = isset($selected_course['progress_percentage']) ? (int) $selected_course['progress_percentage'] : 0;
                $course_status = strtolower((string) $selected_course['assignment_status']);
                $course_status_class = 'not-started';
                $course_status_label = 'Not started';
                if ($course_progress >= 100) {
                    $course_status_class = 'completed';
                    $course_status_label = 'Completed';
                } elseif ($course_progress > 0 || $course_status === 'in_progress') {
                    $course_status_class = 'in-progress';
                    $course_status_label = 'In progress';
                }
            ?>
                <div class="course-card" style="margin-bottom: 10px;">
                    <div class="course-meta">
                        <span class="badge <?php echo $course_status_class; ?>"><?php echo htmlspecialchars($course_status_label); ?></span>
                        <span class="pill">Progress <?php echo $course_progress; ?>%</span>
                    </div>
                    <p class="course-title" style="margin-bottom:4px;">Viewing: <?php echo htmlspecialchars($selected_course['name']); ?></p>
                    <?php if (!empty($selected_course['description'])) : ?>
                        <p style="margin:0 0 8px; color:#4a5568; font-size: 14px;">
                            <?php echo htmlspecialchars($selected_course['description']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (empty($course_items)) : ?>
                    <div class="empty">No post-based items are assigned to this course yet.</div>
                <?php else : ?>
                    <div class="content-list">
                        <?php foreach ($course_items as $item) :
                            $progress_raw = strtolower((string) $item['progress_status']);
                            $status_class = 'not-started';
                            $status_label = 'Not started';
                            if ($progress_raw === 'completed') {
                                $status_class = 'completed';
                                $status_label = 'Completed';
                            } elseif ($progress_raw === 'in_progress') {
                                $status_class = 'in-progress';
                                $status_label = 'In progress';
                            }
                            $quiz_text = 'No quiz linked';
                            if (!empty($item['quiz_id']) && (int) $item['quiz_done'] === 1) {
                                $quiz_text = 'Quiz completed';
                            } elseif (!empty($item['quiz_id'])) {
                                $quiz_text = 'Quiz pending';
                            }
                            $button_state = $status_class === 'completed' ? 'completed' : '';
                            $button_label = $status_class === 'completed' ? 'Completed' : 'Mark complete';
                            $show_button = empty($item['quiz_id']);
                        ?>
                            <div class="content-item">
                                <div class="content-main">
                                    <span class="status-dot <?php echo $status_class; ?>"></span>
                                    <div>
                                        <p class="content-title"><a href="/mobile/post.php?id=<?php echo (int) $item['post_id']; ?>" style="text-decoration:none;color:#1a202c;"><?php echo htmlspecialchars($item['post_title'] ?? 'Post'); ?></a></p>
                                        <div class="content-meta"><?php echo htmlspecialchars($status_label); ?> · <?php echo htmlspecialchars($quiz_text); ?></div>
                                    </div>
                                </div>
                                <?php if ($show_button) : ?>
                                    <button class="toggle-btn <?php echo $button_state; ?>" data-content-id="<?php echo (int) $item['post_id']; ?>" data-status="<?php echo $status_class; ?>" <?php echo $button_state ? 'disabled' : ''; ?>><?php echo $button_label; ?></button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div style="margin-top:16px; text-align:center;">
            <a href="/mobile/index.php" class="cta" style="background:#edf2f7; color:#2d3748; border:1px solid #e2e8f0;">← Back to mobile home</a>
        </div>
    </div>

    <?php require __DIR__ . '/mobile_nav.php'; ?>

    <script>
        document.querySelectorAll('.toggle-btn').forEach(button => {
            button.addEventListener('click', () => {
                if (button.classList.contains('completed')) {
                    return;
                }
                const contentId = button.dataset.contentId;
                if (!contentId) { return; }
                button.disabled = true;
                button.textContent = 'Updating...';

                fetch(`/includes/training_helpers.php?action=mark_complete&content_id=${contentId}&content_type=post`)
                    .then(resp => resp.json())
                    .then(data => {
                        if (data.success) {
                            button.classList.add('completed');
                            button.textContent = 'Completed';
                            const container = button.closest('.content-item');
                            if (container) {
                                const dot = container.querySelector('.status-dot');
                                const meta = container.querySelector('.content-meta');
                                if (dot) { dot.className = 'status-dot completed'; }
                                if (meta) { meta.textContent = meta.textContent.replace('Not started', 'Completed').replace('In progress', 'Completed'); }
                            }
                        } else {
                            button.disabled = false;
                            button.textContent = 'Mark complete';
                            alert(data.message || 'Unable to update completion');
                        }
                    })
                    .catch(() => {
                        button.disabled = false;
                        button.textContent = 'Mark complete';
                        alert('Unable to update completion');
                    });
            });
        });
    </script>
</body>
</html>
