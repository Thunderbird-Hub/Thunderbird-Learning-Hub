-- Database Migration: Add is_in_training Flag
-- Phase 1 of Training System Overhaul
-- This script adds the is_in_training flag to support the new training system
-- where training is a state (flag) rather than a role

-- 1. Add is_in_training column to users table
ALTER TABLE users
ADD COLUMN is_in_training TINYINT(1) DEFAULT 0
COMMENT 'Boolean flag: whether user is currently in training (state, not role)';

-- 2. Migrate existing training role users to have the flag set
-- Users with role='training' get is_in_training=1, others get 0
UPDATE users
SET is_in_training = 1
WHERE LOWER(TRIM(role)) = 'training';

-- 3. Create index on is_in_training for faster queries
CREATE INDEX idx_is_in_training ON users(is_in_training);

-- 4. Create a migration log table to track when this was run
CREATE TABLE IF NOT EXISTS migration_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    notes TEXT
);

-- 5. Log this migration
INSERT INTO migration_log (migration_name, description, status)
VALUES (
    'add_is_in_training_flag',
    'Added is_in_training boolean flag to users table. Migrated existing training role users to use flag instead.',
    'success'
);

-- Migration completed successfully!
-- Next steps:
-- 1. Verify the column was added: SELECT id, name, role, is_in_training FROM users LIMIT 5;
-- 2. Check migration ran: SELECT * FROM migration_log ORDER BY executed_at DESC LIMIT 1;
-- 3. Proceed to Phase 2: Dual-write implementation in application code
