<?php
/**
 * Backfill quiz_retest_tracking for all completed quiz attempts.
 *
 * Restrict to admin/super_admin users and use prepared statements for safety.
 */

declare(strict_types=1);

@ini_set('display_errors', '0');
@set_time_limit(120);

$root = dirname(__DIR__);

require_once $root . '/includes/auth_check.php';
require_once $root . '/includes/db_connect.php';

if (file_exists($root . '/includes/training_helpers.php')) {
    require_once $root . '/includes/training_helpers.php';
}

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo 'Access denied. Admin only.';
    exit;
}

header('Content-Type: text/plain');

if (!function_exists('upsert_quiz_retest_tracking')) {
    echo "Missing retest helper. Nothing to do.\n";
    exit;
}

$sql = "
    SELECT
        t.user_id,
        t.quiz_id,
        t.completed_at,
        tq.retest_period_months
    FROM (
        SELECT
            user_id,
            quiz_id,
            MAX(completed_at) AS completed_at
        FROM user_quiz_attempts
        WHERE status IN ('passed', 'completed')
          AND completed_at IS NOT NULL
        GROUP BY user_id, quiz_id
    ) t
    JOIN training_quizzes tq ON tq.id = t.quiz_id
    WHERE tq.retest_period_months > 0
    ORDER BY t.user_id, t.quiz_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$processed = 0;
$errors = [];

foreach ($rows as $row) {
    $result = upsert_quiz_retest_tracking(
        $pdo,
        intval($row['user_id']),
        intval($row['quiz_id']),
        $row['completed_at']
    );

    if (($result['status'] ?? '') === 'success') {
        $processed++;
    } else {
        $errors[] = [
            'user_id' => intval($row['user_id']),
            'quiz_id' => intval($row['quiz_id']),
            'message' => $result['message'] ?? 'unknown error'
        ];
    }
}

echo "Backfill complete. Updated/checked {$processed} quiz/user pairs." . \PHP_EOL;

if (!empty($errors)) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "- User {$error['user_id']}, Quiz {$error['quiz_id']}: {$error['message']}\n";
    }
}

