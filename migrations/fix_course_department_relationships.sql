-- Migration: Fix course-department relationships
-- This script converts existing course department names to proper course_departments table relationships
-- Run this after ensuring your departments are set up correctly

-- Step 1: Show current state (for verification)
SELECT 'Current Course-Department Issues:' as status;
SELECT
    c.id as course_id,
    c.name as course_name,
    c.department as dept_name_stored,
    d.id as dept_id_found,
    d.name as dept_name_found
FROM training_courses c
LEFT JOIN departments d ON c.department = d.name
WHERE c.department IS NOT NULL AND c.department != ''
ORDER BY c.id;

-- Step 2: Create proper course_departments relationships for existing courses
INSERT IGNORE INTO course_departments (course_id, department_id, assigned_by)
SELECT
    c.id as course_id,
    d.id as department_id,
    1 as assigned_by  -- Assuming admin user ID 1, adjust if needed
FROM training_courses c
JOIN departments d ON c.department = d.name
WHERE c.department IS NOT NULL
  AND c.department != ''
  AND d.id IS NOT NULL;

-- Step 3: Show what was migrated
SELECT 'Migration Results:' as status;
SELECT
    COUNT(*) as courses_migrated
FROM training_courses c
JOIN departments d ON c.department = d.name
WHERE c.department IS NOT NULL
  AND c.department != ''
  AND d.id IS NOT NULL;

-- Step 4: Verify the relationships were created
SELECT 'Verification - Course-Department Relationships Created:' as status;
SELECT
    c.id as course_id,
    c.name as course_name,
    d.name as department_name,
    cd.assigned_date
FROM course_departments cd
JOIN training_courses c ON cd.course_id = c.id
JOIN departments d ON cd.department_id = d.id
ORDER BY c.name, d.name;

-- Step 5: Show final department stats (calculated, not stored)
SELECT 'Final Department Stats (Calculated):' as status;
SELECT
    d.id,
    d.name,
    d.description,
    (SELECT COUNT(ud.user_id) FROM user_departments ud WHERE ud.department_id = d.id) as member_count,
    (SELECT COUNT(cd.course_id) FROM course_departments cd WHERE cd.department_id = d.id) as course_count,
    d.created_at
FROM departments d
ORDER BY d.name;

-- Note: After running this migration:
-- 1. Courses will be properly linked to departments via the course_departments table
-- 2. Department counts will be accurate (calculated on-the-fly)
-- 3. The training_courses.department field becomes legacy (for reference only)
