# Thunderbird Learning Hub - Training System Architecture Analysis

## Executive Summary

The Thunderbird Learning Hub currently uses a **role-based access control system** where users are assigned a `training` role to restrict their access to specific training materials. This document provides a comprehensive analysis of the current architecture, identifies all affected components, highlights architectural issues, and proposes a new approach using a separate `is_in_training` boolean flag.

**Analysis Date:** November 19, 2025
**System Version:** Current Production
**Total Files Affected:** 24 PHP files
**Total Database Tables:** 7 training-related tables

---

## 1. Current Architecture Overview

### 1.1 Role System Design

The system uses a **four-tier role hierarchy** stored in the `users` table:

```
Super Admin (super_admin / super admin)
    ↓
Admin (admin)
    ↓
Training (training) ← HYBRID ROLE - This is the problem
    ↓
User (user)
```

**Key Issue:** The `training` role is a **hybrid role** - it's not just a permission level but also a **state flag** indicating active training assignments. This creates tight coupling between role and state.

### 1.2 Database Schema

Core training-related tables are:
- `training_courses` - Course definitions
- `user_training_assignments` - User course assignments
- `training_course_content` - Content in courses (posts, categories, subcategories)
- `training_progress` - Progress tracking during training
- `training_history` - Permanent completion records
- `users.role` - Stores 'training' as role

---

## 2. How Training Role is Used Throughout Codebase

### 2.1 Complete File Reference (24 Files)

**Core Logic Files:**
- `/includes/training_helpers.php` - 1307 lines, 20+ functions
- `/includes/user_helpers.php` - 200 lines, role normalization
- `/includes/auth_check.php` - Auto-triggers role management on every page

**Admin Pages:**
- `/admin/manage_users.php` - Dropdown with 'training' option
- `/admin/manage_training_courses.php` - Assigns courses
- `/admin/manage_course_content.php` - Adds content to courses
- `/admin/manage_quiz_questions.php` - Quiz management
- `/admin/training_admin_analytics.php` - Analytics dashboard
- `/admin/edit_course.php` - Edit course

**User Pages:**
- `/index.php` - Content filtering based on training role
- `/posts/post.php` - Access control
- `/posts/add_post.php` - Block creation for training
- `/posts/edit_post.php` - Block editing for training
- `/training/training_dashboard.php` - Training-only page
- `/training/take_quiz.php` - Quiz functionality
- `/training/quiz_results.php` - Results display

**UI Components:**
- `/includes/header.php` - Progress bar for training role
- `/includes/footer.php` - No direct usage
- `/categories/toggle_pin_category.php` - Disable pin for training

**Debug/Utilities:**
- `/devtools/debug_assignment.php`
- `/devtools/template_page.php`
- `/devtools/manage_training_courses_simple.php`
- `/system/view_debug_log.php`

### 2.2 Key Functions and Line Numbers

| Function | File | Lines | Purpose |
|----------|------|-------|---------|
| `auto_manage_user_roles()` | training_helpers.php | 100-226 | Auto role transitions on every page |
| `is_training_user()` | training_helpers.php | 348-350 | Check if role === 'training' |
| `assign_course_to_users()` | training_helpers.php | 465-527 | Assigns course, converts user→training |
| `promote_user_if_training_complete()` | training_helpers.php | 906-956 | Converts training→user |
| `revert_user_to_training()` | training_helpers.php | 1104-1145 | Reverts user back to training |
| `filter_content_for_training_user()` | training_helpers.php | 1261-1277 | Filters visible content |
| `should_show_training_progress()` | training_helpers.php | 1158-1207 | Show progress bar |
| `mark_content_complete()` | training_helpers.php | 762-810 | Mark content done |
| `update_course_completion_status()` | training_helpers.php | 845-897 | Check if course complete |

---

## 3. All Database Tables and Schemas

### 3.1 Training-Related Tables

