# Department Assignment System Testing Guide

## Overview
This guide provides comprehensive testing procedures for the department-based training assignment system to ensure it works correctly according to the user's requirements:

1. User is assigned to a department
2. User automatically inherits all training courses assigned to that department
3. User's `is_in_training` flag is set to 1, enabling training access
4. User role is changed to 'training' (except for admins/super admins)

## Testing Prerequisites

### Database Requirements
Ensure all required database tables exist:
- `departments`
- `user_departments`
- `course_departments`
- `user_training_assignments`
- `training_courses`
- `users`

### Required Columns
Verify these critical columns exist:
- `users.is_in_training`
- `user_training_assignments.assignment_source`
- `user_training_assignments.department_id`

### Sample Data Setup
Before testing, ensure you have:
1. At least 1 department created
2. At least 1 active training course created
3. The course assigned to the department
4. At least 1 active non-admin user for testing

## Testing Procedures

### Phase 1: Diagnostic Testing

#### 1.1 Run Diagnostic Script
**URL:** `/debug_department_assignment.php`

**Expected Results:**
- ✅ All required tables exist
- ✅ All critical columns exist
- ✅ Sample data found (departments, courses, users)
- ✅ User analysis shows no broken department/assignment relationships

**Troubleshooting:**
- If tables are missing: Run migration files
- If columns are missing: Run assignment source tracking migration
- If no sample data: Create test data manually

### Phase 2: System Health Check

#### 2.1 Admin Interface Health Check
**URL:** `/admin/manage_departments.php`

**Expected Results:**
- 🟢 System Health: Healthy (green box)
- No issues listed
- Link to diagnostic script available

**Troubleshooting:**
- If issues shown: Follow recommendations listed
- If status is "issues_found": Address each issue individually

### Phase 3: End-to-End Workflow Testing

#### 3.1 Department Assignment Workflow

**Steps:**
1. Login as admin
2. Navigate to Department Management
3. Click "Manage" on a department that has courses assigned
4. Select a non-admin user and click "Add Selected Users"

**Expected Results:**
- ✅ Success message shows: "✅ Successfully assigned user to X course(s) and enabled training access"
- ✅ User now has `is_in_training = 1` in database
- ✅ User has training assignments in `user_training_assignments` table
- ✅ User role changed to 'training' (if not admin/super admin)
- ✅ Training progress bar visible when user logs in

#### 3.2 Verify Database State

**SQL Queries to Run:**

```sql
-- Check user training flag
SELECT id, name, role, is_in_training FROM users WHERE name = 'Test User';

-- Check user department assignment
SELECT * FROM user_departments WHERE user_id = [USER_ID];

-- Check training assignments
SELECT uta.*, tc.name as course_name, d.name as dept_name
FROM user_training_assignments uta
JOIN training_courses tc ON uta.course_id = tc.id
LEFT JOIN departments d ON uta.department_id = d.id
WHERE uta.user_id = [USER_ID];
```

**Expected Results:**
- `is_in_training = 1`
- Department assignment record exists
- Training assignments exist with `assignment_source = 'department'`
- `department_id` properly set

#### 3.3 User Experience Testing

**Steps:**
1. Login as the assigned user
2. Navigate to the main interface
3. Check if training progress is visible
4. Access training content

**Expected Results:**
- ✅ Training progress bar visible at top
- ✅ User can only access assigned training content
- ✅ User sees their assigned courses
- ✅ Role changed to 'training' (for non-privileged users)

### Phase 4: Edge Case Testing

#### 4.1 Department with No Courses
**Steps:**
1. Create department with no courses assigned
2. Assign user to department
3. Check user state

**Expected Results:**
- ⚠️ Message: "User added to department successfully! (No courses were assigned...)"
- `is_in_training` flag remains 0
- User role unchanged
- No training assignments created

#### 4.2 Admin User Assignment
**Steps:**
1. Assign admin user to department
2. Check admin user state

**Expected Results:**
- ✅ User gets training assignments
- ✅ `is_in_training` flag set to 1
- ❌ User role remains 'admin' (unchanged)
- ✅ Admin can still access all content + training content

#### 4.3 Duplicate Assignment
**Steps:**
1. Assign user to department (first time)
2. Assign same user to same department again

**Expected Results:**
- First assignment: Works normally
- Second assignment: No duplicate assignments created
- Success message appears each time

#### 4.4 User Removal from Department
**Steps:**
1. Assign user to department with courses
2. Remove user from department
3. Check user state

