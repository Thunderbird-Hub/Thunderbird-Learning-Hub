-- Complete Training System Setup with Assignment Source Tracking
-- Created: 2025-11-21
-- Author: Implementation Agent
-- Purpose: Create training tables if they don't exist and add source tracking

-- Start transaction
START TRANSACTION;

-- ============================================================
-- STEP 1: Create Core Training Tables (if they don't exist)
-- ============================================================

-- Create training_courses table
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'training_courses'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE training_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        department VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active),
        INDEX idx_created_by (created_by)
    ) COMMENT=\"Training courses available for assignment\"',
    'SELECT ''training_courses table already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create training_course_content table
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'training_course_content'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE training_course_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        content_type ENUM(\"category\", \"subcategory\", \"post\") NOT NULL,
        content_id INT NOT NULL,
        time_required_minutes INT DEFAULT 0,
        admin_notes TEXT,
        training_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
        INDEX idx_course (course_id),
        INDEX idx_content (content_type, content_id),
        UNIQUE KEY unique_course_content (course_id, content_type, content_id)
    ) COMMENT=\"Content items that make up training courses\"',
    'SELECT ''training_course_content table already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create user_training_assignments table with source tracking
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE user_training_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        status ENUM(\"not_started\", \"in_progress\", \"completed\") DEFAULT \"not_started\",
        assigned_by INT NOT NULL,
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completion_date TIMESTAMP NULL,
        assignment_source ENUM(\"direct\", \"department\") NOT NULL DEFAULT \"direct\",
        department_id INT NULL,
        retest_exempt BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL ON UPDATE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_course (course_id),
        INDEX idx_user_course_source (user_id, course_id, assignment_source),
        INDEX idx_department_id (department_id),
        INDEX idx_assignment_source (assignment_source),
        UNIQUE KEY unique_user_course (user_id, course_id)
    ) COMMENT=\"User training assignments with source tracking\"',
    'SELECT ''user_training_assignments table already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create training_progress table
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'training_progress'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE training_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        content_type VARCHAR(50) DEFAULT \"\",
        content_id INT NOT NULL,
        status ENUM(\"not_started\", \"in_progress\", \"completed\") DEFAULT \"not_started\",
        completion_date TIMESTAMP NULL,
        time_spent_minutes INT DEFAULT 0,
        time_started TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        quiz_completed BOOLEAN DEFAULT FALSE,
        quiz_score INT DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
        INDEX idx_user_course (user_id, course_id),
        INDEX idx_content (content_type, content_id),
        INDEX idx_status (status),
        UNIQUE KEY unique_user_content (user_id, content_type, content_id)
    ) COMMENT=\"Training progress tracking\"',
    'SELECT ''training_progress table already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create training_quizzes table
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'training_quizzes'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE training_quizzes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        content_id INT NOT NULL,
        content_type VARCHAR(50) DEFAULT \"post\",
        passing_score INT DEFAULT 80,
        time_limit_minutes INT DEFAULT 30,
        retest_period_months INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_content (content_type, content_id),
        INDEX idx_active (is_active)
    ) COMMENT=\"Training quizzes for content assessment\"',
    'SELECT ''training_quizzes table already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create user_quiz_attempts table
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_quiz_attempts'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE user_quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        quiz_id INT NOT NULL,
        status ENUM(\"pending\", \"in_progress\", \"passed\", \"failed\") DEFAULT \"pending\",
        score INT DEFAULT 0,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        time_taken_minutes INT DEFAULT 0,
        attempts_count INT DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (quiz_id) REFERENCES training_quizzes(id) ON DELETE CASCADE,
        INDEX idx_user_quiz (user_id, quiz_id),
        INDEX idx_status (status)
    ) COMMENT=\"User quiz attempts tracking\"',
    'SELECT ''user_quiz_attempts table already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create user_quiz_answers table
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_quiz_answers'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE user_quiz_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attempt_id INT NOT NULL,
        question_id INT NOT NULL,
        answer_text TEXT,
        is_correct BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attempt_id) REFERENCES user_quiz_attempts(id) ON DELETE CASCADE,
        INDEX idx_attempt (attempt_id)
    ) COMMENT=\"User quiz answers storage\"',
    'SELECT ''user_quiz_answers table already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create training_history table
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'training_history'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE training_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        content_type VARCHAR(50) DEFAULT \"\",
        content_id INT NOT NULL,
        completion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        time_spent_minutes INT DEFAULT 0,
        original_assignment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        course_completed_date TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
        INDEX idx_user_course (user_id, course_id),
        INDEX idx_completion_date (completion_date)
    ) COMMENT=\"Permanent training completion history\"',
    'SELECT ''training_history table already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- STEP 2: Add Assignment Source Tracking to Existing Tables
