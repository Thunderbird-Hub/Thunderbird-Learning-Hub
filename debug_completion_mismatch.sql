-- Debug script to investigate completion vs attempts mismatch
-- Shows why completion percentage shows 100% but no completed attempts

-- Run this to diagnose User ID 8

SELECT '=== DEBUGGING COMPLETION VS ATTEMPTS MISMATCH ===' as info;

SET @user_id = 8;
SET @course_id = 1;
SET @quiz_id = 1;

SELECT 'Training Progress:' as section,
       COUNT(*) as total_entries,
       COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_entries
FROM training_progress tp WHERE tp.user_id = @user_id;

SELECT 'Quiz Attempts:' as section,
       COUNT(*) as total_attempts,
       COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed_based
FROM user_quiz_attempts uqa WHERE uqa.user_id = @user_id;
