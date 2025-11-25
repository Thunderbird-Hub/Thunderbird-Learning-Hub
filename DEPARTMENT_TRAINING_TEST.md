# Department Training Assignment Test Scenarios

## Overview
This document provides comprehensive test scenarios to verify that department training assignments work correctly in the Thunderbird Learning Hub mobile application.

## Enhanced Error Handling & Debugging
The department training assignment logic has been enhanced with:
- Comprehensive error logging
- Input validation
- Database table existence checks
- Transaction safety
- Detailed failure reporting
- Session synchronization

## Test Scenarios

### Scenario 1: Basic Department Course Assignment
**Steps:**
1. Log in as admin
2. Navigate to Admin → Department Management
3. Create a new department (e.g., "Test Department")
4. Navigate to Training Management and create a course
5. Assign the course to the department
6. Create a new user and assign them to the department
7. Check the user's training status

**Expected Results:**
- User should be automatically assigned to department courses
- User's `is_in_training` flag should be set to 1
- Session should update if editing current user
- Success message should indicate number of courses assigned

**Debug Logs to Check:**
```
DEBUG: Starting department course assignment - User ID: X, Department ID: Y
DEBUG: Found N courses in course_departments table
DEBUG: Total unique courses to assign: N
DEBUG: Successfully assigned course X to user Y
DEBUG: Department course assignment SUMMARY - Assigned Count: N
```

### Scenario 2: Legacy Department Name Assignment
**Steps:**
1. Create a course with department field set (legacy method)
2. Create a department with the same name
3. Assign user to department
4. Check if user gets the legacy course

**Expected Results:**
- User should be assigned to courses matching department name
- Debug logs should show legacy course detection

### Scenario 3: No Courses Available
**Steps:**
1. Create a department with no assigned courses
2. Assign a user to this department
3. Check results

**Expected Results:**
- User should be added to department but no training assigned
- Success message should indicate no courses were assigned
- Debug logs should show "No courses found"

### Scenario 4: Error Conditions
**Steps:**
1. Try assigning with invalid department ID
2. Try assigning with invalid user ID
3. Check behavior when database tables are missing

**Expected Results:**
- Appropriate error messages
- Debug logs should show specific error conditions
- No partial assignments should occur

### Scenario 5: Bulk User Assignment
**Steps:**
1. Create multiple users
2. Assign them all to a department with courses
3. Check each user's training status

**Expected Results:**
- All users should be assigned to department courses
- Each assignment should be logged separately

### Scenario 6: User Session Updates
**Steps:**
1. Create a department with courses
2. Assign the current logged-in user to the department
3. Check if session updates immediately

**Expected Results:**
- User's session should update `user_is_in_training` flag
- Mobile training page should show new assignments

## Debug Log Analysis

### Successful Assignment Pattern:
```
DEBUG: Starting department course assignment - User ID: 123, Department ID: 5, Assigned by: 1
DEBUG: Found 2 courses in course_departments table
DEBUG: Found 1 courses via legacy department name 'Test Department'
DEBUG: Total unique courses to assign: 3
DEBUG: Attempting to assign course 1 to user 123
DEBUG: Successfully assigned course 1 to user 123
DEBUG: Attempting to assign course 2 to user 123
DEBUG: Successfully assigned course 2 to user 123
DEBUG: Attempting to assign course 3 to user 123
DEBUG: Successfully assigned course 3 to user 123
DEBUG: Department course assignment SUMMARY - User ID: 123, Department ID: 5, Assigned Count: 3, Failed Count: 0, Flag Update Result: SUCCESS
DEBUG: Updated session training flag for user 123 (if current user)
DEBUG: Department course assignment transaction committed successfully
```

### Failure Patterns:
**No courses found:**
```
DEBUG: Found 0 courses in course_departments table
DEBUG: Found 0 courses via legacy department name 'Test Department'
DEBUG: Total unique courses to assign: 0
DEBUG: No courses found for department 5
```

**Missing table:**
```
ERROR: Required table 'course_departments' does not exist
```

**Assignment failure:**
```
WARNING: assign_course_to_users returned 0 for course 5, user 123
ERROR: Failed to assign course 5 to user 123: [specific error message]
```

## Mobile App Integration Testing

### Mobile Training Page
After department assignment, verify:
1. Mobile training page shows assigned courses
2. Progress tracking works correctly
3. User can access assigned content
4. Navigation between courses functions

### Mobile User Profile
Check that:
1. User role displays correctly
2. Training status updates immediately
3. Session persistence works

## Database Verification

Run these SQL queries to verify assignments:

```sql
-- Check user department assignments
SELECT * FROM user_departments WHERE user_id = [USER_ID];

-- Check training assignments
SELECT * FROM user_training_assignments WHERE user_id = [USER_ID];

-- Check training flag
SELECT id, name, is_in_training FROM users WHERE id = [USER_ID];

-- Check department courses
SELECT cd.course_id, tc.name
FROM course_departments cd
JOIN training_courses tc ON cd.course_id = tc.id
WHERE cd.department_id = [DEPT_ID];
```

## Common Issues & Solutions

### Issue: No courses assigned
**Possible Causes:**
- No courses assigned to department
- Missing `course_departments` table
- Incorrect department ID
- Training helper function not available

**Solutions:**
1. Check debug logs for specific error messages
2. Verify department has courses assigned
3. Ensure all required database tables exist
4. Check that `includes/training_helpers.php` is being loaded

### Issue: Training flag not updated
**Possible Causes:**
- Database constraint failure
- Transaction rollback
- Session update failure

**Solutions:**
1. Check database for constraint violations
2. Verify transaction completion in logs
3. Manually update user flag as test

### Issue: Session not updating
**Possible Causes:**
- Different user being edited
- Session state not synchronized
- Cache issues

**Solutions:**
1. Verify correct user ID in session
2. Test with current user session
3. Clear session cache if needed

## Performance Considerations

The enhanced function includes:
- Early validation to prevent unnecessary processing
- Transaction safety to prevent partial assignments
- Efficient error handling to avoid memory leaks
- Detailed logging for debugging

## Rollback Plan

If issues occur, the enhanced logging will help identify:
- Specific failure points
- Data consistency issues
- Transaction conflicts
- Missing dependencies

All changes are backward compatible and don't affect existing functionality.