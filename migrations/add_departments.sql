-- Database Migration: Add Departments System
-- Phase 1 of Enhanced Training System
-- This script adds department support for better course organization and bulk assignment

-- 1. Create departments table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_by INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create user_departments junction table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS user_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    assigned_by INT,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_dept (user_id, department_id),
    INDEX idx_user (user_id),
    INDEX idx_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create course_departments junction table (for assigning courses to departments)
CREATE TABLE IF NOT EXISTS course_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    department_id INT NOT NULL,
    assigned_by INT,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_course_dept (course_id, department_id),
    INDEX idx_course (course_id),
    INDEX idx_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Add department support to training_courses table
ALTER TABLE training_courses
ADD COLUMN IF NOT EXISTS can_assign_to_departments TINYINT(1) DEFAULT 0 COMMENT 'Whether this course can be assigned to departments',
ADD COLUMN IF NOT EXISTS assignment_type ENUM('user', 'department', 'both') DEFAULT 'user' COMMENT 'Type of assignment allowed (user, department, or both)';

-- 5. Create quiz_retest_tracking table (for Phase 3 - retest eligibility)
CREATE TABLE IF NOT EXISTS quiz_retest_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    last_passed_date DATE,
    retest_eligible_date DATE,
    retest_enabled TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES training_quizzes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_quiz (user_id, quiz_id),
    INDEX idx_retest_eligible (retest_eligible_date),
    INDEX idx_retest_enabled (retest_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Log this migration
INSERT INTO migration_log (migration_name, description, status)
VALUES (
    'add_departments',
    'Added departments system with user_departments and course_departments junction tables. Added support for department-based course assignment.',
    'success'
);

-- Migration completed successfully!
-- Next steps:
-- 1. Verify the tables were created: SHOW TABLES LIKE 'department%';
-- 2. Check migration log: SELECT * FROM migration_log ORDER BY executed_at DESC LIMIT 1;
-- 3. Proceed to Phase 1: Creating department helper functions
