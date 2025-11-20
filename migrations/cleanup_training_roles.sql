-- Migration: Clean up training roles and set is_in_training flags
-- This converts any "training" roles to "user" and sets is_in_training=1
-- Run once to clean up the database after role system simplification

-- Step 1: Convert any "training" roles to "user" and set training flag
UPDATE users
SET role = 'user', is_in_training = 1
WHERE LOWER(role) = 'training';

-- Step 2: Fix users who have training assignments but no training flag
UPDATE users u
SET is_in_training = 1
WHERE EXISTS (
    SELECT 1 FROM user_training_assignments uta
    WHERE uta.user_id = u.id
)
AND u.is_in_training = 0;

-- Step 3: Display results for verification (comment out in production)
SELECT
    role,
    COUNT(*) as total_users,
    SUM(is_in_training) as users_in_training,
    ROUND((SUM(is_in_training) / COUNT(*)) * 100, 2) as training_percentage
FROM users
GROUP BY role
ORDER BY role;

-- Verification query - Check if migration was successful
SELECT 'Migration Results:' as status,
       (SELECT COUNT(*) FROM users WHERE LOWER(role) = 'training') as training_roles_remaining,
       (SELECT COUNT(*) FROM users WHERE is_in_training = 1) as users_with_training_flag,
       (SELECT COUNT(*) FROM user_training_assignments) as total_training_assignments;

-- Note: After running this migration, the system will use only 3 roles:
-- - user (regular users)
-- - admin (administrators)
-- - super_admin (super administrators)
--
-- Training status will be determined by the is_in_training flag (0/1)
-- rather than role assignments