<?php
/**
 * Quiz Taking Page
 * Interface for trainees to take training quizzes
 * Only accessible by training users for assigned content
 *
 * Created: 2025-11-06
 * Author: Claude Code Assistant
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';

// Load training helpers if available
if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

// --- BEGIN REPLACEMENT ---
$quiz_id     = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$content_id  = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
$content_type = isset($_GET['content_type']) ? trim(strtolower($_GET['content_type'])) : '';

$orig_content_id  = $content_id;
$orig_content_type = $content_type;

// Optional: normalize/guard content_type to known values
$allowed_ct = ['post','subcategory','category',''];
if (!in_array($content_type, $allowed_ct, true)) {
    $content_type = '';
}

if (function_exists('log_debug')) {
    log_debug(
        "take_quiz.php accessed - Quiz ID: {$quiz_id}, Content ID: {$content_id}, Content Type: '{$content_type}', User ID: " . ($_SESSION['user_id'] ?? 'none') . ", Time: " . date('Y-m-d H:i:s'),
        'INFO'
    );
}
// --- END REPLACEMENT ---




// Allow legacy flows to omit content_type/content_id; we’ll derive both after loading the quiz.
if ($quiz_id <= 0) {
    header('Location: training_dashboard.php');
    exit;
}

$page_title = 'Training Quiz';
$error_message = '';
$success_message = '';
$is_mobile_mode = (isset($_GET['mobile']) && $_GET['mobile'] === '1');

// Check if user is training user
if (!function_exists('is_training_user') || !is_training_user()) {
    header('Location: /index.php');
    exit;
}

// Get quiz information and verify user has access
$quiz = null;
$quiz_attempt = null;
$can_attempt = false;

try {
    // Get quiz details
    $stmt = $pdo->prepare("
        SELECT tq.*,
               tcc.content_id, tcc.content_type,
               CASE tcc.content_type
                   WHEN 'category' THEN c.name
                   WHEN 'subcategory' THEN sc.name
                   WHEN 'post' THEN p.title
               END as content_name,
               CASE tcc.content_type
                   WHEN 'category' THEN 'category.php?id='
                   WHEN 'subcategory' THEN '/categories/subcategory.php?id='
                   WHEN 'post' THEN '/posts/post.php?id='
               END as content_url
        FROM training_quizzes tq
        JOIN training_course_content tcc ON tq.content_id = tcc.content_id AND (tq.content_type = tcc.content_type OR tq.content_type = '' OR tq.content_type IS NULL)
        LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
        LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
        LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
        WHERE tq.id = ? AND tq.is_active = TRUE
    ");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (function_exists('log_debug')) {
        log_debug("Quiz query result for Quiz ID $quiz_id: " . json_encode($quiz));
        log_debug("Expected Content ID: $content_id, Expected Content Type: '$content_type'");
    }

    // --- BEGIN REPLACEMENT ---
if (!$quiz) {
    $error_message = 'Quiz not found or not active.';
    if (function_exists('log_debug')) {
        log_debug("Quiz not found - Quiz ID: $quiz_id");
    }
} else {
    // Normalize/derive content types for comparison and access checks
    $provided_ct = strtolower(trim((string)$content_type));
    $quiz_ct     = strtolower(trim((string)$quiz['content_type']));

    // If URL omitted content_type, derive it: legacy blank quiz types default to 'post'
    if ($provided_ct === '') {
        $provided_ct  = ($quiz_ct !== '') ? $quiz_ct : 'post';
        $content_type = $provided_ct; // keep consistent for later steps/logs
    }

    // Validate content_id and content_type with legacy allowance:
    // Accept when quiz_ct == provided_ct, or quiz_ct is blank AND provided_ct is 'post'
    $ct_matches = ($quiz_ct === $provided_ct) || ($quiz_ct === '' && $provided_ct === 'post');

    // Normalize/auto-correct URL params to the quiz's true content when mismatched or omitted.
$provided_was_missing = ($content_id <= 0 || $provided_ct === '');
if ($quiz['content_id'] != $content_id || !$ct_matches || $provided_was_missing) {

    // Adopt the quiz's canonical content mapping
    $content_id  = intval($quiz['content_id']);
    $quiz_ct     = strtolower(trim((string)$quiz['content_type']));
    $content_type = ($quiz_ct !== '') ? $quiz_ct : 'post'; // legacy blank => 'post'
    $ct_matches   = true; // by definition now normalized

    if (function_exists('log_debug')) {
        log_debug("Auto-corrected quiz URL params -> content_id={$content_id}, content_type='{$content_type}'");
    }
}

// Verify access after normalization
if (function_exists('is_assigned_training_content')) {
    $can_attempt = is_assigned_training_content($pdo, $_SESSION['user_id'], $content_id, $content_type);
} else {
    $can_attempt = true; // Fallback if helper is unavailable
}

if (!$can_attempt) {
    $error_message = 'You do not have access to this quiz.';
}
}
// --- END REPLACEMENT ---


    // Check for retest eligibility and existing attempts
    if ($can_attempt) {
        // First check for existing in-progress attempt
        $stmt = $pdo->prepare("
            SELECT * FROM user_quiz_attempts
            WHERE user_id = ? AND quiz_id = ? AND status = 'in_progress'
            ORDER BY started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);
        $quiz_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no in-progress attempt, check if user can start a new attempt (retest logic)
        if (!$quiz_attempt) {
            $can_create_new_attempt = false;

            // Get the most recent completed attempt
            $stmt = $pdo->prepare("
                SELECT * FROM user_quiz_attempts
                WHERE user_id = ? AND quiz_id = ? AND status IN ('passed', 'failed')
                ORDER BY completed_at DESC
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id'], $quiz_id]);
            $last_completed_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$last_completed_attempt) {
                // No previous attempts - first time taking quiz
                $can_create_new_attempt = true;
            } else {
                // Check retest eligibility based on quiz settings
                $retest_months = intval($quiz['retest_period_months'] ?? 0);

                if ($retest_months <= 0) {
                    // No retest restriction - can retake anytime
                    $can_create_new_attempt = true;
                } else {
                    // Calculate when retest becomes available
                    $completed_at = new DateTime($last_completed_attempt['completed_at']);
                    $retest_available_at = clone $completed_at;
                    $retest_available_at->add(new DateInterval("P{$retest_months}M"));
                    $now = new DateTime();

                    if ($now >= $retest_available_at) {
                        // Retest period has passed
                        $can_create_new_attempt = true;
                    } else {
                        // Retest period not yet passed - show error
                        $days_until_retest = $now->diff($retest_available_at)->days;
                        $error_message = "This quiz requires a waiting period of {$retest_months} month(s) before retaking. You can retake this quiz in {$days_until_retest} day(s).";

                        if (function_exists('log_debug')) {
                            log_debug("Retest blocked - User {$_SESSION['user_id']}, Quiz {$quiz_id}, Completed at: {$last_completed_attempt['completed_at']}, Retest available: {$retest_available_at->format('Y-m-d H:i:s')}");
                        }
                    }
                }
            }

            // Create new attempt if allowed
            if ($can_create_new_attempt) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_quiz_attempts
                    (user_id, quiz_id, attempt_number, status, started_at)
                    VALUES (?, ?, (
                        SELECT COALESCE(MAX(attempt_number), 0) + 1
                        FROM user_quiz_attempts
                        WHERE user_id = ? AND quiz_id = ?
                    ), 'in_progress', CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$_SESSION['user_id'], $quiz_id, $_SESSION['user_id'], $quiz_id]);

                $attempt_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    SELECT * FROM user_quiz_attempts WHERE id = ?
                ");
                $stmt->execute([$attempt_id]);
                $quiz_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

                if (function_exists('log_debug')) {
                    log_debug("Created new quiz attempt - User {$_SESSION['user_id']}, Quiz {$quiz_id}, Attempt ID: {$attempt_id}");
                }
            }
        }
    }

} catch (PDOException $e) {
    $error_message = 'Error loading quiz: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_attempt && $quiz_attempt) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'submit_quiz') {
        $answers = isset($_POST['answers']) ? $_POST['answers'] : [];

        if (empty($answers)) {
            $error_message = 'Please answer all questions before submitting.';
        } else {
            try {
                $pdo->beginTransaction();

                // Get quiz questions and correct answers
                $stmt = $pdo->prepare("
                    SELECT qq.id, qq.points, qac.id as choice_id, qac.is_correct
                    FROM quiz_questions qq
                    JOIN quiz_answer_choices qac ON qq.id = qac.question_id
                    WHERE qq.quiz_id = ? AND qq.is_active = TRUE
                    ORDER BY qq.question_order, qq.id
                ");
                $stmt->execute([$quiz_id]);
                $question_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate score
                $total_points = 0;
                $earned_points = 0;
                $correct_answers = 0;
                $total_questions = 0;

                $questions = [];
                foreach ($question_data as $data) {
                    if (!isset($questions[$data['id']])) {
                        $questions[$data['id']] = [
                            'points' => $data['points'],
                            'correct_choice' => null,
                            'user_choice' => null
                        ];
                        $total_points += $data['points'];
                        $total_questions++;
                    }

                    if ($data['is_correct']) {
                        $questions[$data['id']]['correct_choice'] = $data['choice_id'];
                    }
                }

                // Process user answers
                $user_answers = [];
                foreach ($answers as $question_id => $choice_id) {
                    if (isset($questions[$question_id])) {
                        $questions[$question_id]['user_choice'] = $choice_id;

                        // Save answer
                        $stmt = $pdo->prepare("
                            INSERT INTO user_quiz_answers
                            (attempt_id, question_id, selected_choice_id, is_correct, points_earned)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                            selected_choice_id = VALUES(selected_choice_id),
                            is_correct = VALUES(is_correct),
                            points_earned = VALUES(points_earned),
                            answered_at = CURRENT_TIMESTAMP
                        ");

                        $is_correct = ($questions[$question_id]['correct_choice'] == $choice_id);
                        $points_earned = $is_correct ? $questions[$question_id]['points'] : 0;

                        $stmt->execute([$quiz_attempt['id'], $question_id, $choice_id, $is_correct, $points_earned]);

                        if ($is_correct) {
                            $earned_points += $questions[$question_id]['points'];
                            $correct_answers++;
                        }
                    }
                }

                // Calculate percentage score
                $score = $total_points > 0 ? round(($earned_points / $total_points) * 100) : 0;
                $status = ($score >= $quiz['passing_score']) ? 'passed' : 'failed';

                // Update attempt FIRST - this is the source of truth
                $stmt = $pdo->prepare("
                    UPDATE user_quiz_attempts
                    SET status = ?, score = ?, total_points = ?, earned_points = ?,
                        completed_at = CURRENT_TIMESTAMP,
                        time_taken_minutes = TIMESTAMPDIFF(MINUTE, started_at, CURRENT_TIMESTAMP)
                    WHERE id = ?
                ");
                $stmt->execute([$status, $score, $total_points, $earned_points, $quiz_attempt['id']]);

                // Verify the quiz attempt was actually updated before proceeding
                $verify_stmt = $pdo->prepare("
                    SELECT status, completed_at FROM user_quiz_attempts
                    WHERE id = ? AND status IN ('passed', 'failed') AND completed_at IS NOT NULL
                ");
                $verify_stmt->execute([$quiz_attempt['id']]);
                $attempt_verified = $verify_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$attempt_verified) {
                    throw new PDOException('Failed to properly update quiz attempt record');
                }

                // If passed, update training progress
                if ($status === 'passed') {
                    if (function_exists('log_debug')) {
                        log_debug("Updating training progress - User ID: " . $_SESSION['user_id'] . ", Content Type: '$content_type', Content ID: $content_id, Score: $score");
                    }

                    // Normalize content_type to handle legacy blanks/nulls consistently
$norm_ct = trim(strtolower((string)$content_type));
if ($norm_ct === '') { $norm_ct = 'post'; }

// 1) Try a flexible UPDATE first (works whether legacy rows used ''/NULL or proper type)
$upd = $pdo->prepare("
    UPDATE training_progress
       SET quiz_completed = TRUE,
           quiz_score = ?,
           quiz_completed_at = CURRENT_TIMESTAMP,
           last_quiz_attempt_id = ?,
           status = 'completed',
           completion_date = CURRENT_TIMESTAMP
     WHERE user_id = ?
       AND content_id = ?
       AND (content_type = ? OR content_type = '' OR content_type IS NULL)
");
$upd->execute([$score, $quiz_attempt['id'], $_SESSION['user_id'], $content_id, $norm_ct]);

$rows = $upd->rowCount();

if ($rows === 0) {
    // 2) No existing row? Seed one now (idempotent on retries within the same attempt).
    $ins = $pdo->prepare("
        INSERT INTO training_progress
            (user_id, content_id, content_type, status,
             quiz_completed, quiz_score, quiz_completed_at,
             last_quiz_attempt_id, completion_date)
        VALUES
            (?, ?, ?, 'completed',
             TRUE, ?, CURRENT_TIMESTAMP,
             ?, CURRENT_TIMESTAMP)
    ");
    $ins->execute([$_SESSION['user_id'], $content_id, $norm_ct, $score, $quiz_attempt['id']]);
    $rows = $ins->rowCount();
}

// Verify the training progress was actually updated
$verify_progress_stmt = $pdo->prepare("
    SELECT status, quiz_completed FROM training_progress
    WHERE user_id = ? AND content_id = ?
      AND (content_type = ? OR content_type = '' OR content_type IS NULL)
      AND status = 'completed' AND quiz_completed = TRUE
");
$verify_progress_stmt->execute([$_SESSION['user_id'], $content_id, $norm_ct]);
$progress_verified = $verify_progress_stmt->fetch(PDO::FETCH_ASSOC);

if (!$progress_verified) {
    throw new PDOException('Failed to properly update training progress record');
}

if (function_exists('log_debug')) {
    log_debug('Training progress write - mode=' . ($rows ? 'ok' : 'no-op') .
              ", user_id={$_SESSION['user_id']}, content_id={$content_id}, ct='{$norm_ct}', rows={$rows}");
}

// Mirror completion to the originating POST row so the post UI/header sees it.
$orig_ct_norm = trim(strtolower((string)$orig_content_type));
if ($orig_ct_norm === '') { $orig_ct_norm = 'post'; }

if ($orig_ct_norm === 'post' && $orig_content_id > 0) {
    // Skip if we're already writing to that same post row
    $is_same_row = ($norm_ct === 'post' && intval($content_id) === intval($orig_content_id));

    if (!$is_same_row) {
        if (function_exists('log_debug')) {
            log_debug("Mirroring completion to originating post row -> post_id={$orig_content_id}, user_id={$_SESSION['user_id']}");
        }

        // UPDATE first (accept legacy ''/NULL), then INSERT if needed (idempotent)
        $upd2 = $pdo->prepare("
            UPDATE training_progress
               SET quiz_completed = TRUE,
                   quiz_score = ?,
                   quiz_completed_at = CURRENT_TIMESTAMP,
                   last_quiz_attempt_id = ?,
                   status = 'completed',
                   completion_date = CURRENT_TIMESTAMP
             WHERE user_id = ?
               AND content_id = ?
               AND (content_type = 'post' OR content_type = '' OR content_type IS NULL)
        ");
        $upd2->execute([$score, $quiz_attempt['id'], $_SESSION['user_id'], $orig_content_id]);

        if ($upd2->rowCount() === 0) {
            $ins2 = $pdo->prepare("
                INSERT INTO training_progress
                    (user_id, content_id, content_type, status,
                     quiz_completed, quiz_score, quiz_completed_at,
                     last_quiz_attempt_id, completion_date)
                VALUES
                    (?, ?, 'post', 'completed',
                     TRUE, ?, CURRENT_TIMESTAMP,
                     ?, CURRENT_TIMESTAMP)
            ");
            $ins2->execute([$_SESSION['user_id'], $orig_content_id, $score, $quiz_attempt['id']]);
        }

        // Optional: snapshot for logs
        if (function_exists('log_debug')) {
            $chk2 = $pdo->prepare("
                SELECT user_id, content_id, content_type, status, quiz_completed, quiz_score,
                       last_quiz_attempt_id, quiz_completed_at, completion_date
                  FROM training_progress
                 WHERE user_id = ? AND content_id = ?
                   AND (content_type = 'post' OR content_type = '' OR content_type IS NULL)
                 LIMIT 1
            ");
            $chk2->execute([$_SESSION['user_id'], $orig_content_id]);
            $tp_row2 = $chk2->fetch(PDO::FETCH_ASSOC);
            log_debug('Post-origin progress snapshot: ' . json_encode($tp_row2));
        }
    }
}
} else {
    // User failed the quiz - still update training progress to reflect the attempt
    if (function_exists('log_debug')) {
        log_debug("User failed quiz - updating training progress with attempt info - User ID: " . $_SESSION['user_id'] . ", Content Type: '$content_type', Content ID: $content_id, Score: $score");
    }

    // Normalize content_type to handle legacy blanks/nulls consistently
    $norm_ct = trim(strtolower((string)$content_type));
    if ($norm_ct === '') { $norm_ct = 'post'; }

    // Update training progress to reflect quiz attempt (but keep as in_progress since they failed)
    $upd_failed = $pdo->prepare("
        UPDATE training_progress
           SET last_quiz_attempt_id = ?,
               quiz_score = ?,
               quiz_completed_at = CURRENT_TIMESTAMP,
               status = 'in_progress'
         WHERE user_id = ?
           AND content_id = ?
           AND (content_type = ? OR content_type = '' OR content_type IS NULL)
    ");
    $upd_failed->execute([$quiz_attempt['id'], $score, $_SESSION['user_id'], $content_id, $norm_ct]);

    if ($upd_failed->rowCount() === 0) {
        // No existing row? Seed one with in_progress status
        $ins_failed = $pdo->prepare("
            INSERT INTO training_progress
                (user_id, content_id, content_type, status,
                 quiz_completed, quiz_score, quiz_completed_at,
                 last_quiz_attempt_id)
            VALUES
                (?, ?, ?, 'in_progress',
                 FALSE, ?, CURRENT_TIMESTAMP,
                 ?)
        ");
        $ins_failed->execute([$_SESSION['user_id'], $content_id, $norm_ct, $score, $quiz_attempt['id']]);
    }

    if (function_exists('log_debug')) {
        log_debug('Failed quiz training progress updated - user_id=' . $_SESSION['user_id'] .
                  ', content_id=' . $content_id . ', score=' . $score . ', status=in_progress');
    }
}
                // Commit essential quiz operations before role management
                $pdo->commit();

                // Now handle post-transaction operations (role management, etc.)
                if (function_exists('update_course_completion_status') && function_exists('promote_user_if_training_complete')) {
                    // Get course ID for this content
                    $course_stmt = $pdo->prepare("\n    SELECT course_id\n    FROM training_course_content\n    WHERE (content_type = ? OR content_type = '' OR content_type IS NULL)\n      AND content_id = ?\n    LIMIT 1\n");
                    $course_stmt->execute([$norm_ct, $content_id]);
                    $course_data = $course_stmt->fetch(PDO::FETCH_ASSOC);

                    if (!empty($course_data['course_id']) && function_exists('update_course_completion_status')) {
                        update_course_completion_status($pdo, $_SESSION['user_id'], intval($course_data['course_id']));
                    }

                    // Even if we couldn't resolve course_id (legacy/blank content_type cases),
                    // still evaluate global completion for promotion.
                    if (function_exists('promote_user_if_training_complete')) {
                        promote_user_if_training_complete($pdo, $_SESSION['user_id']);
                    }
                }

                // Trigger automatic role management
                if (function_exists('auto_manage_user_roles')) {
                    $role_status = auto_manage_user_roles($pdo, $_SESSION['user_id']);
                    if (function_exists('log_debug') && !empty($role_status['changes'])) {
                        log_debug("Role management after quiz: " . implode('; ', $role_status['changes']));
                    }
                }

                // Redirect to results page
                header('Location: quiz_results.php?attempt_id=' . $quiz_attempt['id']);
                exit;
            } catch (PDOException $e) {
                // Proper error handling - rollback if transaction is still active
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = 'Error submitting quiz: ' . $e->getMessage();

                if (function_exists('log_debug')) {
                    log_debug('Quiz submission error: ' . $e->getMessage() . ' - User ID: ' . $_SESSION['user_id'] . ', Quiz ID: ' . $quiz_id);
                }
            }
        }
    }
}

// Get quiz questions for display
$questions = [];
if ($can_attempt && $quiz) {
    try {
        // Explicitly alias the question id to avoid ambiguity
        $stmt = $pdo->prepare("
    SELECT
        qq.id            AS question_id,
        qq.quiz_id,
        qq.question_text,
        qq.question_image,
        qq.question_type,
        qq.question_order,
        qq.points,
        qq.is_active,
        qq.created_at    AS question_created_at,
        qq.updated_at    AS question_updated_at,

        qac.id           AS choice_id,
        qac.choice_text,
        qac.is_correct,
        qac.choice_order
    FROM quiz_questions qq
    JOIN quiz_answer_choices qac ON qq.id = qac.question_id
    WHERE qq.quiz_id = ? AND qq.is_active = TRUE
    ORDER BY qq.question_order, qq.id, qac.choice_order
");
        $stmt->execute([$quiz_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by question using the explicit alias
        foreach ($results as $row) {
            $question_id = intval($row['question_id']);
            if (!isset($questions[$question_id])) {
                $questions[$question_id] = [
    'id'              => $question_id, // keep 'id' for the renderer below
    'question_text'   => $row['question_text'],
    'question_image'  => $row['question_image'] ?? null,
    'points'          => intval($row['points']),
    'choices'         => []
];
            }
            $questions[$question_id]['choices'][] = [
                'id'         => intval($row['choice_id']),
                'text'       => $row['choice_text'],
                'is_correct' => (bool)$row['is_correct'],
                'order'      => intval($row['choice_order'])
            ];
        }

        // Sort choices by order
        foreach ($questions as &$question) {
            usort($question['choices'], function($a, $b) {
                return $a['order'] - $b['order'];
            });
        }
        unset($question);

        // Reindex questions to 0..N-1 so $index in the render loop is positional, not the DB id
        $questions = array_values($questions);

    } catch (PDOException $e) {
        $error_message = 'Error loading questions: ' . $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.quiz-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.mobile-quiz-mode {
    padding-bottom: 40px;
}

.offline-banner {
    background: #fff4e5;
    border: 1px solid #ffd7a8;
    color: #8a5b00;
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 14px;
}

.quiz-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.quiz-header h1 {
    margin: 0 0 15px 0;
    font-size: 28px;
}

.quiz-info {
    background: rgba(255,255,255,0.2);
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.quiz-info-row {
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 15px;
}

.quiz-info-item {
    text-align: center;
}

.quiz-info-label {
    font-size: 12px;
    opacity: 0.9;
    margin-bottom: 5px;
}

.quiz-info-value {
    font-size: 18px;
    font-weight: bold;
}

.timer {
    background: #ffc107;
    color: #212529;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: bold;
    margin: 20px 0;
    text-align: center;
    display: none;
}

.timer.warning {
    background: #dc3545;
    color: white;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.question-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.question-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.question-number {
    background: #667eea;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
    margin-right: 15px;
}

.question-text {
    font-size: 18px;
    font-weight: 500;
    color: #333;
    flex: 1;
}

.question-points {
    background: #e3f2fd;
    color: #1976d2;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 14px;
    font-weight: 500;
}

.answer-choices {
    margin: 20px 0;
}

.answer-choice {
    display: flex;
    align-items: center;
    padding: 15px;
    margin: 10px 0;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.answer-choice:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.answer-choice.selected {
    border-color: #667eea;
    background: #f8f9ff;
}

.answer-choice input[type="radio"] {
    margin-right: 15px;
    transform: scale(1.2);
}

.answer-choice label {
    flex: 1;
    cursor: pointer;
    font-size: 16px;
    color: #333;
}

.mobile-quiz-mode .answer-choice,
@media (max-width: 768px) {
    .answer-choice {
        padding: 18px;
        border-width: 3px;
    }

    .answer-choice label {
        font-size: 18px;
    }
}

.mobile-step-controls {
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 12px 0 18px;
}

.mobile-quiz-mode .mobile-step-controls {
    display: flex;
}

.mobile-step-controls .step-button {
    flex: 1;
    padding: 14px 10px;
    font-size: 16px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

.mobile-step-controls .step-button.primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border: none;
}

.mobile-step-label {
    flex: 1;
    text-align: center;
    font-weight: 600;
    font-size: 14px;
}

.quiz-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e9ecef;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a6fd8;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.progress-indicator {
    text-align: center;
    margin: 20px 0;
}

.progress-indicator .progress-text {
    color: #666;
    font-size: 14px;
    margin-bottom: 10px;
}

.progress-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.progress-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e9ecef;
    transition: all 0.2s ease;
}

.progress-dot.completed {
    background: #28a745;
}

.progress-dot.current {
    background: #667eea;
    transform: scale(1.3);
}

.mobile-quiz-mode .question-card {
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
}

.mobile-hidden {
    display: none !important;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #495057;
}

.content-navigation {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.content-navigation a {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
}

.content-navigation a:hover {
    text-decoration: underline;
}
</style>

<div class="quiz-container<?php echo $is_mobile_mode ? ' mobile-quiz-mode' : ''; ?>" data-mobile-mode="<?php echo $is_mobile_mode ? '1' : '0'; ?>">
    <div id="offline-banner" class="offline-banner" style="display:none;">
        <strong>Offline:</strong> Quiz is read-only until you're back online. Submit and navigation are disabled.
    </div>
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            <br><br>
            <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    <?php elseif (!$can_attempt): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🚫</div>
            <h3>Access Denied</h3>
            <p>You don't have access to this quiz.</p>
            <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    <?php elseif ($quiz): ?>
        <!-- Quiz Header -->
        <div class="quiz-header">
            <h1>📝 Training Quiz</h1>
            <div class="quiz-info">
                <div class="quiz-info-row">
                    <div class="quiz-info-item">
                        <div class="quiz-info-label">Content</div>
                        <div class="quiz-info-value"><?php echo htmlspecialchars($quiz['content_name']); ?></div>
                    </div>
                    <div class="quiz-info-item">
                        <div class="quiz-info-label">Questions</div>
                        <div class="quiz-info-value"><?php echo count($questions); ?></div>
                    </div>
                    <div class="quiz-info-item">
                        <div class="quiz-info-label">Passing Score</div>
                        <div class="quiz-info-value"><?php echo $quiz['passing_score']; ?>%</div>
                    </div>
                    <?php if ($quiz['time_limit_minutes']): ?>
                    <div class="quiz-info-item">
                        <div class="quiz-info-label">Time Limit</div>
                        <div class="quiz-info-value"><?php echo $quiz['time_limit_minutes']; ?> min</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Timer (if time limit) -->
        <?php if ($quiz['time_limit_minutes']): ?>
        <div class="timer" id="quiz-timer">
            ⏱️ Time Remaining: <span id="time-display"><?php echo $quiz['time_limit_minutes']; ?>:00</span>
        </div>
        <?php endif; ?>

        <!-- Progress Indicator -->
        <div class="progress-indicator">
            <div class="progress-text">Question Progress</div>
            <div class="progress-dots">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="progress-dot" data-question="<?php echo $index + 1; ?>"></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content Navigation -->
        <div class="content-navigation">
            <p>📚 Read the content before taking the quiz:</p>
            <a href="<?php echo htmlspecialchars($quiz['content_url'] . $quiz['content_id']); ?>">
                View <?php echo ucfirst($quiz['content_type']); ?> Content
            </a>
        </div>

        <?php if (empty($questions)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">❓</div>
                <h3>No Questions Available</h3>
                <p>This quiz doesn't have any questions yet. Please contact your administrator.</p>
                <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        <?php else: ?>
            <form method="POST" id="quiz-form">
                <input type="hidden" name="action" value="submit_quiz">

                <div class="mobile-step-controls" id="mobile-step-controls-top">
                    <button type="button" class="step-button" id="prev-question-btn">Previous</button>
                    <div class="mobile-step-label" id="mobile-step-label">Question 1 of <?php echo count($questions); ?></div>
                    <button type="button" class="step-button primary" id="next-question-btn">Next</button>
                </div>

                <?php foreach ($questions as $index => $question): ?>
    <div class="question-card" data-question="<?php echo $index + 1; ?>" data-index="<?php echo $index; ?>">

        <?php if (!empty($question['question_image'])): ?>
            <div class="quiz-question-image" style="text-align:center;margin-bottom:12px;">
                <img src="images/<?php echo htmlspecialchars($question['question_image']); ?>"
                     alt="Question Image"
                     style="max-width:100%;height:auto;border-radius:8px;border:1px solid #e9ecef;">
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
    <label for="choice_<?php echo intval($choice['id']); ?>">
        <?php echo htmlspecialchars($choice['text']); ?>
    </label>
</div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="mobile-step-controls" id="mobile-step-controls-bottom">
                    <button type="button" class="step-button" id="prev-question-btn-bottom">Previous</button>
                    <div class="mobile-step-label" id="mobile-step-label-bottom">Question 1 of <?php echo count($questions); ?></div>
                    <button type="button" class="step-button primary" id="next-question-btn-bottom">Next</button>
                </div>

                <!-- Quiz Navigation -->
                <div class="quiz-navigation">
                    <a href="training_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                    <div>
                        <span id="completion-status" style="color: #666; margin-right: 15px;">
                            Answer all questions to submit
                        </span>
                        <button type="submit" class="btn btn-success" id="submit-btn" disabled>
                            Submit Quiz
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
let timeLimit = <?php echo $quiz['time_limit_minutes'] ?? 0; ?>;
let timeRemaining = timeLimit * 60; // Convert to seconds
let timerInterval = null;
const quizContainer = document.querySelector('.quiz-container');
const isMobileMode = ((quizContainer && quizContainer.dataset.mobileMode === '1') || window.matchMedia('(max-width: 768px)').matches);
const questionCards = Array.from(document.querySelectorAll('.question-card'));
let currentQuestionIndex = 0;
let offlineMode = !navigator.onLine;

function syncStepLabels() {
    const topLabel = document.getElementById('mobile-step-label');
    const bottomLabel = document.getElementById('mobile-step-label-bottom');
    const labelText = `Question ${currentQuestionIndex + 1} of ${questionCards.length}`;

    if (topLabel) topLabel.textContent = labelText;
    if (bottomLabel) bottomLabel.textContent = labelText;
}

function updateStepControls() {
    const controls = document.querySelectorAll('.mobile-step-controls');
    const prevButtons = [
        document.getElementById('prev-question-btn'),
        document.getElementById('prev-question-btn-bottom')
    ];
    const nextButtons = [
        document.getElementById('next-question-btn'),
        document.getElementById('next-question-btn-bottom')
    ];

    if (!isMobileMode || questionCards.length <= 1) {
        controls.forEach(el => el.classList.add('mobile-hidden'));
        questionCards.forEach(card => card.classList.remove('mobile-hidden'));
        return;
    }

    controls.forEach(el => el.classList.remove('mobile-hidden'));

    questionCards.forEach((card, idx) => {
        card.classList.toggle('mobile-hidden', idx !== currentQuestionIndex);
    });

    const onFirst = currentQuestionIndex === 0;
    const onLast = currentQuestionIndex === questionCards.length - 1;

    prevButtons.forEach(btn => { if (btn) btn.disabled = onFirst || offlineMode; });
    nextButtons.forEach(btn => {
        if (btn) {
            btn.disabled = offlineMode;
            btn.textContent = onLast ? 'Go to Submit' : 'Next';
        }
    });

    syncStepLabels();
}

function showQuestion(index) {
    if (index < 0 || index >= questionCards.length) return;
    currentQuestionIndex = index;
    updateStepControls();
    updateProgress();
}

function attachStepHandlers() {
    const nextButtons = [
        document.getElementById('next-question-btn'),
        document.getElementById('next-question-btn-bottom')
    ];
    const prevButtons = [
        document.getElementById('prev-question-btn'),
        document.getElementById('prev-question-btn-bottom')
    ];

    nextButtons.forEach(btn => {
        if (btn) {
            btn.addEventListener('click', () => {
                if (currentQuestionIndex < questionCards.length - 1) {
                    showQuestion(currentQuestionIndex + 1);
                } else {
                    document.getElementById('submit-btn')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    });

    prevButtons.forEach(btn => {
        if (btn) {
            btn.addEventListener('click', () => {
                showQuestion(currentQuestionIndex - 1);
            });
        }
    });
}

// Timer functionality
if (timeLimit > 0) {
    const timerElement = document.getElementById('quiz-timer');
    const timeDisplay = document.getElementById('time-display');

    timerElement.style.display = 'block';

    timerInterval = setInterval(function() {
        timeRemaining--;

        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        if (timeRemaining <= 300 && timeRemaining > 60) { // 5 minutes warning
            timerElement.classList.add('warning');
        }

        if (timeRemaining <= 60) { // 1 minute critical
            timeDisplay.style.color = 'white';
        }

        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            // Auto-submit when time runs out
            document.getElementById('quiz-form').submit();
        }
    }, 1000);
}

function setOfflineState(isOffline) {
    offlineMode = isOffline;
    const offlineBanner = document.getElementById('offline-banner');
    const submitBtn = document.getElementById('submit-btn');
    const radios = document.querySelectorAll('.answer-choice input[type="radio"]');

    if (offlineBanner) {
        offlineBanner.style.display = isOffline ? 'block' : 'none';
    }

    radios.forEach(radio => {
        radio.disabled = isOffline;
    });

    if (submitBtn) {
        submitBtn.disabled = true;
    }

    updateStepControls();
    updateProgress();
}

window.addEventListener('online', () => setOfflineState(false));
window.addEventListener('offline', () => setOfflineState(true));

// Answer selection
function selectAnswer(element) {
    if (offlineMode) {
        return;
    }
    // Restrict to this question block
    const questionCard = element.closest('.question-card');

    // Remove selected class from all choices in this question
    questionCard.querySelectorAll('.answer-choice').forEach(choice => {
        choice.classList.remove('selected');
    });

    // Add selected class to clicked choice
    element.classList.add('selected');

    // Also tick the actual radio inside this row to avoid relying on label click
    const radio = element.querySelector('input[type="radio"]');
    if (radio) {
        radio.checked = true;
    }

    // Update progress
    updateProgress();

    if (isMobileMode && currentQuestionIndex < questionCards.length - 1) {
        showQuestion(currentQuestionIndex + 1);
    }
}

// Update progress and submit button
function updateProgress() {
    const totalQuestions = <?php echo count($questions); ?>;
    const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;

    // Update progress dots
    document.querySelectorAll('.progress-dot').forEach((dot, index) => {
        const isCurrent = isMobileMode ? index === currentQuestionIndex : index === answeredQuestions;

        if (index < answeredQuestions) {
            dot.classList.add('completed');
        } else {
            dot.classList.remove('completed');
        }

        if (isCurrent) {
            dot.classList.add('current');
        } else {
            dot.classList.remove('current');
        }
    });

    // Update submit button
    const submitBtn = document.getElementById('submit-btn');
    const statusText = document.getElementById('completion-status');

    if (offlineMode) {
        if (submitBtn) submitBtn.disabled = true;
        if (statusText) {
            statusText.textContent = 'Offline mode - reconnect to submit';
            statusText.style.color = '#b45309';
        }
        return;
    }

    if (answeredQuestions === totalQuestions) {
        submitBtn.disabled = false;
        statusText.textContent = 'All questions answered ✓';
        statusText.style.color = '#28a745';
    } else {
        submitBtn.disabled = true;
        statusText.textContent = `${answeredQuestions} of ${totalQuestions} questions answered`;
        statusText.style.color = '#666';
    }
}

// Handle form submission
document.getElementById('quiz-form').addEventListener('submit', function(e) {
    const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;
    const totalQuestions = <?php echo count($questions); ?>;

    if (offlineMode) {
        e.preventDefault();
        alert('You are offline. Please reconnect before submitting the quiz.');
        return false;
    }

    if (answeredQuestions < totalQuestions) {
        e.preventDefault();
        alert('Please answer all questions before submitting the quiz.');
        return false;
    }

    if (confirm('Are you ready to submit your quiz? You cannot change your answers after submitting.')) {
        // Clear timer if running
        if (timerInterval) {
            clearInterval(timerInterval);
        }

        // Show loading state
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.textContent = 'Submitting...';
        submitBtn.disabled = true;

        return true;
    } else {
        e.preventDefault();
        return false;
    }
});

// Initialize progress on page load
attachStepHandlers();
updateStepControls();
setOfflineState(offlineMode);
updateProgress();

// Clean up timer on page unload
window.addEventListener('beforeunload', function() {
    if (timerInterval) {
        clearInterval(timerInterval);
    }
});
</script>


