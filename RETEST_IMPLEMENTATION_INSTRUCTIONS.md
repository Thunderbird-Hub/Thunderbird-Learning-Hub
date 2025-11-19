# Quiz Retest Period Feature - Implementation Instructions

## Overview
This feature allows administrators to set retest periods for quizzes, similar to McDonald's training system where users must retake quizzes every few months.

## What's Been Implemented

### 1. Database Schema
The database migration script (`migrations/add_retest_periods.sql`) adds:
- `retest_period_months` column to `training_quizzes` table
- `last_completed_date` and `retest_required` columns to `user_quiz_attempts` table
- `quiz_retest_log` table for tracking retest history
- `retest_exempt` column to `user_training_assignments` table

### 2. Quiz Management Integration
**🎯 FEATURE NOW MOVED TO MANAGE_QUIZZES.PAGE**

The retest period functionality has been integrated directly into the quiz creation and editing workflow in `admin/manage_quizzes.php`:

**Quiz Creation Form:**
- Added "Retest Period" dropdown with options: No retest, 1, 3, 6, 12, 24 months
- Clear description explaining it's like McDonald's training system
- Clean, modern styling that matches existing form elements

**Quiz Editing:**
- Edit modal now includes retest period field
- AJAX-powered editing that populates current retest period values
- Same dropdown options as creation form

**Quiz Display:**
- Each quiz card now shows its retest period setting
- Clear "Retest: Every X months" or "Retest: Not required" display
- Integrated with existing quiz metadata display

## How to Complete Implementation

### Step 1: Run Database Migration
1. Access your MySQL database (via phpMyAdmin, command line, or your preferred tool)
2. Run the SQL commands from `migrations/add_retest_periods.sql`
3. Verify the new columns and tables were created successfully

### Step 2: Test the Feature
1. Navigate to `/admin/manage_quizzes.php` as an admin
2. **Create New Quiz:** Scroll to the "Create New Quiz" section and you'll see the new "Retest Period" dropdown
3. **Edit Existing Quiz:** Click the "✏️ Edit Quiz" button on any quiz to see the retest period in the edit modal
4. **Check Display:** Each quiz card shows its current retest period setting

### Step 3: Configure Retest Periods
- Go to the quiz management page (`/admin/manage_quizzes.php`)
- **For new quizzes:** Set the retest period when creating the quiz
- **For existing quizzes:** Click "✏️ Edit Quiz" to modify the retest period
- Choose from: No retest, 1 month, 3 months, 6 months, 12 months, or 24 months
- The setting is saved automatically when you create or update the quiz

## How It Works

### Setting Retest Periods
- Administrators can set retest periods per quiz
- Periods are measured in months from the date of quiz completion
- Users who passed a quiz will need to retake it after the specified period

### Processing Retest Periods
- The "Process Retest Periods" button checks all completed quizzes
- Users whose quiz completion date + retest period ≤ today are marked for retest
- The `retest_required` flag is set to TRUE for affected users
- A log entry is created in `quiz_retest_log` tracking why the retest was required

### User Experience
- When users access their training dashboard, they'll see visual indicators for quizzes that need retaking
- The quiz will become available again for them to retake
- Their previous attempts are preserved for record-keeping

## Future Enhancements
- Automatic email notifications when retests are required
- Dashboard indicators showing users how many days until their next retest
- Bulk retest period configuration for multiple quizzes
- Reports on retest compliance rates

## Files Modified
- `admin/training_admin_analytics.php` - Added retest management interface
- `migrations/add_retest_periods.sql` - Database schema changes
- `migrations/add_retest_periods.php` - PHP migration script (requires PHP CLI)

## Technical Notes
- The feature uses AJAX for responsive UI interactions
- Bootstrap modal for setting retest periods
- Modern styling consistent with the existing dashboard design
- Proper error handling and user feedback
- Security: Admin-only access to retest management