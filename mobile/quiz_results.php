<?php
/**
 * Mobile Quiz Results
 */
$login_path = '/mobile/login.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/mobile_beta_gate.php';

if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

enforce_mobile_beta_access();

$attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;
if ($attempt_id <= 0) {
    header('Location: /mobile/training.php');
    exit;
}

$page_title = 'Quiz Results';
$error_message = '';
$is_admin_view_flag = isset($_GET['admin_view']) && (
    $_GET['admin_view'] === '1' ||
    $_GET['admin_view'] === 'true'
);
$can_force_breakdown = $is_admin_view_flag && (is_admin() || is_super_admin());

if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$attempt = null;
$quiz = null;
$questions = [];
$user_answers = [];
$mobile_content_url = '';

try {
    if (function_exists('log_debug')) {
        log_debug("mobile/quiz_results.php: fetching attempt_id=$attempt_id (viewer user_id=" . ($_SESSION['user_id'] ?? 'none') . ")");
    }

    $stmt = $pdo->prepare("
        SELECT uqa.*, tq.quiz_title, tq.passing_score, tq.retest_period_months,
               tcc.content_id, tcc.content_type,
               CASE tcc.content_type
                   WHEN 'category'   THEN c.name
                   WHEN 'subcategory' THEN sc.name
                   WHEN 'post'       THEN p.title
               END AS content_name,
               CASE tcc.content_type
                   WHEN 'category'   THEN 'category.php?id='
                   WHEN 'subcategory' THEN '/categories/subcategory.php?id='
                   WHEN 'post'       THEN '/posts/post.php?id='
               END AS content_url
        FROM user_quiz_attempts uqa
        JOIN training_quizzes tq
          ON uqa.quiz_id = tq.id
        JOIN training_course_content tcc
          ON tq.content_id = tcc.content_id
         AND (
                tq.content_type = tcc.content_type
             OR tq.content_type = ''
             OR tq.content_type IS NULL
         )
        LEFT JOIN categories c
          ON tcc.content_type = 'category'   AND tcc.content_id = c.id
        LEFT JOIN subcategories sc
          ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
        LEFT JOIN posts p
          ON tcc.content_type = 'post'       AND tcc.content_id = p.id
       WHERE uqa.id = ?
       LIMIT 1
    ");
    $stmt->execute([$attempt_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        if (function_exists('log_debug')) {
            log_debug("mobile/quiz_results.php: primary fetch returned no row; attempting fallback join via training_quizzes only");
        }

        $stmt = $pdo->prepare("
            SELECT uqa.*, tq.quiz_title, tq.passing_score, tq.retest_period_months,
                   tq.content_id,
                   COALESCE(NULLIF(tq.content_type,''),'post') AS content_type,
                   CASE COALESCE(NULLIF(tq.content_type,''),'post')
                       WHEN 'category'   THEN c.name
                       WHEN 'subcategory' THEN sc.name
                       WHEN 'post'       THEN p.title
                   END AS content_name,
                   CASE COALESCE(NULLIF(tq.content_type,''),'post')
                       WHEN 'category'   THEN 'category.php?id='
                       WHEN 'subcategory' THEN '/categories/subcategory.php?id='
                       WHEN 'post'       THEN '/posts/post.php?id='
                   END AS content_url
            FROM user_quiz_attempts uqa
            JOIN training_quizzes tq
              ON uqa.quiz_id = tq.id
            LEFT JOIN categories c
              ON COALESCE(NULLIF(tq.content_type,''),'post') = 'category'   AND tq.content_id = c.id
            LEFT JOIN subcategories sc
              ON COALESCE(NULLIF(tq.content_type,''),'post') = 'subcategory' AND tq.content_id = sc.id
            LEFT JOIN posts p
              ON COALESCE(NULLIF(tq.content_type,''),'post') = 'post'       AND tq.content_id = p.id
           WHERE uqa.id = ?
           LIMIT 1
        ");
        $stmt->execute([$attempt_id]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (function_exists('log_debug')) {
        log_debug(
            "mobile/quiz_results.php: attempt fetch result = " .
            ($attempt ? 'FOUND (owner user_id=' . $attempt['user_id'] . ')' : 'NOT FOUND')
        );
    }

    if ($attempt) {
        $viewer_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $owner_id  = isset($attempt['user_id']) ? (int)$attempt['user_id'] : 0;

        if ($owner_id !== $viewer_id && !is_admin() && !is_super_admin()) {
            if (function_exists('log_debug')) {
                log_debug("mobile/quiz_results.php: permission denied for viewer user_id={$viewer_id} on attempt owned by user_id={$owner_id}");
            }
            $error_message = 'You are not allowed to view this quiz attempt.';
            $attempt = null;
        }
    }

    if (!$attempt) {
        if (empty($error_message)) {
            $error_message = 'Quiz attempt not found.';
        }
    } else {
        $quiz = [
            'quiz_title'    => $attempt['quiz_title'],
            'passing_score' => $attempt['passing_score'],
            'content_name'  => $attempt['content_name'],
            'content_url'   => $attempt['content_url'],
            'content_id'    => $attempt['content_id'],
            'content_type'  => $attempt['content_type']
        ];

        switch ($quiz['content_type']) {
            case 'post':
                $mobile_content_url = '/mobile/post.php?id=' . intval($quiz['content_id']);
                break;
            case 'subcategory':
                $mobile_content_url = '/mobile/subcategory.php?id=' . intval($quiz['content_id']);
                break;
            case 'category':
                $mobile_content_url = '/mobile/categories.php';
                break;
            default:
                $mobile_content_url = $quiz['content_url'] . $quiz['content_id'];
                break;
        }

        $earned_points  = isset($attempt['earned_points']) ? floatval($attempt['earned_points']) : 0.0;
        $total_points   = isset($attempt['total_points'])  ? floatval($attempt['total_points'])  : 0.0;
        $display_score  = ($total_points > 0) ? round(($earned_points / $total_points) * 100) : 0;

        $retry_available = false;
        $retry_message = '';
        $retry_url = '';
        $retry_is_resume = false;

        if (isset($_SESSION['user_id']) && (int)$attempt['user_id'] === (int)$_SESSION['user_id']) {
            $retry_url = '/training/take_quiz.php?quiz_id=' . intval($attempt['quiz_id']) .
                '&content_id=' . intval($quiz['content_id']) .
                '&content_type=' . urlencode($quiz['content_type']) .
                '&mobile=1';

            $in_progress_stmt = $pdo->prepare("SELECT id FROM user_quiz_attempts WHERE user_id = ? AND quiz_id = ? AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1");
            $in_progress_stmt->execute([$_SESSION['user_id'], $attempt['quiz_id']]);
            $in_progress = $in_progress_stmt->fetch(PDO::FETCH_ASSOC);

            if ($in_progress && intval($in_progress['id']) !== intval($attempt_id)) {
                $retry_available = true;
                $retry_message = 'Resume your in-progress attempt.';
                $retry_is_resume = true;
            } else {
                $last_attempt_stmt = $pdo->prepare("SELECT completed_at FROM user_quiz_attempts WHERE user_id = ? AND quiz_id = ? AND status IN ('passed','failed') ORDER BY completed_at DESC LIMIT 1");
                $last_attempt_stmt->execute([$_SESSION['user_id'], $attempt['quiz_id']]);
                $last_completed_attempt = $last_attempt_stmt->fetch(PDO::FETCH_ASSOC);

                $retest_months = intval($attempt['retest_period_months'] ?? 0);

                if (!$last_completed_attempt) {
                    $retry_available = true;
                } elseif ($retest_months <= 0) {
                    $retry_available = true;
                } else {
                    $completed_at = new DateTime($last_completed_attempt['completed_at']);
                    $retest_available_at = clone $completed_at;
                    $retest_available_at->add(new DateInterval('P' . $retest_months . 'M'));
                    $now = new DateTime();

                    if ($now >= $retest_available_at) {
                        $retry_available = true;
                    } else {
                        $retry_available = false;
                        $retry_message = 'Available to retry in ' . $now->diff($retest_available_at)->days . ' day(s).';
                    }
                }
            }
        }

        $completed_at_display = 'Not completed yet';
        if (!empty($attempt['completed_at'])) {
            $completed_ts = strtotime($attempt['completed_at']);
            if ($completed_ts) {
                $completed_at_display = date('M j, Y \a\t g:i A', $completed_ts);
            }
        }

        if ($display_score > 0 && (empty($attempt['score']) || intval($attempt['score']) === 0)) {
            $attempt['score'] = $display_score;
        }

        $stmt = $pdo->prepare("
            SELECT qq.id, qq.question_text, qq.points,
                   uqa.selected_choice_id, uqa.is_correct, uqa.points_earned,
                   qac.choice_text, qac.is_correct as correct_answer
            FROM quiz_questions qq
            LEFT JOIN user_quiz_answers uqa
              ON qq.id = uqa.question_id
             AND uqa.attempt_id = ?
            LEFT JOIN quiz_answer_choices qac ON uqa.selected_choice_id = qac.id
            WHERE qq.quiz_id = ?
            ORDER BY qq.question_order, qq.id
        ");
        $stmt->execute([$attempt_id, $attempt['quiz_id']]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $question_id = $row['id'];
            if (!isset($questions[$question_id])) {
                $user_choice_text = $row['choice_text'] ?? '';
                if ($user_choice_text === null || $user_choice_text === '') {
                    $user_choice_text = 'No answer submitted';
                }

                $questions[$question_id] = [
                    'id' => $row['id'],
                    'question_text' => $row['question_text'],
                    'points' => $row['points'],
                    'user_choice' => [
                        'text' => $user_choice_text,
                        'is_correct' => !empty($row['is_correct']),
                        'points_earned' => $row['points_earned'] ?? 0
                    ]
                ];
            }

            if (!isset($user_answers[$question_id])) {
                $stmt = $pdo->prepare("
                    SELECT qac.choice_text, qac.is_correct
                    FROM quiz_answer_choices qac
                    WHERE qac.question_id = ?
                    ORDER BY qac.choice_order
                ");
                $stmt->execute([$question_id]);
                $user_answers[$question_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (PDOException $e) {
    $error_message = 'Error loading quiz results: ' . $e->getMessage();
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
        body.mobile-body { background: #f7fafc; padding: 0 0 80px; margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .mobile-shell { max-width: 960px; margin: 0 auto; padding: 16px; }
        .card { background: white; border-radius: 16px; padding: 16px; box-shadow: 0 10px 30px rgba(15,23,42,0.12); border: 1px solid #e2e8f0; }
        .hero { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 18px; padding: 18px; box-shadow: 0 10px 28px rgba(102,126,234,0.28); margin-bottom: 16px; }
        .hero h1 { margin: 0 0 4px; font-size: 22px; }
        .hero p { margin: 0; opacity: 0.92; }
        .status-chip { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 12px; font-weight: 700; font-size: 14px; }
        .status-chip.passed { background: #ecfdf3; color: #0f5132; border: 1px solid #bcd0c7; }
        .status-chip.failed { background: #fef2f2; color: #842029; border: 1px solid #f5c2c7; }
        .status-chip.in_progress { background: #fff7ed; color: #b45309; border: 1px solid #fed7aa; }
        .score-ring { width: 120px; height: 120px; border-radius: 50%; display: grid; place-items: center; background: conic-gradient(#667eea calc(var(--score) * 1%), #e5e7eb 0); position: relative; }
        .score-ring::after { content: ''; position: absolute; inset: 14px; background: white; border-radius: 50%; }
        .score-ring span { position: relative; font-size: 26px; font-weight: 800; color: #111827; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 14px; }
        .meta-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; }
        .meta-item .label { color: #4b5563; font-size: 13px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.6px; }
        .meta-item .value { color: #111827; font-weight: 700; font-size: 16px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .btn { text-decoration: none; padding: 12px 14px; border-radius: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; box-shadow: 0 10px 20px rgba(102,126,234,0.25); }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .btn-warning { background: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
        .alert { padding: 14px; border-radius: 12px; border: 1px solid transparent; margin: 10px 0; }
        .alert-danger { background: #fef2f2; color: #842029; border-color: #f5c2c7; }
        .alert-info { background: #e0f2fe; color: #075985; border-color: #bae6fd; }
        .question { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; box-shadow: 0 5px 16px rgba(15,23,42,0.08); margin-bottom: 10px; }
        .question-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .pill { padding: 6px 10px; border-radius: 10px; background: #eef2ff; color: #3730a3; font-weight: 700; font-size: 12px; }
        .answer { background: #f8fafc; border-radius: 10px; padding: 12px; border-left: 4px solid #667eea; }
        .feedback { margin-top: 8px; padding: 10px; border-radius: 10px; display: inline-flex; align-items: center; gap: 8px; font-weight: 700; }
        .feedback.correct { background: #ecfdf3; color: #0f5132; }
        .feedback.incorrect { background: #fef2f2; color: #842029; }
        .correct-answer { margin-top: 8px; padding: 12px; background: #ecfdf3; border-radius: 10px; border-left: 4px solid #22c55e; color: #166534; }
    </style>
</head>
<body class="mobile-body">
    <div class="mobile-shell">
        <div class="hero">
            <h1>Quiz Results</h1>
            <p>Review your latest attempt and next steps.</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php else: ?>
            <div class="card" style="margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <div class="status-chip <?php echo htmlspecialchars($attempt['status']); ?>">
                        <?php echo $attempt['status'] === 'passed' ? '✅ Passed' : ($attempt['status'] === 'failed' ? '❌ Failed' : '⏳ In Progress'); ?>
                    </div>
                    <div class="score-ring" style="--score: <?php echo max(0, min(100, $attempt['score'] ?? 0)); ?>;">
                        <span><?php echo $attempt['score'] ?? 0; ?>%</span>
                    </div>
                    <div style="flex:1; min-width: 200px;">
                        <div style="font-weight:700; color:#1f2937; font-size:18px; margin-bottom:4px;">Content: <?php echo htmlspecialchars($quiz['content_name'] ?? ''); ?></div>
                        <div style="color:#4b5563;">Quiz: <?php echo htmlspecialchars($quiz['quiz_title'] ?? ''); ?></div>
                    </div>
                </div>
                <div class="meta-grid">
                    <div class="meta-item"><div class="label">Attempt</div><div class="value">#<?php echo $attempt['attempt_number']; ?></div></div>
                    <div class="meta-item"><div class="label">Time Taken</div><div class="value"><?php echo $attempt['time_taken_minutes']; ?> mins</div></div>
                    <div class="meta-item"><div class="label">Completed</div><div class="value"><?php echo htmlspecialchars($completed_at_display); ?></div></div>
                    <div class="meta-item"><div class="label">Passing Score</div><div class="value"><?php echo intval($quiz['passing_score']); ?>%</div></div>
                </div>
                <div class="actions">
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($mobile_content_url); ?>">📚 Review Content</a>
                    <?php if ($retry_available): ?>
                        <a class="btn btn-warning" href="<?php echo htmlspecialchars($retry_url); ?>"><?php echo $retry_is_resume ? 'Resume Attempt' : 'Retake Quiz'; ?></a>
                    <?php elseif ($retry_message): ?>
                        <div class="alert alert-info" style="margin:0;">⚠️ <?php echo htmlspecialchars($retry_message); ?></div>
                    <?php endif; ?>
                    <a class="btn btn-primary" href="/mobile/training.php">🏠 Training Dashboard</a>
                </div>
            </div>

            <?php if ($attempt['status'] === 'passed' || $can_force_breakdown): ?>
                <?php if ($can_force_breakdown && $attempt['status'] !== 'passed'): ?>
                    <div class="alert alert-info">Admin view: showing breakdown before pass.</div>
                <?php endif; ?>
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question">
                        <div class="question-header">
                            <div class="pill">Q<?php echo $index + 1; ?></div>
                            <div style="font-weight:700; color:#111827; flex:1;"><?php echo htmlspecialchars($question['question_text']); ?></div>
                            <div class="pill" style="background:#f3f4f6; color:#374151;"><?php echo $question['points']; ?> pts</div>
                        </div>
                        <div class="answer">
                            <div style="font-size:12px; color:#4b5563; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:6px;">Your Answer</div>
                            <div style="font-weight:600; color:#111827;">&bull; <?php echo htmlspecialchars($question['user_choice']['text']); ?></div>
                            <div class="feedback <?php echo $question['user_choice']['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                <?php echo $question['user_choice']['is_correct'] ? '✅ Correct' : '❌ Incorrect'; ?>
                            </div>
                        </div>
                        <?php if (!$question['user_choice']['is_correct']): ?>
                            <div class="correct-answer">
                                <strong>Correct Answer:</strong>
                                <?php
                                    foreach ($user_answers[$question['id']] as $choice) {
                                        if ($choice['is_correct']) {
                                            echo ' ' . htmlspecialchars($choice['choice_text']);
                                            break;
                                        }
                                    }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">Question breakdown will be available after you pass this quiz.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/mobile_nav.php'; ?>
</body>
</html>
