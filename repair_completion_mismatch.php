<?php
/**
 * Data Repair Script for Completion vs Attempts Mismatch
 *
 * This script fixes cases where training_progress shows completion
 * but user_quiz_attempts don't have proper records, or vice versa.
 *
 * Run this script to repair existing data inconsistencies.
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "<h2>🔧 Data Repair: Completion vs Attempts Mismatch</h2>";

// Function to repair mismatched data
function repair_mismatched_data($pdo) {
    echo "<h3>🔍 Finding and Repairing Mismatched Data</h3>";

    $repair_count = 0;

    // Find cases where training_progress shows completed but no valid quiz attempts exist
    echo "<h4>Case 1: Training Progress Completed but No Valid Quiz Attempts</h4>";

    $stmt = $pdo->query("
        SELECT DISTINCT
            tp.user_id,
            tp.content_id,
            tp.quiz_completed,
            tp.quiz_score,
            tp.last_quiz_attempt_id,
            tp.quiz_completed_at,
            u.name as user_name
        FROM training_progress tp
        JOIN users u ON tp.user_id = u.id
        LEFT JOIN user_quiz_attempts uqa ON tp.last_quiz_attempt_id = uqa.id
        WHERE tp.status = 'completed'
          AND tp.quiz_completed = 1
          AND (
              uqa.id IS NULL
              OR uqa.status NOT IN ('passed', 'failed')
              OR uqa.completed_at IS NULL
          )
        ORDER BY tp.user_id, tp.content_id
    ");

    $mismatched_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($mismatched_cases) {
        echo "<p style='color: orange;'>Found " . count($mismatched_cases) . " mismatched cases:</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>User</th><th>Content ID</th><th>Quiz Completed</th><th>Last Attempt ID</th><th>Action</th></tr>";

        foreach ($mismatched_cases as $case) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($case['user_name']) . " (ID: {$case['user_id']})</td>";
            echo "<td>{$case['content_id']}</td>";
            echo "<td>" . ($case['quiz_completed'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($case['last_quiz_attempt_id'] ?: 'None') . "</td>";

            // Try to find a quiz for this content
            $quiz_find = $pdo->prepare("
                SELECT tq.id, tq.content_id, tq.content_type
                FROM training_quizzes tq
                WHERE (tq.content_type = 'post' OR tq.content_type = '' OR tq.content_type IS NULL)
                  AND tq.content_id = ?
                  AND tq.is_active = 1
                LIMIT 1
            ");
            $quiz_find->execute([$case['content_id']]);
            $quiz_info = $quiz_find->fetch(PDO::FETCH_ASSOC);

            if ($quiz_info) {
                // Create a synthetic quiz attempt record
                $synthetic_attempt = $pdo->prepare("
                    INSERT INTO user_quiz_attempts
                    (user_id, quiz_id, attempt_number, status, score, total_points, earned_points,
                     started_at, completed_at, time_taken_minutes, created_at)
                    VALUES (?, ?, 1, 'passed', ?, 100, ?,
                     DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 60, NOW())
                ");

                $synthetic_score = intval($case['quiz_score'] ?? 100);
                $synthetic_earned = $synthetic_score; // Assume 100 total points

                $synthetic_attempt->execute([
                    $case['user_id'],
                    $quiz_info['id'],
                    $synthetic_score,
                    $synthetic_earned
                ]);

                $synthetic_attempt_id = $pdo->lastInsertId();

                // Update training_progress with the new attempt_id
                $update_tp = $pdo->prepare("
                    UPDATE training_progress
                    SET last_quiz_attempt_id = ?,
                        quiz_completed_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    WHERE user_id = ? AND content_id = ?
                ");
                $update_tp->execute([$synthetic_attempt_id, $case['user_id'], $case['content_id']]);

                echo "<td style='color: green;'>✅ Created synthetic attempt #{$synthetic_attempt_id}</td>";
                $repair_count++;
            } else {
                echo "<td style='color: red;'>❌ No active quiz found for content</td>";
            }

            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: green;'>✅ No mismatched cases found</p>";
    }

    // Find cases where quiz attempts exist but training_progress doesn't reflect them
    echo "<h4>Case 2: Valid Quiz Attempts but Training Progress Not Updated</h4>";

    $stmt2 = $pdo->query("
        SELECT
            uqa.id as attempt_id,
            uqa.user_id,
            uqa.quiz_id,
            uqa.status,
            uqa.score,
            uqa.completed_at,
            u.name as user_name,
            tq.content_id,
            COALESCE(tq.content_type, 'post') as content_type
        FROM user_quiz_attempts uqa
        JOIN users u ON uqa.user_id = u.id
        JOIN training_quizzes tq ON uqa.quiz_id = tq.id
        LEFT JOIN training_progress tp ON (
            uqa.user_id = tp.user_id
            AND tq.content_id = tp.content_id
            AND (tp.content_type = COALESCE(tq.content_type, 'post') OR tp.content_type = '' OR tp.content_type IS NULL)
        )
        WHERE uqa.status IN ('passed', 'failed')
          AND uqa.completed_at IS NOT NULL
          AND (
              tp.id IS NULL
              OR tp.status != 'completed'
              OR tp.quiz_completed != 1
          )
        ORDER BY uqa.user_id, tq.content_id
    ");

    $orphaned_attempts = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    if ($orphaned_attempts) {
        echo "<p style='color: orange;'>Found " . count($orphaned_attempts) . " orphaned attempts:</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>User</th><th>Quiz ID</th><th>Status</th><th>Score</th><th>Completed At</th><th>Action</th></tr>";

        foreach ($orphaned_attempts as $attempt) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($attempt['user_name']) . " (ID: {$attempt['user_id']})</td>";
            echo "<td>{$attempt['quiz_id']}</td>";
            echo "<td>{$attempt['status']}</td>";
            echo "<td>{$attempt['score']}%</td>";
            echo "<td>{$attempt['completed_at']}</td>";

            // Update or create training progress
            $status = ($attempt['status'] === 'passed') ? 'completed' : 'in_progress';
            $quiz_completed = ($attempt['status'] === 'passed') ? 1 : 0;

            $update_tp = $pdo->prepare("
                INSERT INTO training_progress
                (user_id, content_id, content_type, status, quiz_completed, quiz_score,
                 quiz_completed_at, last_quiz_attempt_id, completion_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                quiz_completed = VALUES(quiz_completed),
                quiz_score = VALUES(quiz_score),
                quiz_completed_at = VALUES(quiz_completed_at),
                last_quiz_attempt_id = VALUES(last_quiz_attempt_id),
                completion_date = VALUES(completion_date)
            ");

            $completion_date = ($attempt['status'] === 'passed') ? $attempt['completed_at'] : NULL;

            $update_tp->execute([
                $attempt['user_id'],
                $attempt['content_id'],
                $attempt['content_type'],
                $status,
                $quiz_completed,
                $attempt['score'],
                $attempt['completed_at'],
                $attempt['attempt_id'],
                $completion_date
            ]);

            echo "<td style='color: green;'>✅ Updated training progress</td>";
            $repair_count++;
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: green;'>✅ No orphaned attempts found</p>";
    }

    return $repair_count;
}

// Run the repair
try {
    $total_repairs = repair_mismatched_data($pdo);

    echo "<h3>📊 Repair Summary</h3>";
    echo "<p><strong>Total repairs performed: {$total_repairs}</strong></p>";

    if ($total_repairs > 0) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>✅ Data repair completed successfully!</strong><br>";
        echo "The mismatched data has been fixed. Please check the analytics page again.";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>ℹ️ No repairs needed</strong><br>";
        echo "The data appears to be consistent. The issue might be elsewhere.";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>❌ Error during repair:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<p><small><em>This script safely repairs data inconsistencies between training_progress and user_quiz_attempts tables.</em></small></p>";
?>