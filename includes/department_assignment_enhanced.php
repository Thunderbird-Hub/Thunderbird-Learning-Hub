<?php
/**
 * Enhanced Department Assignment System
 * Created: 2025-11-26
 * Author: Claude Code Assistant
 *
 * This file provides enhanced versions of the department assignment functions
 * with better error handling, debugging, and user feedback according to the diagnostic plan.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection
if (!isset($pdo) || !$pdo) {
    require_once __DIR__ . '/db_connect.php';
}

/**
 * Enhanced version of assign_user_to_department_courses with better error handling
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $department_id Department ID
 * @param int $assigned_by User ID making assignment
 * @return array Detailed result with success/failure information
 */
function assign_user_to_department_courses_enhanced($pdo, $user_id, $department_id, $assigned_by) {
    $result = [
        'success' => false,
        'courses_assigned' => 0,
        'courses_failed' => 0,
        'training_flag_set' => false,
        'errors' => [],
        'debug_info' => []
    ];

    try {
        // Enhanced debug logging
        $debug_msg = "ENHANCED DEBUG: Starting department course assignment - User ID: $user_id, Department ID: $department_id, Assigned by: $assigned_by";
        error_log($debug_msg);
        $result['debug_info'][] = $debug_msg;

        // Verify inputs
        if (empty($user_id) || empty($department_id) || empty($assigned_by)) {
            $error_msg = "ERROR: Invalid parameters for department course assignment";
            error_log($error_msg);
            $result['errors'][] = $error_msg;
            return $result;
        }

        // Verify user exists and is active
        $user_check = $pdo->prepare("SELECT id, name, is_active, role FROM users WHERE id = ?");
        $user_check->execute([$user_id]);
        $user_data = $user_check->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            $error_msg = "ERROR: User ID $user_id does not exist";
            error_log($error_msg);
            $result['errors'][] = $error_msg;
            return $result;
        }

        if (!$user_data['is_active']) {
            $error_msg = "ERROR: User {$user_data['name']} (ID: $user_id) is not active";
            error_log($error_msg);
            $result['errors'][] = $error_msg;
            return $result;
        }

        $debug_msg = "DEBUG: Verified user '{$user_data['name']}' (Role: {$user_data['role']}, Active: Yes)";
        error_log($debug_msg);
        $result['debug_info'][] = $debug_msg;

        // Verify department exists
        $dept_check = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
        $dept_check->execute([$department_id]);
        $dept_data = $dept_check->fetch(PDO::FETCH_ASSOC);

        if (!$dept_data) {
            $error_msg = "ERROR: Department ID $department_id does not exist";
            error_log($error_msg);
            $result['errors'][] = $error_msg;
            return $result;
        }

        $debug_msg = "DEBUG: Verified department '{$dept_data['name']}' (ID: $department_id)";
        error_log($debug_msg);
        $result['debug_info'][] = $debug_msg;

        // Check if required tables exist
        $tables_to_check = ['course_departments', 'training_courses', 'user_training_assignments'];
        foreach ($tables_to_check as $table) {
            try {
                $pdo->query("SELECT 1 FROM $table LIMIT 1");
                $debug_msg = "DEBUG: Table '$table' exists";
                $result['debug_info'][] = $debug_msg;
            } catch (PDOException $e) {
                $error_msg = "ERROR: Required table '$table' does not exist: " . $e->getMessage();
                error_log($error_msg);
                $result['errors'][] = $error_msg;
                return $result;
            }
        }

        $pdo->beginTransaction();

        // Get all courses in this department (modern mapping table)
        $course_stmt = $pdo->prepare("
            SELECT cd.course_id, tc.name as course_name, tc.is_active
            FROM course_departments cd
            JOIN training_courses tc ON cd.course_id = tc.id
            WHERE cd.department_id = ? AND tc.is_active = 1
        ");
        $course_stmt->execute([$department_id]);
        $courses = $course_stmt->fetchAll(PDO::FETCH_ASSOC);

        $debug_msg = "DEBUG: Found " . count($courses) . " active courses in course_departments table";
        error_log($debug_msg);
        $result['debug_info'][] = $debug_msg;

        // Fallback: also include legacy training_courses.department matches
        $legacy_courses = [];
        $dept_name_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $dept_name_stmt->execute([$department_id]);
        $department_name = $dept_name_stmt->fetchColumn();

        if (!empty($department_name)) {
            $legacy_stmt = $pdo->prepare("
                SELECT id, name as course_name, is_active
                FROM training_courses
                WHERE department = ? AND is_active = 1
                AND id NOT IN (SELECT course_id FROM course_departments WHERE department_id = ?)
            ");
            $legacy_stmt->execute([$department_name, $department_id]);
            $legacy_courses = $legacy_stmt->fetchAll(PDO::FETCH_ASSOC);

            $debug_msg = "DEBUG: Found " . count($legacy_courses) . " additional courses via legacy department name '$department_name'";
            error_log($debug_msg);
            $result['debug_info'][] = $debug_msg;
        }

        // Merge modern and legacy courses
        $all_courses = array_merge($courses, $legacy_courses);
        $debug_msg = "DEBUG: Total unique courses to assign: " . count($all_courses);
        error_log($debug_msg);
        $result['debug_info'][] = $debug_msg;

        if (empty($all_courses)) {
            $warning_msg = "WARNING: No active courses found for department '{$dept_data['name']}' (ID: $department_id)";
            error_log($warning_msg);
            $result['errors'][] = $warning_msg;
            $pdo->rollBack();
            return $result;
        }

        // Load training helpers if not already loaded
        if (!function_exists('assign_course_to_users')) {
            require_once __DIR__ . '/training_helpers.php';
        }

        $assigned_count = 0;
        $failed_assignments = [];

        // Assign each course to the user
        foreach ($all_courses as $course) {
            $course_id = $course['course_id'];
            $course_name = $course['course_name'];

            try {
                $debug_msg = "DEBUG: Attempting to assign course '$course_name' (ID: $course_id) to user {$user_data['name']} (ID: $user_id)";
                error_log($debug_msg);
                $result['debug_info'][] = $debug_msg;

                // Use the assign_course_to_users function with department context
                $assignment_result = assign_course_to_users($pdo, $course_id, [$user_id], $assigned_by, $department_id);

                if ($assignment_result > 0) {
                    $assigned_count++;
                    $debug_msg = "DEBUG: Successfully assigned course '$course_name' to user {$user_data['name']}";
                    error_log($debug_msg);
                    $result['debug_info'][] = $debug_msg;
                } else {
                    $warning_msg = "WARNING: assign_course_to_users returned 0 for course '$course_name', user {$user_data['name']}";
                    error_log($warning_msg);
                    $result['debug_info'][] = $warning_msg;
                    $failed_assignments[] = $course_name;
                }
            } catch (Exception $course_error) {
                $error_msg = "ERROR: Failed to assign course '$course_name' to user {$user_data['name']}: " . $course_error->getMessage();
                error_log($error_msg);
                $result['errors'][] = $error_msg;
                $failed_assignments[] = $course_name;
            }
        }

        // Set is_in_training flag if courses were assigned
        $flag_set = false;
        if ($assigned_count > 0) {
            try {
                $flag_stmt = $pdo->prepare("
                    UPDATE users
                    SET is_in_training = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $flag_result = $flag_stmt->execute([$user_id]);
                $flag_set = $flag_result;

                $debug_msg = $flag_result ?
                    "DEBUG: Successfully set is_in_training flag for user {$user_data['name']}" :
                    "ERROR: Failed to set is_in_training flag for user {$user_data['name']}";
                error_log($debug_msg);
                $result['debug_info'][] = $debug_msg;
                $result['training_flag_set'] = $flag_result;

                // Update session if this is the current user
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                    $_SESSION['user_is_in_training'] = 1;
                    $debug_msg = "DEBUG: Updated session training flag for user $user_id";
                    error_log($debug_msg);
                    $result['debug_info'][] = $debug_msg;
                }
            } catch (PDOException $flag_error) {
                $error_msg = "ERROR: Failed to set training flag: " . $flag_error->getMessage();
                error_log($error_msg);
                $result['errors'][] = $error_msg;
            }
        }

        // Summary logging
        $summary_msg = "SUMMARY - User: {$user_data['name']} (ID: $user_id), Department: {$dept_data['name']} (ID: $department_id), Assigned: $assigned_count, Failed: " . count($failed_assignments) . ", Flag Set: " . ($flag_set ? 'YES' : 'NO');
        error_log($summary_msg);
        $result['debug_info'][] = $summary_msg;

        if (!empty($failed_assignments)) {
            $failed_list = implode(', ', $failed_assignments);
            $debug_msg = "DEBUG: Failed course assignments: $failed_list";
            error_log($debug_msg);
            $result['debug_info'][] = $debug_msg;
        }

        $pdo->commit();

        $result['success'] = true;
        $result['courses_assigned'] = $assigned_count;
        $result['courses_failed'] = count($failed_assignments);

        return $result;

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "ERROR: PDO Exception in department course assignment: " . $e->getMessage() . " Trace: " . $e->getTraceAsString();
        error_log($error_msg);
        $result['errors'][] = $error_msg;
        return $result;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "ERROR: General Exception in department course assignment: " . $e->getMessage() . " Trace: " . $e->getTraceAsString();
        error_log($error_msg);
        $result['errors'][] = $error_msg;
        return $result;
    }
}

/**
 * Generate user-friendly feedback message based on assignment result
 * @param array $result Result from assign_user_to_department_courses_enhanced
 * @return string User-friendly message
 */
function generate_assignment_feedback_message($result) {
    if (!$result['success']) {
        if (!empty($result['errors'])) {
            return "❌ Assignment failed: " . $result['errors'][0];
        } else {
            return "❌ Assignment failed for unknown reasons.";
        }
    }

    $assigned = $result['courses_assigned'];
    $failed = $result['courses_failed'];
    $flag_set = $result['training_flag_set'];

    if ($assigned > 0) {
        $message = "✅ Successfully assigned user to $assigned course(s)";

        if ($flag_set) {
            $message .= " and enabled training access";
        } else {
            $message .= " (training flag may not be set)";
        }

        if ($failed > 0) {
            $message .= ". ⚠️ $failed course(s) failed to assign - check error logs";
        }

        return $message;
    } else {
        return "⚠️ User added to department but no courses were assigned. The department may not have any active courses.";
    }
}

/**
 * Check if department assignment workflow is working correctly
 * @param PDO $pdo Database connection
 * @return array System health status
 */
function check_department_assignment_system_health($pdo) {
    $health = [
        'status' => 'unknown',
        'issues' => [],
        'recommendations' => []
    ];

    try {
        // Check required tables
        $required_tables = ['departments', 'user_departments', 'course_departments', 'user_training_assignments', 'training_courses', 'users'];
        foreach ($required_tables as $table) {
            try {
                $pdo->query("SELECT 1 FROM $table LIMIT 1");
            } catch (PDOException $e) {
                $health['issues'][] = "Missing table: $table";
                $health['recommendations'][] = "Run migration: migrations/add_departments.sql";
            }
        }

        // Check required columns
        $column_checks = [
            ['users', 'is_in_training'],
            ['user_training_assignments', 'assignment_source'],
            ['user_training_assignments', 'department_id']
        ];

        foreach ($column_checks as [$table, $column]) {
            try {
                $stmt = $pdo->query("SELECT $column FROM $table LIMIT 1");
            } catch (PDOException $e) {
                $health['issues'][] = "Missing column: $table.$column";
                $health['recommendations'][] = "Run migration: database/migrations/setup_training_system_with_source_tracking.sql";
            }
        }

        // Check for orphaned records
        $orphan_checks = [
            "SELECT COUNT(*) as count FROM user_departments ud LEFT JOIN users u ON ud.user_id = u.id WHERE u.id IS NULL",
            "SELECT COUNT(*) as count FROM user_departments ud LEFT JOIN departments d ON ud.department_id = d.id WHERE d.id IS NULL",
            "SELECT COUNT(*) as count FROM course_departments cd LEFT JOIN training_courses tc ON cd.course_id = tc.id WHERE tc.id IS NULL",
            "SELECT COUNT(*) as count FROM course_departments cd LEFT JOIN departments d ON cd.department_id = d.id WHERE d.id IS NULL"
        ];

        foreach ($orphan_checks as $check_sql) {
            $stmt = $pdo->query($check_sql);
            $orphan_count = $stmt->fetch()['count'];
            if ($orphan_count > 0) {
                $health['issues'][] = "Found $orphan_count orphaned records";
                $health['recommendations'][] = "Clean up orphaned database records";
            }
        }

        // Check for users with department assignments but no training assignments
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM users u
            JOIN user_departments ud ON u.id = ud.user_id
            LEFT JOIN user_training_assignments uta ON u.id = uta.user_id
            WHERE u.is_active = 1 AND uta.id IS NULL
        ");
        $mismatch_count = $stmt->fetch()['count'];
        if ($mismatch_count > 0) {
            $health['issues'][] = "$mismatch_count users have department assignments but no training assignments";
            $health['recommendations'][] = "Run the diagnostic script to identify assignment issues";
        }

        // Check for users with training assignments but no training flag
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM users u
            JOIN user_training_assignments uta ON u.id = uta.user_id
            WHERE u.is_in_training = 0
        ");
        $flag_mismatch = $stmt->fetch()['count'];
        if ($flag_mismatch > 0) {
            $health['issues'][] = "$flag_mismatch users have training assignments but training flag not set";
            $health['recommendations'][] = "Run auto_manage_user_roles() for affected users";
        }

        if (empty($health['issues'])) {
            $health['status'] = 'healthy';
        } else {
            $health['status'] = 'issues_found';
        }

    } catch (PDOException $e) {
        $health['status'] = 'error';
        $health['issues'][] = "Database error during health check: " . $e->getMessage();
    }

    return $health;
}

?>