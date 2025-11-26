<?php
/**
 * Mobile Quiz Taking Page
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

$quiz_id      = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$content_id   = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
$content_type = isset($_GET['content_type']) ? trim(strtolower($_GET['content_type'])) : '';

$orig_content_id  = $content_id;
$orig_content_type = $content_type;
$allowed_ct = ['post', 'subcategory', 'category', ''];
if (!in_array($content_type, $allowed_ct, true)) {
    $content_type = '';
}

if (function_exists('log_debug')) {
    log_debug(
        "mobile/quiz.php accessed - Quiz ID: {$quiz_id}, Content ID: {$content_id}, Content Type: '{$content_type}', User ID: " . ($_SESSION['user_id'] ?? 'none') . ", Time: " . date('Y-m-d H:i:s'),
        'INFO'
    );
}

if ($quiz_id <= 0) {
    header('Location: /mobile/training.php');
    exit;
}

$page_title = 'Training Quiz';
$error_message = '';
$success_message = '';

if (!function_exists('is_training_user') || !is_training_user()) {
    header('Location: /mobile/index.php');
    exit;
}

$quiz = null;
$quiz_attempt = null;
$can_attempt = false;
$mobile_content_url = '/mobile/training.php';

try {
    $stmt = $pdo->prepare(
        "SELECT tq.*,\n               tcc.content_id, tcc.content_type,\n               CASE tcc.content_type\n                   WHEN 'category' THEN c.name\n                   WHEN 'subcategory' THEN sc.name\n                   WHEN 'post' THEN p.title\n               END as content_name,\n               CASE tcc.content_type\n                   WHEN 'category' THEN 'category.php?id='\n                   WHEN 'subcategory' THEN '/categories/subcategory.php?id='\n                   WHEN 'post' THEN '/posts/post.php?id='\n               END as content_url\n        FROM training_quizzes tq\n        JOIN training_course_content tcc ON tq.content_id = tcc.content_id AND (tq.content_type = tcc.content_type OR tq.content_type = '' OR tq.content_type IS NULL)\n        LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id\n        LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id\n        LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id\n        WHERE tq.id = ? AND tq.is_active = TRUE"
    );
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (function_exists('log_debug')) {
        log_debug("Mobile quiz query result for Quiz ID $quiz_id: " . json_encode($quiz));
        log_debug("Expected Content ID: $content_id, Expected Content Type: '$content_type'");
    }

    if (!$quiz) {
        $error_message = 'Quiz not found or not active.';
        if (function_exists('log_debug')) {
            log_debug("Quiz not found - Quiz ID: $quiz_id");
        }
    } else {
        $provided_ct = strtolower(trim((string)$content_type));
        $quiz_ct     = strtolower(trim((string)$quiz['content_type']));

        if ($provided_ct === '') {
            $provided_ct  = ($quiz_ct !== '') ? $quiz_ct : 'post';
            $content_type = $provided_ct;
        }

        $ct_matches = ($quiz_ct === $provided_ct) || ($quiz_ct === '' && $provided_ct === 'post');
        $provided_was_missing = ($content_id <= 0 || $provided_ct === '');

        if ($quiz['content_id'] != $content_id || !$ct_matches || $provided_was_missing) {
            $content_id   = intval($quiz['content_id']);
            $quiz_ct      = strtolower(trim((string)$quiz['content_type']));
            $content_type = ($quiz_ct !== '') ? $quiz_ct : 'post';
            $ct_matches   = true;

            if (function_exists('log_debug')) {
                log_debug("Auto-corrected mobile quiz URL params -> content_id={$content_id}, content_type='{$content_type}'");
            }
        }

        if (function_exists('is_assigned_training_content')) {
            $can_attempt = is_assigned_training_content($pdo, $_SESSION['user_id'], $content_id, $content_type);
        } else {
            $can_attempt = true;
        }

        if (!$can_attempt) {
            $error_message = 'You do not have access to this quiz.';
        }

        switch ($content_type) {
            case 'post':
                $mobile_content_url = '/mobile/post.php?id=' . intval($content_id);
                break;
            case 'subcategory':
                $mobile_content_url = '/mobile/subcategory.php?id=' . intval($content_id);
                break;
            case 'category':
                $mobile_content_url = '/mobile/categories.php';
                break;
            default:
                $mobile_content_url = '/mobile/training.php';
                break;
        }
    }

    if ($can_attempt) {
        $stmt = $pdo->prepare(
            "SELECT * FROM user_quiz_attempts\n            WHERE user_id = ? AND quiz_id = ? AND status = 'in_progress'\n            ORDER BY started_at DESC\n            LIMIT 1"
        );
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);
        $quiz_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz_attempt) {
            $can_create_new_attempt = false;

            $stmt = $pdo->prepare(
                "SELECT * FROM user_quiz_attempts\n                WHERE user_id = ? AND quiz_id = ? AND status IN ('passed', 'failed')\n                ORDER BY completed_at DESC\n                LIMIT 1"
            );
            $stmt->execute([$_SESSION['user_id'], $quiz_id]);
            $last_completed_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$last_completed_attempt) {
                $can_create_new_attempt = true;
            } else {
                $retest_months = intval($quiz['retest_period_months'] ?? 0);

                if ($retest_months <= 0) {
                    $can_create_new_attempt = true;
                } else {
                    $completed_at = new DateTime($last_completed_attempt['completed_at']);
                    $retest_available_at = clone $completed_at;
                    $retest_available_at->add(new DateInterval("P{$retest_months}M"));
                    $now = new DateTime();

                    if ($now >= $retest_available_at) {
                        $can_create_new_attempt = true;
                    } else {
                        $days_until_retest = $now->diff($retest_available_at)->days;
                        $error_message = "This quiz requires a waiting period of {$retest_months} month(s) before retaking. You can retake this quiz in {$days_until_retest} day(s).";

                        if (function_exists('log_debug')) {
                            log_debug("Retest blocked (mobile) - User {$_SESSION['user_id']}, Quiz {$quiz_id}, Completed at: {$last_completed_attempt['completed_at']}, Retest available: {$retest_available_at->format('Y-m-d H:i:s')}");
                        }
                    }
                }
            }

            if ($can_create_new_attempt) {
                $stmt = $pdo->prepare(
                    "INSERT INTO user_quiz_attempts (user_id, quiz_id, status, started_at)\n                    VALUES (?, ?, 'in_progress', NOW())"
                );
                $stmt->execute([$_SESSION['user_id'], $quiz_id]);
                $quiz_attempt = [
                    'id' => $pdo->lastInsertId(),
                    'status' => 'in_progress',
                ];
            }
        }
    }
} catch (PDOException $e) {
    $error_message = 'Error loading quiz: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_quiz' && $can_attempt && $quiz && $quiz_attempt) {
    try {
        if ($quiz_attempt['status'] !== 'in_progress') {
            $error_message = 'This attempt is no longer active.';
        } else {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id, question_id, is_correct\n                FROM quiz_answer_choices\n                WHERE question_id IN (\n                    SELECT id FROM quiz_questions WHERE quiz_id = ? AND is_active = TRUE\n                )"
            );
            $stmt->execute([$quiz_id]);
            $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $correct_answers = [];
            foreach ($choices as $choice) {
                if ($choice['is_correct']) {
                    $correct_answers[intval($choice['question_id'])] = intval($choice['id']);
                }
            }

            $user_answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];
            $total_questions = count($correct_answers);
            $score = 0;

            foreach ($correct_answers as $question_id => $correct_choice_id) {
                $user_choice_id = isset($user_answers[$question_id]) ? intval($user_answers[$question_id]) : null;
                $is_correct = ($user_choice_id && $user_choice_id === $correct_choice_id);
                if ($is_correct) {
                    $score++;
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO user_quiz_answers (attempt_id, question_id, answer_choice_id)\n                     VALUES (?, ?, ?)\n                     ON DUPLICATE KEY UPDATE answer_choice_id = VALUES(answer_choice_id)"
                );
                $stmt->execute([$quiz_attempt['id'], $question_id, $user_choice_id]);
            }

            $percentage_score = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;
            $status = ($percentage_score >= $quiz['passing_score']) ? 'passed' : 'failed';

            $stmt = $pdo->prepare(
                "UPDATE user_quiz_attempts\n                 SET status = ?, score = ?, completed_at = NOW()\n                 WHERE id = ?"
            );
            $stmt->execute([$status, $percentage_score, $quiz_attempt['id']]);

            $norm_ct = ($content_type === '') ? 'post' : $content_type;
            $progress_stmt = $pdo->prepare(
                "INSERT INTO training_progress (user_id, course_id, content_type, content_id, status, quiz_completed, quiz_score, last_quiz_attempt_id, updated_at)\n                 VALUES (?, 0, ?, ?, 'completed', 1, ?, ?, NOW())\n                 ON DUPLICATE KEY UPDATE\n                 status = 'completed',\n                 quiz_completed = 1,\n                 quiz_score = VALUES(quiz_score),\n                 last_quiz_attempt_id = VALUES(last_quiz_attempt_id),\n                 updated_at = NOW()"
            );
            $progress_stmt->execute([
                $_SESSION['user_id'],
                $norm_ct,
                $content_id,
                $percentage_score,
                $quiz_attempt['id']
            ]);

            $pdo->commit();

            if (function_exists('update_course_completion_status') && function_exists('promote_user_if_training_complete')) {
                $course_stmt = $pdo->prepare(
                    "SELECT course_id\n                    FROM training_course_content\n                    WHERE (content_type = ? OR content_type = '' OR content_type IS NULL)\n                      AND content_id = ?\n                    LIMIT 1"
                );
                $course_stmt->execute([$norm_ct, $content_id]);
                $course_data = $course_stmt->fetch(PDO::FETCH_ASSOC);

                if (!empty($course_data['course_id']) && function_exists('update_course_completion_status')) {
                    update_course_completion_status($pdo, $_SESSION['user_id'], intval($course_data['course_id']));
                }

                if (function_exists('promote_user_if_training_complete')) {
                    promote_user_if_training_complete($pdo, $_SESSION['user_id']);
                }
            }

            if (function_exists('auto_manage_user_roles')) {
                $role_status = auto_manage_user_roles($pdo, $_SESSION['user_id']);
                if (function_exists('log_debug') && !empty($role_status['changes'])) {
                    log_debug("Role management after mobile quiz: " . implode('; ', $role_status['changes']));
                }
            }

            header('Location: /mobile/quiz_results.php?attempt_id=' . $quiz_attempt['id']);
            exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = 'Error submitting quiz: ' . $e->getMessage();

        if (function_exists('log_debug')) {
            log_debug('Quiz submission error (mobile): ' . $e->getMessage() . ' - User ID: ' . $_SESSION['user_id'] . ', Quiz ID: ' . $quiz_id);
        }
    }
}

$questions = [];
if ($can_attempt && $quiz) {
    try {
        $stmt = $pdo->prepare(
            "SELECT\n                qq.id            AS question_id,\n                qq.quiz_id,\n                qq.question_text,\n                qq.question_image,\n                qq.question_type,\n                qq.question_order,\n                qq.points,\n                qq.is_active,\n                qq.created_at    AS question_created_at,\n                qq.updated_at    AS question_updated_at,\n                qac.id           AS choice_id,\n                qac.choice_text,\n                qac.is_correct,\n                qac.choice_order\n            FROM quiz_questions qq\n            JOIN quiz_answer_choices qac ON qq.id = qac.question_id\n            WHERE qq.quiz_id = ? AND qq.is_active = TRUE\n            ORDER BY qq.question_order, qq.id, qac.choice_order"
        );
        $stmt->execute([$quiz_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $question_id = intval($row['question_id']);
            if (!isset($questions[$question_id])) {
                $questions[$question_id] = [
                    'id'             => $question_id,
                    'question_text'  => $row['question_text'],
                    'question_image' => $row['question_image'] ?? null,
                    'points'         => intval($row['points']),
                    'choices'        => []
                ];
            }
            $questions[$question_id]['choices'][] = [
                'id'         => intval($row['choice_id']),
                'text'       => $row['choice_text'],
                'is_correct' => (bool) $row['is_correct'],
                'order'      => intval($row['choice_order'])
            ];
        }

        foreach ($questions as &$question) {
            usort($question['choices'], function ($a, $b) {
                return $a['order'] - $b['order'];
            });
        }
        unset($question);

        $questions = array_values($questions);
    } catch (PDOException $e) {
        $error_message = 'Error loading questions: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - ' . htmlspecialchars(SITE_NAME); ?></title>
    <link rel="preload" href="/assets/css/style.css?v=20260205" as="style">
    <link rel="stylesheet" href="/assets/css/style.css?v=20260205" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="/assets/css/style.css?v=20260205"></noscript>
    <style>
        body.mobile-body {
            margin: 0;
            padding: 0 0 80px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            color: #1a202c;
        }
        .mobile-shell {
            max-width: 900px;
            margin: 0 auto;
            padding: 16px;
        }
        .quiz-hero {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
            margin-bottom: 16px;
        }
        .quiz-hero h1 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 700;
        }
        .quiz-hero p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .quiz-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .quiz-meta .item {
            background: rgba(255,255,255,0.12);
            padding: 10px;
            border-radius: 12px;
            text-align: center;
        }
        .quiz-meta .label { font-size: 12px; opacity: 0.9; }
        .quiz-meta .value { font-size: 16px; font-weight: 700; }
        .alert {
            background: #fff4e5;
            color: #92400e;
            border: 1px solid #f6ad55;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 14px;
        }
        .error {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        .card {
            background: white;
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 12px;
        }
        .content-link a { color: #4c51bf; font-weight: 600; }
        .progress-dots { display: flex; flex-wrap: wrap; gap: 6px; }
        .progress-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #e2e8f0;
            border: 2px solid #cbd5e0;
            transition: all 0.2s ease;
        }
        .progress-dot.active { background: #667eea; border-color: #667eea; }
        .progress-dot.answered { background: #48bb78; border-color: #48bb78; }
        .timer {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            font-weight: 700;
            color: #1a202c;
            display: none;
        }
        .timer.warning { background: #fff5f5; border-color: #fc8181; color: #c53030; }
        .question-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        }
        .question-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .question-number {
            background: #667eea;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
        }
        .question-text { flex: 1; font-weight: 600; }
        .question-points {
            background: #e3f2fd;
            color: #1d4ed8;
            padding: 6px 10px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
        }
        .answer-choice {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            margin: 8px 0;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f8fafc;
        }
        .answer-choice:hover { border-color: #667eea; }
        .answer-choice.selected {
            border-color: #667eea;
            background: #eef2ff;
        }
        .answer-choice input[type="radio"] { transform: scale(1.1); }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 16px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-secondary { background: #e2e8f0; color: #2d3748; }
        .btn-success { background: #10b981; color: white; }
        .nav-row { display: flex; justify-content: space-between; gap: 10px; margin-top: 12px; }
        .mobile-step-controls { display: flex; align-items: center; gap: 10px; margin: 8px 0; }
        .mobile-step-label { flex: 1; text-align: center; font-weight: 600; }
        @media (max-width: 640px) {
            .mobile-shell { padding: 12px; }
            .question-header { flex-direction: column; align-items: flex-start; }
            .nav-row { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body class="mobile-body">
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <div class="mobile-shell">
        <div class="quiz-hero">
            <h1>Training Quiz</h1>
            <p>Answer each question and submit to record your progress.</p>
            <?php if ($quiz): ?>
            <div class="quiz-meta">
                <div class="item"><div class="label">Content</div><div class="value"><?php echo htmlspecialchars($quiz['content_name']); ?></div></div>
                <div class="item"><div class="label">Questions</div><div class="value"><?php echo count($questions); ?></div></div>
                <div class="item"><div class="label">Passing</div><div class="value"><?php echo $quiz['passing_score']; ?>%</div></div>
                <?php if (!empty($quiz['time_limit_minutes'])): ?>
                    <div class="item"><div class="label">Time Limit</div><div class="value"><?php echo $quiz['time_limit_minutes']; ?> min</div></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!$quiz): ?>
            <div class="card">
                <h3>Quiz Unavailable</h3>
                <p>This quiz could not be loaded.</p>
                <a class="btn btn-secondary" href="/mobile/training.php">Back to Training</a>
            </div>
        <?php elseif (!$can_attempt): ?>
            <div class="card">
                <h3>Access Denied</h3>
                <p>You don't have access to this quiz.</p>
                <a class="btn btn-secondary" href="/mobile/training.php">Back to Training</a>
            </div>
        <?php else: ?>
            <?php if ($quiz['time_limit_minutes']): ?>
                <div class="timer" id="quiz-timer">⏱️ Time Remaining: <span id="time-display"><?php echo $quiz['time_limit_minutes']; ?>:00</span></div>
            <?php endif; ?>

            <div class="card content-link">
                <p>📚 Review the related content before taking the quiz:</p>
                <a href="<?php echo htmlspecialchars($mobile_content_url); ?>">Open Content</a>
            </div>

            <div class="card">
                <div class="progress-dots" id="progress-dots">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="progress-dot" data-question="<?php echo $index + 1; ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (empty($questions)): ?>
                <div class="card">
                    <h3>No Questions Available</h3>
                    <p>Please contact your administrator.</p>
                    <a class="btn btn-secondary" href="/mobile/training.php">Back to Training</a>
                </div>
            <?php else: ?>
                <form method="POST" id="quiz-form">
                    <input type="hidden" name="action" value="submit_quiz">

                    <div class="mobile-step-controls">
                        <button type="button" class="btn btn-secondary" id="prev-question-btn">Previous</button>
                        <div class="mobile-step-label" id="mobile-step-label">Question 1 of <?php echo count($questions); ?></div>
                        <button type="button" class="btn btn-primary" id="next-question-btn">Next</button>
                    </div>

                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-card" data-question="<?php echo $index + 1; ?>" data-index="<?php echo $index; ?>">
                            <?php if (!empty($question['question_image'])): ?>
                                <div style="text-align:center;margin-bottom:10px;">
                                    <img src="/images/<?php echo htmlspecialchars($question['question_image']); ?>" alt="Question Image" style="max-width:100%;height:auto;border-radius:10px;border:1px solid #e2e8f0;">
                                </div>
                            <?php endif; ?>

                            <div class="question-header">
                                <div class="question-number"><?php echo $index + 1; ?></div>
                                <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                                <div class="question-points"><?php echo $question['points']; ?> pts</div>
                            </div>

                            <div class="answer-choices">
                                <?php foreach ($question['choices'] as $choice): ?>
                                    <div class="answer-choice" onclick="selectAnswer(this)">
                                        <input type="radio"
                                            name="answers[<?php echo intval($question['id']); ?>]"
                                            value="<?php echo intval($choice['id']); ?>"
                                            id="choice_<?php echo intval($choice['id']); ?>"
                                            onchange="updateProgress()">
                                        <label for="choice_<?php echo intval($choice['id']); ?>"><?php echo htmlspecialchars($choice['text']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mobile-step-controls">
                        <button type="button" class="btn btn-secondary" id="prev-question-btn-bottom">Previous</button>
                        <div class="mobile-step-label" id="mobile-step-label-bottom">Question 1 of <?php echo count($questions); ?></div>
                        <button type="button" class="btn btn-primary" id="next-question-btn-bottom">Next</button>
                    </div>

                    <div class="nav-row">
                        <a class="btn btn-secondary" href="/mobile/training.php">Back</a>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                            <span id="completion-status" style="color:#4a5568;">Answer all questions to submit</span>
                            <button type="submit" class="btn btn-success" id="submit-btn" disabled>Submit Quiz</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        const questions = document.querySelectorAll('.question-card');
        const progressDots = document.querySelectorAll('.progress-dot');
        const completionStatus = document.getElementById('completion-status');
        const submitBtn = document.getElementById('submit-btn');
        const nextBtns = [document.getElementById('next-question-btn'), document.getElementById('next-question-btn-bottom')].filter(Boolean);
        const prevBtns = [document.getElementById('prev-question-btn'), document.getElementById('prev-question-btn-bottom')].filter(Boolean);
        const labels = [document.getElementById('mobile-step-label'), document.getElementById('mobile-step-label-bottom')].filter(Boolean);
        let currentIndex = 0;

        function showQuestion(index) {
            questions.forEach((card, idx) => {
                card.style.display = idx === index ? 'block' : 'none';
            });
            progressDots.forEach((dot, idx) => {
                dot.classList.toggle('active', idx === index);
            });
            labels.forEach(label => {
                if (label) label.textContent = `Question ${index + 1} of ${questions.length}`;
            });
            if (progressDots[index]) progressDots[index].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }

        function selectAnswer(choiceEl) {
            const radio = choiceEl.querySelector('input[type="radio"]');
            if (!radio) return;
            radio.checked = true;
            const groupName = radio.name;
            document.querySelectorAll(`input[name="${groupName}"]`).forEach(input => {
                const parent = input.closest('.answer-choice');
                if (parent) parent.classList.remove('selected');
            });
            choiceEl.classList.add('selected');
            updateProgress();
        }

        function updateProgress() {
            const answers = document.querySelectorAll('.answer-choices');
            let answered = 0;

            answers.forEach((container, idx) => {
                const checked = container.querySelector('input[type="radio"]:checked');
                const dot = progressDots[idx];
                if (checked) {
                    answered++;
                    if (dot) dot.classList.add('answered');
                } else if (dot) {
                    dot.classList.remove('answered');
                }
            });

            const allAnswered = answered === answers.length && answers.length > 0;
            completionStatus.textContent = allAnswered ? 'All questions answered. Ready to submit.' : `Answered ${answered} of ${answers.length}`;
            submitBtn.disabled = !allAnswered;
        }

        nextBtns.forEach(btn => btn.addEventListener('click', () => {
            if (currentIndex < questions.length - 1) {
                currentIndex++;
                showQuestion(currentIndex);
            }
        }));

        prevBtns.forEach(btn => btn.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex--;
                showQuestion(currentIndex);
            }
        }));

        showQuestion(currentIndex);
        updateProgress();

        <?php if (!empty($quiz['time_limit_minutes'])): ?>
        (function() {
            const totalSeconds = <?php echo intval($quiz['time_limit_minutes']) * 60; ?>;
            let remaining = totalSeconds;
            const display = document.getElementById('time-display');
            const timerEl = document.getElementById('quiz-timer');
            if (!display || !timerEl) return;
            timerEl.style.display = 'block';

            const interval = setInterval(() => {
                remaining--;
                const minutes = Math.max(Math.floor(remaining / 60), 0);
                const seconds = Math.max(remaining % 60, 0).toString().padStart(2, '0');
                display.textContent = `${minutes}:${seconds}`;

                if (remaining <= 60) {
                    timerEl.classList.add('warning');
                }

                if (remaining <= 0) {
                    clearInterval(interval);
                    alert('Time is up! Submitting your quiz.');
                    document.getElementById('quiz-form').submit();
                }
            }, 1000);
        })();
        <?php endif; ?>
    </script>
</body>
</html>
