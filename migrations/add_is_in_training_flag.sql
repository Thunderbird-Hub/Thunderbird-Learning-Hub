-- Database Migration: Training System Overhaul Phase 1 - Foundation
-- This script adds the is_in_training flag to support the new training system architecture
-- where training is a state (flag) rather than a role
-- Run this SQL script manually in your MySQL database
--
-- Purpose: Separate training as a permission/role from training as a state
-- Before: Users with role='training' were restricted in permissions
-- After: Users have role ('user', 'admin', 'super_admin') and an is_in_training flag for state

-- 1. Add is_in_training column to users table
ALTER TABLE users
ADD COLUMN is_in_training TINYINT(1) DEFAULT 0 COMMENT 'Training state flag: 1 = user is in training, 0 = user is not in training';

-- 2. Migrate existing training role users to the new system
-- Set is_in_training=1 for all users currently with role='training'
UPDATE users
SET is_in_training = 1
WHERE LOWER(role) = 'training';

-- 3. Create index for performance on the new flag
CREATE INDEX idx_is_in_training ON users(is_in_training);

-- 4. Create index for combined lookups (common query pattern)
CREATE INDEX idx_role_is_in_training ON users(role, is_in_training);

-- 5. Log the migration completion
-- Note: This is for audit trail purposes
ALTER TABLE users
ADD COLUMN training_flag_migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the training flag migration was applied' AFTER is_in_training;

-- Summary of changes:
-- - Added is_in_training TINYINT(1) column (default 0)
-- - Migrated all existing 'training' role users to is_in_training=1
-- - Created indexes for query performance
-- - Added migration audit timestamp

-- Migration completed successfully!
-- Note: If columns already exist, MySQL will ignore the ALTER TABLE commands with no error.
-- Next: Phase 2 will implement dual-write logic to support both old and new systems during transition.