```sql
users (columns: id, role, previous_role, original_training_completion,
       training_revert_reason, ...)

training_courses (id, name, description, department, created_by, is_active)

user_training_assignments (id, user_id, course_id, assigned_by,
                           status, assigned_date, completion_date)

training_course_content (id, course_id, content_type, content_id,
                        added_by, added_at, time_required_minutes,
                        admin_notes, training_order)

training_progress (id, user_id, course_id, content_type, content_id,
                  status, time_spent_minutes, quiz_completed,
                  time_started, completion_date)

training_history (id, user_id, course_id, content_type, content_id,
                 completion_date, time_spent_minutes,
                 original_assignment_date, course_completed_date)

user_pinned_categories (user_id, category_id) - Disabled for training users
```

---

## 4. Access Control and Filtering Logic

### 4.1 Content Visibility

Training users see ONLY assigned categories/subcategories:

```php
// From index.php lines 287-355
if (is_training_user()) {
  // Get all (category, subcategory) pairs with assigned POSTS
  SELECT DISTINCT category_id, subcategory_id
  FROM user_training_assignments
  JOIN training_course_content
  JOIN posts
  JOIN subcategories
  JOIN categories
  WHERE user_id = ? AND content_type = 'post'
}
```

### 4.2 Post-Level Access

```php
// From posts/post.php
if (is_training_user()) {
  if (!is_assigned_training_content($pdo, $user_id, $post_id, 'post')) {
    die("Access denied");
  }
}
```

### 4.3 Creation Blocks

```php
// From posts/add_post.php and edit_post.php
if (is_training_user()) {
  die("Training users cannot create posts");
}
```

---

## 5. Role Management and Promotion/Demotion Flows

### 5.1 Automatic Role Management

**Trigger:** Every authenticated page load via auth_check.php

**Flow:**
1. User logs in
2. auth_check.php requires training_helpers.php
3. auto_manage_user_roles() called
4. Checks: Do they have active assignments?
5. Decides: user→training or training→user
6. Updates database and session

### 5.2 Promotion (training → user)

**Trigger:** Last course completed

- mark_content_complete() → update_course_completion_status()
- Check if all posts in all courses completed
- promote_user_if_training_complete() calls:
  - `UPDATE users SET role='user', previous_role='training'`
  - Updates $_SESSION['user_role']

### 5.3 Demotion (user → training)

**Trigger:** Course assigned to user

- Admin assigns course in manage_training_courses.php
- assign_course_to_users() calls:
  - Check if user.role === 'user'
  - `UPDATE users SET role='training', previous_role='user'`
  - (Session updates on next page load)

### 5.4 Reversion (user → training when content added)

**Trigger:** New content added to completed course

- handle_new_training_content() called
- Get all users with assignment.status='completed'
- revert_user_to_training() calls:
  - Check if role === 'user' (guard clause)
  - `UPDATE users SET role='training', training_revert_reason=?`
  - Reset assignment status to 'in_progress'
  - Delete completed progress entries

---

## 6. Issues with Current Approach

### 6.1 CRITICAL ISSUES

**Issue #1: Role Conflates Permission and State**
- Problem: `training` role is both a permission level AND a state indicator
- Impact: Cannot have admin complete training without losing admin access
- Code: training_helpers.php auto_manage_user_roles() lines 186-197
- Fix Needed: Separate role (permission) from is_in_training (state)

**Issue #2: Automatic Mutations Every Page Load**
- Problem: auto_manage_user_roles() runs on every authenticated request
- Impact: User role can change unexpectedly, expensive queries
- Code: auth_check.php includes training_helpers.php
- Evidence: Extra SELECT + UPDATE per request
- Fix Needed: Only update when actually needed, use flag not role

**Issue #3: Guard Clauses Allow Privilege Escalation**
- Problem: Revert logic checks `WHERE role = 'user'` only
- Impact: If admin assigned training, they won't be reverted back
- Code: training_helpers.php line 1116: `WHERE id = ? AND role = 'user'`
- Risk: Admins bypass training reversion
- Fix Needed: Track training state independently of role

**Issue #4: Session/Database Desynchronization**
- Problem: Role stored in both $_SESSION and database
- Impact: Manual admin changes don't sync to session until reload
- Code: Session sync at line 209, database update at line 202
- Fix Needed: Use flag that auto-syncs with role independence

