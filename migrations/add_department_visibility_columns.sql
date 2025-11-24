-- Database Migration: Add Department Visibility Columns
-- This script adds department-based visibility fields for categories, subcategories, and posts
-- to support sharing content with entire departments alongside individual users.

-- 1) Add allowed_departments column to categories for restricted visibility
ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS allowed_departments TEXT NULL COMMENT 'JSON array of department IDs allowed to view this category';

-- 2) Add allowed_departments column to subcategories for restricted visibility
ALTER TABLE subcategories
    ADD COLUMN IF NOT EXISTS allowed_departments TEXT NULL COMMENT 'JSON array of department IDs allowed to view this subcategory';

-- 3) Add shared_departments column to posts for shared visibility mode
ALTER TABLE posts
    ADD COLUMN IF NOT EXISTS shared_departments TEXT NULL COMMENT 'JSON array of department IDs this post is shared with';

-- 4) Log the migration execution
INSERT INTO migration_log (migration_name, description, status)
VALUES (
    'add_department_visibility_columns',
    'Add department visibility columns to categories, subcategories, and posts',
    'success'
) ON DUPLICATE KEY UPDATE status='success', executed_at=CURRENT_TIMESTAMP;

-- Migration completed successfully!
-- Verification steps:
-- 1. Confirm columns were added: DESCRIBE categories; DESCRIBE subcategories; DESCRIBE posts;
-- 2. Validate logging entry: SELECT * FROM migration_log WHERE migration_name='add_department_visibility_columns';
