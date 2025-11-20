-- Migration: Reset User Training Progress Completely
-- This script resets all training-related data for a specific user
-- Use this to reset a user for testing purposes

-- Set the user ID you want to reset (change this value)
SET @user_id_to_reset = 8;

-- Show what we're about to reset (for confirmation)
SELECT '=== BEFORE RESET - Current State ===' as status;
SELECT
    u.id,
    u.name,
    u.role,
    u.is_in_training,
    COUNT(DISTINCT uta.id) as training_assignments,
    COUNT(DISTINCT uqa.id) as quiz_attempts,
    COUNT(DISTINCT tp.id) as training_progress_entries
FROM users u
LEFT JOIN user_training_assignments uta ON u.id = uta.user_id
LEFT JOIN user_quiz_attempts uqa ON u.id = uqa.user_id
LEFT JOIN training_progress tp ON u.id = tp.user_id
WHERE u.id = @user_id_to_reset
GROUP BY u.id, u.name, u.role, u.is_in_training;

-- Reset user's training status (make them a training user again)
UPDATE users
SET is_in_training = 1,
    original_training_completion = NULL
WHERE id = @user_id_to_reset;

-- Delete all quiz attempts for this user
DELETE FROM user_quiz_answers
WHERE attempt_id IN (
    SELECT id FROM user_quiz_attempts
    WHERE user_id = @user_id_to_reset
);

DELETE FROM user_quiz_attempts
WHERE user_id = @user_id_to_reset;

-- Delete all training progress entries for this user
DELETE FROM training_progress
WHERE user_id = @user_id_to_reset;

-- Delete all training assignments for this user
DELETE FROM user_training_assignments
WHERE user_id = @user_id_to_reset;

-- Reset retest tracking for this user (delete their retest records)
DELETE FROM quiz_retest_tracking
WHERE user_id = @user_id_to_reset;

-- Re-assign user to all their department courses (if they have department assignments)
-- This ensures they get the same training content again
INSERT IGNORE INTO user_training_assignments (user_id, course_id, assigned_by, assigned_date)
SELECT
    ud.user_id,
    cd.course_id,
    1 as assigned_by, -- Assuming admin user ID 1
    CURRENT_TIMESTAMP as assigned_date
FROM user_departments ud
JOIN course_departments cd ON ud.department_id = cd.department_id
WHERE ud.user_id = @user_id_to_reset;

-- Show the final state after reset
SELECT '=== AFTER RESET - Final State ===' as status;
SELECT
    u.id,
    u.name,
    u.role,
    u.is_in_training,
    COUNT(DISTINCT uta.id) as training_assignments,
    COUNT(DISTINCT uqa.id) as quiz_attempts,
    COUNT(DISTINCT tp.id) as training_progress_entries
FROM users u
LEFT JOIN user_training_assignments uta ON u.id = uta.user_id
LEFT JOIN user_quiz_attempts uqa ON u.id = uqa.user_id
LEFT JOIN training_progress tp ON u.id = tp.user_id
WHERE u.id = @user_id_to_reset
GROUP BY u.id, u.name, u.role, u.is_in_training;

-- Show what courses the user now has assigned
SELECT '=== Re-assigned Courses ===' as status;
SELECT
    tc.id as course_id,
    tc.name as course_name,
    tc.department as course_department,
    d.name as department_name
FROM user_training_assignments uta
JOIN training_courses tc ON uta.course_id = tc.id
LEFT JOIN course_departments cd ON tc.id = cd.course_id
LEFT JOIN departments d ON cd.department_id = d.id
WHERE uta.user_id = @user_id_to_reset
ORDER BY tc.name;

SELECT '=== RESET COMPLETE ===' as status;
SELECT 'User has been completely reset and re-assigned to their department courses.' as message;