-- ============================================================

-- Only proceed if table already existed (not created above)
SET @table_existed = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
) - IF(@table_exists = 0, 1, 0);

-- Add assignment_source column if it doesn't exist and table existed
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND COLUMN_NAME = 'assignment_source'
);

SET @sql = IF(@column_exists = 0 AND @table_existed > 0,
    'ALTER TABLE user_training_assignments ADD COLUMN assignment_source ENUM(\"direct\", \"department\") NOT NULL DEFAULT \"direct\" AFTER assigned_date',
    'SELECT ''assignment_source column already exists or table was just created'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add department_id column if it doesn't exist and table existed
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND COLUMN_NAME = 'department_id'
);

SET @sql = IF(@column_exists = 0 AND @table_existed > 0,
    'ALTER TABLE user_training_assignments ADD COLUMN department_id INT NULL AFTER assignment_source',
    'SELECT ''department_id column already exists or table was just created'' as message'
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
    'SELECT ''fk_uta_department constraint already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill existing records if table existed
SET @sql = IF(@table_existed > 0,
    'UPDATE user_training_assignments SET assignment_source = \"direct\", department_id = NULL WHERE assignment_source IS NULL OR department_id IS NULL',
    'SELECT ''No backfill needed - table was just created'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes for performance if they don't exist
SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND INDEX_NAME = 'idx_uta_user_course_source'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_uta_user_course_source ON user_training_assignments(user_id, course_id, assignment_source)',
    'SELECT ''idx_uta_user_course_source index already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND INDEX_NAME = 'idx_uta_department_id'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_uta_department_id ON user_training_assignments(department_id)',
    'SELECT ''idx_uta_department_id index already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_training_assignments'
      AND INDEX_NAME = 'idx_uta_assignment_source'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_uta_assignment_source ON user_training_assignments(assignment_source)',
    'SELECT ''idx_uta_assignment_source index already exists'' as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Commit transaction
COMMIT;

-- ============================================================
-- Verification Queries
-- ============================================================

SELECT 'Training System Setup Complete!' as status;

-- 1. Check table structure
SELECT '=== Table Structure ===' as info;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_training_assignments'
ORDER BY ORDINAL_POSITION;

-- 2. Verify backfill was successful
SELECT '=== Assignment Source Distribution ===' as info;
SELECT COUNT(*) as total_records,
       SUM(CASE WHEN assignment_source = 'direct' THEN 1 ELSE 0 END) as direct_assignments,
       SUM(CASE WHEN assignment_source = 'department' THEN 1 ELSE 0 END) as department_assignments
FROM user_training_assignments;

-- 3. Verify indexes were created
SELECT '=== Index Verification ===' as info;
SHOW INDEX FROM user_training_assignments
WHERE Key_name IN ('idx_uta_user_course_source', 'idx_uta_department_id', 'idx_uta_assignment_source');

-- 4. Verify foreign keys
SELECT '=== Foreign Key Verification ===' as info;
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'user_training_assignments'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

SELECT 'Setup Complete! Training system with assignment source tracking is ready.' as final_status;