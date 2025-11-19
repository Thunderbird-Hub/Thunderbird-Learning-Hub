<?php
/**
 * Database Migration: Add Retest Period Feature
 * This script adds the retest period functionality to the training system
 */

require_once __DIR__ . '/../includes/db_connect.php';

try {
    echo "Starting retest period feature migration...\n";

    // 1. Add retest_period column to training_quizzes table
    echo "Adding retest_period column to training_quizzes...\n";
    $pdo->exec("
        ALTER TABLE training_quizzes
        ADD COLUMN retest_period_months INT DEFAULT NULL COMMENT 'Retest period in months, NULL means no retest required'
    ");

    // 2. Add last_completed_date column to user_quiz_attempts for tracking retest eligibility
    echo "Adding last_completed_date column to user_quiz_attempts...\n";
    $pdo->exec("
        ALTER TABLE user_quiz_attempts
        ADD COLUMN last_completed_date DATE DEFAULT NULL COMMENT 'Date when user last completed this quiz for retest calculation'
    ");

    // 3. Add retest_required column to user_quiz_attempts
    echo "Adding retest_required column to user_quiz_attempts...\n";
    $pdo->exec("
        ALTER TABLE user_quiz_attempts
        ADD COLUMN retest_required BOOLEAN DEFAULT FALSE COMMENT 'Whether user needs to retake this quiz'
    ");

    // 4. Create quiz_retest_log table to track retest history
    echo "Creating quiz_retest_log table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quiz_retest_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT NOT NULL,
            user_id INT NOT NULL,
            previous_attempt_id INT NOT NULL,
            retest_reason ENUM('period_expired', 'admin_forced', 'content_updated') NOT NULL,
            retest_date DATETIME NOT NULL,
            old_status ENUM('passed', 'failed', 'in_progress') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (quiz_id) REFERENCES training_quizzes(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (previous_attempt_id) REFERENCES user_quiz_attempts(id)
        )
    ");

    // 5. Add retest_exempt column to user_training_assignments for specific user exemptions
    echo "Adding retest_exempt column to user_training_assignments...\n";
    $pdo->exec("
        ALTER TABLE user_training_assignments
        ADD COLUMN retest_exempt BOOLEAN DEFAULT FALSE COMMENT 'Whether user is exempt from retest requirements'
    ");

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>