**Expected Results:**
- User department assignment removed
- Only department-sourced training assignments removed
- Direct training assignments preserved
- `is_in_training` flag cleared if no remaining assignments
- Role changed back to 'user' (if was 'training')

### Phase 5: Performance Testing

#### 5.1 Bulk Assignment Testing
**Steps:**
1. Create 10+ test users
2. Assign all users to department with multiple courses
3. Monitor system response

**Expected Results:**
- ✅ System completes without timeouts
- ✅ All users receive assignments
- ✅ Database transactions complete successfully
- ✅ Error handling works for individual failures

### Phase 6: Error Handling Testing

#### 6.1 Invalid Parameters
**Steps:**
1. Try assigning non-existent user
2. Try assigning to non-existent department
3. Try with missing form data

**Expected Results:**
- ❌ Appropriate error messages displayed
- ❌ No database corruption
- ✅ System remains stable

#### 6.2 Database Connection Issues
**Steps:**
1. Temporarily disable database connection
2. Try department assignment
3. Re-enable connection

**Expected Results:**
- ❌ Graceful error handling
- ❌ No partial data corruption
- ✅ System recovers when connection restored

## Troubleshooting Common Issues

### Issue 1: "No courses were assigned"
**Possible Causes:**
- Department has no active courses assigned
- Course-department relationships missing
- Courses marked as inactive

**Solutions:**
1. Check course assignments: `SELECT * FROM course_departments WHERE department_id = [DEPT_ID];`
2. Verify courses are active: `SELECT * FROM training_courses WHERE is_active = 1;`
3. Assign courses to department first

### Issue 2: Training flag not set
**Possible Causes:**
- `is_in_training` column missing
- Training assignment failed silently
- Role management logic error

**Solutions:**
1. Check column exists: Run migration
2. Review debug logs: `tail -f includes/assignment_debug.log`
3. Manually run: `UPDATE users SET is_in_training = 1 WHERE id = [USER_ID];`

### Issue 3: User role not changed
**Possible Causes:**
- User is admin/super_admin (roles preserved)
- Role update failed
- Session not updated

**Solutions:**
1. Verify user is not admin: `SELECT role FROM users WHERE id = [USER_ID];`
2. Check role management in training_helpers.php
3. Clear session and re-login

### Issue 4: Content not accessible
**Possible Causes:**
- Training content not assigned to courses
- Course content relationships missing
- Access control logic error

**Solutions:**
1. Check course content: `SELECT * FROM training_course_content WHERE course_id = [COURSE_ID];`
2. Verify user assignments: `SELECT * FROM user_training_assignments WHERE user_id = [USER_ID];`
3. Test access control logic

## Automated Testing Script

Create a PHP script to automatically verify the system:

```php
<?php
// test_department_assignment.php
require_once 'includes/db_connect.php';
require_once 'includes/department_assignment_enhanced.php';

echo "🧪 Running Automated Department Assignment Tests\n\n";

// Test 1: System Health
$health = check_department_assignment_system_health($pdo);
echo "System Health: " . $health['status'] . "\n";
if (!empty($health['issues'])) {
    echo "Issues: " . implode(', ', $health['issues']) . "\n";
}

// Test 2: Create test data if needed
// ... implementation

// Test 3: Assignment workflow
// ... implementation

echo "✅ Testing complete!\n";
?>
```

## Performance Monitoring

### Key Metrics to Monitor:
- Database query execution times
- Memory usage during bulk operations
- Error rates in assignment functions
- User login/role management performance

### Monitoring Tools:
- PHP error logs
- Database slow query log
- Application performance monitoring
- System resource monitoring

## Documentation and Maintenance

### Regular Maintenance Tasks:
1. Review and rotate debug logs
2. Update testing procedures as features change
3. Monitor system health dashboard
4. Test after database schema changes
5. Verify after major application updates

### Documentation Updates:
- Update this guide when adding new features
- Document any edge cases discovered
- Record solutions to new problems
- Maintain changelog for fixes

---

**Testing Checklist:**
- [ ] Database structure verified
- [ ] Diagnostic script runs successfully
- [ ] System health shows "Healthy"
- [ ] Single user assignment works
- [ ] Bulk assignment works
- [ ] Training flag set correctly
- [ ] User role changed appropriately
- [ ] Training content accessible
- [ ] Edge cases handled properly
- [ ] Error messages user-friendly
- [ ] Performance acceptable
- [ ] Logs contain useful debugging info

**Final Verification:**
Run the complete workflow with a test user to confirm the entire system works end-to-end according to the original requirements.