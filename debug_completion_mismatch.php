<?php
/**
 * Debug script to investigate completion vs attempts mismatch
 * Shows why completion percentage shows 100% but no completed attempts
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "<h2>🔍 Debugging Completion vs Attempts Mismatch</h2>";

// Test with specific values (you can adjust these)
$test_user_id = 8;
$test_course_id = 1;
$test_quiz_id = 1;

echo "<h3>Test Parameters:</h3>";
echo "<p>User ID: $test_user_id</p>";
echo "<p>Course ID: $test_course_id</p>";
echo "<p>Quiz ID: $test_quiz_id</p>";

// Check training progress entries (for completion calculation)
echo "<h3>Training Progress Entries (for Completion %):</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            tp.id,
            tp.user_id,
            tp.content_id,
            tp.content_type,
            tp.status,
            tp.quiz_completed,
            tp.quiz_score,
            tp.quiz_completed_at,
            tp.completion_date
        FROM training_progress tp
        WHERE tp.user_id = ?
        ORDER BY tp.completion_date DESC
        LIMIT 10
    ");
    $stmt->execute([$test_user_id]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($progress) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Content ID</th><th>Content Type</th><th>Status</th><th>Quiz Completed</th><th>Quiz Score</th><th>Completed At</th><th>Completion Date</th></tr>";
        foreach ($progress as $entry) {
            echo "<tr>";
            echo "<td>" . $entry['id'] . "</td>";
            echo "<td>" . $entry['content_id'] . "</td>";
            echo "<td>" . htmlspecialchars($entry['content_type']) . "</td>";
            echo "<td>" . htmlspecialchars($entry['status']) . "</td>";
            echo "<td>" . ($entry['quiz_completed'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($entry['quiz_score'] ? $entry['quiz_score'] . '%' : 'N/A') . "</td>";
            echo "<td>" . ($entry['quiz_completed_at'] ?: 'N/A') . "</td>";
            echo "<td>" . ($entry['completion_date'] ?: 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count completed entries
        $completed_count = array_filter($progress, function($entry) {
            return $entry['status'] === 'completed';
        });
        echo "<p><strong>Completed entries found: " . count($completed_count) . "</strong></p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No training progress entries found for User ID $test_user_id</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error checking training progress: " . $e->getMessage() . "</p>";
}

// Check quiz attempts directly
echo "<h3>User Quiz Attempts (for 'No completed attempts yet'):</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            uqa.id,
            uqa.user_id,
            uqa.quiz_id,
            uqa.status,
            uqa.score,
            uqa.completed_at,
            uqa.started_at,
            uqa.attempt_number
        FROM user_quiz_attempts uqa
        WHERE uqa.user_id = ?
        ORDER BY uqa.created_at DESC
    ");
    $stmt->execute([$test_user_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($attempts) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Attempt ID</th><th>User ID</th><th>Quiz ID</th><th>Status</th><th>Score</th><th>Started At</th><th>Completed At</th><th>Attempt #</th></tr>";
        foreach ($attempts as $attempt) {
            echo "<tr>";
            echo "<td>" . $attempt['id'] . "</td>";
            echo "<td>" . $attempt['user_id'] . "</td>";
            echo "<td>" . $attempt['quiz_id'] . "</td>";
            echo "<td>" . htmlspecialchars($attempt['status']) . "</td>";
            echo "<td>" . ($attempt['score'] ? $attempt['score'] . '%' : 'N/A') . "</td>";
            echo "<td>" . $attempt['started_at'] . "</td>";
            echo "<td>" . ($attempt['completed_at'] ?: 'Not completed') . "</td>";
            echo "<td>" . ($attempt['attempt_number'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count attempts matching different criteria
        $status_match = array_filter($attempts, function($attempt) {
            return in_array(strtolower($attempt['status'] ?? ''), ['passed', 'failed']);
        });
        $completed_match = array_filter($attempts, function($attempt) {
            return !empty($attempt['completed_at']);
        });
        echo "<p><strong>Attempts with status 'passed'/' 'failed': " . count($status_match) . "</strong></p>";
        echo "<p><strong>Attempts with completed_at NOT NULL: " . count($completed_match) . "</strong></p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No quiz attempts found for User ID $test_user_id</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error checking quiz attempts: " . $e->getMessage() . "</p>";
}

// Check training assignments
echo "<h3>User Training Assignments:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            uta.id,
            uta.user_id,
            uta.course_id,
            uta.status,
            uta.assigned_date,
            uta.completion_date
        FROM user_training_assignments uta
        WHERE uta.user_id = ?
    ");
    $stmt->execute([$test_user_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($assignments) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Assignment ID</th><th>User ID</th><th>Course ID</th><th>Status</th><th>Assigned Date</th><th>Completion Date</th></tr>";
        foreach ($assignments as $assignment) {
            echo "<tr>";
            echo "<td>" . $assignment['id'] . "</td>";
            echo "<td>" . $assignment['user_id'] . "</td>";
            echo "<td>" . $assignment['course_id'] . "</td>";
            echo "<td>" . htmlspecialchars($assignment['status']) . "</td>";
            echo "<td>" . $assignment['assigned_date'] . "</td>";
            echo "<td>" . ($assignment['completion_date'] ?: 'Not completed') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        $completed_assignments = array_filter($assignments, function($assignment) {
            return $assignment['status'] === 'completed';
        });
        echo "<p><strong>Completed assignments: " . count($completed_assignments) . "</strong></p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No training assignments found for User ID $test_user_id</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error checking training assignments: " . $e->getMessage() . "</p>";
}

// Test the specific analytics query
echo "<h3>Analytics Query Test:</h3>";
try {
    // This is the query from line 920-926 in analytics
    $attempt_stmt = $pdo->prepare("
        SELECT id, attempt_number, score, status, completed_at
        FROM user_quiz_attempts
        WHERE user_id = ?
          AND quiz_id = ?
          AND (status IN ('passed','failed') OR completed_at IS NOT NULL)
        ORDER BY attempt_number DESC
    ");
    $attempt_stmt->execute([$test_user_id, $test_quiz_id]);
    $attempts_result = $attempt_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($attempts_result) {
        echo "<p><strong>✅ Analytics query found " . count($attempts_result) . " attempts</strong></p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Attempt #</th><th>Score</th><th>Status</th><th>Completed At</th></tr>";
        foreach ($attempts_result as $att) {
            echo "<tr>";
            echo "<td>" . $att['id'] . "</td>";
            echo "<td>" . ($att['attempt_number'] ?? 'N/A') . "</td>";
            echo "<td>" . ($att['score'] ? $att['score'] . '%' : 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($att['status']) . "</td>";
            echo "<td>" . ($att['completed_at'] ?: 'Not completed') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><strong>❌ Analytics query found 0 attempts</strong></p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error with analytics query: " . $e->getMessage() . "</p>";
}

echo "<h3>📋 Diagnosis:</h3>";
echo "<p><strong>If you see:</strong></p>";
echo "<ul>";
echo "<li>✅ <strong>Completed training progress entries</strong> but ❌ <strong>No quiz attempts</strong> → Quiz submission issue</li>";
echo "<li>❌ <strong>No training progress</strong> and ❌ <strong>No quiz attempts</strong> → User never started training</li>";
echo "<li>✅ <strong>Both present</strong> → System working correctly</li>";
echo "</ul>";
echo "<p><strong>The key insight is that completion % is based on training_progress entries, but attempts are based on user_quiz_attempts. If one is missing, that's the bug!</strong></p>";
?>