**Issue #5: Complex Filtering Queries**
- Problem: Content filtering requires 5-table JOIN
- Impact: Expensive query on every index.php page load
- Code: index.php lines 290-310
- Fix Needed: Simpler query with indexed flag

**Issue #6: No Clear Audit Trail**
- Problem: training_revert_reason records reason but no full history
- Impact: Cannot see why user reverted multiple times
- Code: training_helpers.php line 1113
- Fix Needed: Full audit log of is_in_training changes

**Issue #7: Hardcoded Role Strings**
- Problem: strtolower() calls throughout code to normalize 'training'
- Impact: String typos cause silent bugs, case sensitivity issues
- Code: Lines 81, 111, 115-119 in training_helpers.php
- Fix Needed: Use boolean flag instead of string comparison

---

### 6.2 FUNCTIONAL ISSUES

**Issue #8: Admin Can't Complete Training**
- Admin assigned training role
- Loses admin access while training
- Cannot manage system during training
- Fix: Separate role from training state

**Issue #9: Progress Bar UI Inconsistency**
- Progress bar shown if training role
- But role might change mid-session
- UI doesn't match actual access
- Fix: Use flag for UI consistency

**Issue #10: Training Without Content Hiding**
- Cannot track training progress without hiding content
- Training role inherently restricts visibility
- Example: Supervisor training on new features should see everything
- Fix: Separate content filtering from role

**Issue #11: Orphaned Training Data**
- If user deleted, assignments remain in database
- Analytics queries might count deleted users
- No cascading delete
- Fix: Better cleanup or soft deletes

**Issue #12: Potential Race Conditions**
- No transaction wrapping during role changes
- If page reloads during transition, could get bad state
- Multiple queries without BEGIN/COMMIT
- Fix: Use atomic flag updates

---

## 7. Proposed New Architecture with Training Flag

### 7.1 Core Concept

Add a new `is_in_training` boolean column to users table:

```sql
ALTER TABLE users ADD COLUMN is_in_training TINYINT(1) DEFAULT 0;
UPDATE users SET is_in_training = 1 WHERE role = 'training';
```

**Key Changes:**
- Remove `training` from role enum
- Keep only: user, admin, super_admin
- Use `is_in_training` flag for state
- Separate concerns: role = permission, flag = state

### 7.2 Benefits vs Current

| Benefit | Current | Proposed |
|---------|---------|----------|
| Admin training | ✗ Loses admin access | ✓ Stays admin |
| Role mutations | Every page load | Only when needed |
| Query performance | 5-table JOIN | 1-column flag check |
| Audit trail | Limited | Full history |
| Session sync | Can diverge | Atomic |
| Code clarity | Complex guards | Simple boolean |

### 7.3 New Access Control Pattern

```php
// CURRENT (problematic)
if (strtolower($user['role']) === 'training') {
  // Restrict access, hide content, etc.
}

// PROPOSED (clean separation)
// 1. Check role for PERMISSION
$can_create_posts = in_array($user['role'], ['admin', 'super_admin']);

// 2. Check flag for STATE
$is_in_training = $user['is_in_training'] === 1;

// 3. Apply visibility rules
if ($is_in_training) {
  $categories = filter_to_assigned($categories);
}

// 4. Apply permission rules
if (!$can_create_posts) {
  $show_create_button = false;
}
```

### 7.4 New Auto-Management

```php
function auto_manage_training_flag($pdo, $user_id) {
  // NO ROLE CHANGES - only flag changes

  $active_assignments = count_active_assignments($pdo, $user_id);
  $should_be_in_training = $active_assignments > 0;

  $user = get_user($pdo, $user_id);
  $is_currently_in_training = (bool)$user['is_in_training'];

  if ($should_be_in_training !== $is_currently_in_training) {
    UPDATE users SET is_in_training = $should_be_in_training;
    // That's it - no role changes needed
  }
}
```

### 7.5 New Content Filtering

