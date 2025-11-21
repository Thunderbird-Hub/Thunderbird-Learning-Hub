-- Migration: Add Assignment Source Tracking to user_training_assignments table
-- Created: 2025-11-21
-- Author: Implementation Agent
-- Purpose: Track assignment sources (direct vs department) for training assignments

-- Start transaction
START TRANSACTION;

-- Check and add assignment_source column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND COLUMN_NAME = 'assignment_source'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE user_training_assignments ADD COLUMN assignment_source ENUM(\'direct\', \'department\') NOT NULL DEFAULT \'direct\' AFTER assigned_date',
    'SELECT \'assignment_source column already exists\' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add department_id column if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND COLUMN_NAME = 'department_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE user_training_assignments ADD COLUMN department_id INT NULL AFTER assignment_source',
    'SELECT \'department_id column already exists\' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if it doesn't exist
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND CONSTRAINT_NAME = 'fk_uta_department'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE user_training_assignments ADD CONSTRAINT fk_uta_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT \'fk_uta_department constraint already exists\' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill existing records to set assignment_source = 'direct' and department_id = NULL
UPDATE user_training_assignments
SET assignment_source = 'direct', department_id = NULL
WHERE assignment_source IS NULL OR department_id IS NULL;

-- Add composite index for efficient removal queries (if it doesn't exist)
SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND INDEX_NAME = 'idx_uta_user_course_source'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_uta_user_course_source ON user_training_assignments(user_id, course_id, assignment_source)',
    'SELECT \'idx_uta_user_course_source index already exists\' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on department_id for department-based queries (if it doesn't exist)
SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND INDEX_NAME = 'idx_uta_department_id'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_uta_department_id ON user_training_assignments(department_id)',
    'SELECT \'idx_uta_department_id index already exists\' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on assignment_source for filtering (if it doesn't exist)
SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND INDEX_NAME = 'idx_uta_assignment_source'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_uta_assignment_source ON user_training_assignments(assignment_source)',
    'SELECT \'idx_uta_assignment_source index already exists\' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Commit transaction
COMMIT;

-- Migration verification queries
-- 1. Check table structure
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_training_assignments'
ORDER BY ORDINAL_POSITION;

-- 2. Verify backfill was successful
SELECT COUNT(*) as total_records,
       SUM(CASE WHEN assignment_source = 'direct' THEN 1 ELSE 0 END) as direct_assignments,
       SUM(CASE WHEN assignment_source = 'department' THEN 1 ELSE 0 END) as department_assignments
FROM user_training_assignments;

-- 3. Verify indexes were created
SHOW INDEX FROM user_training_assignments
WHERE Key_name IN ('idx_uta_user_course_source', 'idx_uta_department_id', 'idx_uta_assignment_source');