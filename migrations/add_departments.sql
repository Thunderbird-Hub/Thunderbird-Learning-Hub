-- Database Migration: Add Departments System
-- Phase 1 of Enhanced Training System with Departments
-- This script adds department management functionality to the training system

-- 1. Create departments table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name)
) COMMENT 'Stores department definitions';

-- 2. Create user_departments junction table (many-to-many)
CREATE TABLE IF NOT EXISTS user_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_dept (user_id, department_id),
    INDEX idx_user (user_id),
    INDEX idx_department (department_id)
) COMMENT 'Maps users to departments (many-to-many)';

-- 3. Create course_departments junction table
CREATE TABLE IF NOT EXISTS course_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    department_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_course_dept (course_id, department_id),
    INDEX idx_course (course_id),
    INDEX idx_department (department_id)
) COMMENT 'Maps training courses to departments (many-to-many)';

-- 4. Add column to training_courses to flag department-assignable courses
ALTER TABLE training_courses
ADD COLUMN can_assign_to_departments TINYINT(1) DEFAULT 0 COMMENT 'Whether this course can be assigned to departments';

-- 5. Create retest tracking table for Phase 3
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
) COMMENT 'Tracks retest eligibility for quizzes';

-- 6. Log this migration
INSERT INTO migration_log (migration_name, description, status)
VALUES (
    'add_departments',
    'Added departments system: departments table, user_departments junction, course_departments junction, and quiz_retest_tracking table',
    'success'
) ON DUPLICATE KEY UPDATE status='success', executed_at=CURRENT_TIMESTAMP;

-- Migration completed successfully!
-- Next steps:
-- 1. Verify tables were created: SELECT * FROM departments LIMIT 1;
-- 2. Check migration ran: SELECT * FROM migration_log WHERE migration_name='add_departments';
-- 3. Proceed to Phase 1 Code Implementation