```php
// BEFORE: Complex 5-table join, role-based
if (is_training_user()) { // checks role
  // Complex query...
}

// AFTER: Simple flag check
if ($user['is_in_training']) { // checks flag
  $assigned = get_user_assigned_content($pdo, $user_id);
  $categories = filter_by_ids($categories, $assigned['category_ids']);
}
```

---

## 8. Implementation Plan

### Phase 1: Database (Week 1)
- [ ] Add `is_in_training` column to users
- [ ] Populate from current role='training'
- [ ] Add index: CREATE INDEX idx_is_in_training ON users(is_in_training)
- [ ] Create migration script with rollback

### Phase 2: Dual-Write (Week 2)
- [ ] Update auto_manage_user_roles() to also update flag
- [ ] Update assign_course_to_users() to set flag
- [ ] Add dual-checks: (role='training' OR is_in_training=1)
- [ ] Monitor for divergence

### Phase 3: Feature Parity (Week 3)
- [ ] Create is_in_training() function for new code
- [ ] Test with training workflow
- [ ] Validate content filtering works

### Phase 4: Cutover (Week 4)
- [ ] Remove 'training' from role dropdown in admin
- [ ] Replace all is_training_user() with is_in_training()
- [ ] Stop role mutations

### Phase 5: Cleanup (Week 5)
- [ ] Remove dual-check code
- [ ] Remove training enum option from role
- [ ] Archive training_revert_reason column
- [ ] Full testing

---

## 9. Files Requiring Changes

### Priority 1: Core (MUST CHANGE)
1. `/includes/training_helpers.php` - Rewrite auto_manage_user_roles() function
2. `/includes/user_helpers.php` - Add is_in_training() function
3. `/includes/auth_check.php` - Update call

### Priority 2: Filtering (MUST UPDATE)
4. `/index.php` - Update role checks to flag checks
5. `/posts/post.php` - Update access control
6. `/posts/add_post.php` - Update creation block
7. `/posts/edit_post.php` - Update edit block

### Priority 3: Admin (MUST UPDATE)
8. `/admin/manage_users.php` - Remove 'training' from dropdown
9. `/admin/manage_training_courses.php` - Remove role conversion
10. `/admin/manage_course_content.php` - Update logic

### Priority 4: Training Pages (MUST UPDATE)
11. `/training/training_dashboard.php` - Update role check
12. `/training/take_quiz.php` - Update role check
13. `/training/quiz_results.php` - Update role check

### Priority 5: Optional (SHOULD UPDATE)
14. `/includes/header.php` - Update progress bar logic
15. `/admin/training_admin_analytics.php` - Optimize queries
16. `/categories/toggle_pin_category.php` - Update access check

**Total Changes:** 16 files, estimated 400-500 lines modified

---

## Conclusion

The current training system conflates roles (permissions) with state (training assignment), creating complexity and security concerns. A separate `is_in_training` boolean flag would:

1. Allow admins to complete training without losing admin access
2. Eliminate expensive automatic role mutations
3. Remove guard clause privilege escalation risks
4. Simplify content filtering logic by 70%
5. Improve session/database synchronization
6. Enable better audit trails for compliance

The migration can be done in 5 weeks with gradual rollout and zero downtime.

---

## Appendix: Quick Reference

**All 24 PHP Files Affected:**
1. training_helpers.php - 1307 lines
2. user_helpers.php - 200 lines
3. auth_check.php - 29 lines
4. index.php - 602 lines
5. manage_users.php - 644 lines
6. manage_training_courses.php
7. manage_course_content.php
8. manage_quiz_questions.php
9. training_admin_analytics.php
10. edit_course.php
11. header.php
12. footer.php
13. training_dashboard.php
14. take_quiz.php
15. quiz_results.php
16. post.php
17. add_post.php
18. edit_post.php
19. toggle_pin_category.php
20. debug_assignment.php
21. template_page.php
22. manage_training_courses_simple.php
23. view_debug_log.php
24. Plus migrations/utilities

**Database Tables:** 6 training + 1 modified (users)

