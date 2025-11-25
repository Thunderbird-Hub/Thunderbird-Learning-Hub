<?php
/**
 * Department Helper Functions
 * Created: 2025-11-19
 * Author: Claude Code Assistant
 *
 * This file contains all functions for managing departments,
 * assigning users to departments, and assigning courses to departments.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection
if (!isset($pdo) || !$pdo) {
    require_once __DIR__ . '/db_connect.php';
}

/**
 * Check if a column exists on the current database
 */
function column_exists_in_table($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Column existence check failed for {$table}.{$column}: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// DEPARTMENT CRUD FUNCTIONS
// ============================================================

/**
 * Create a new department
 * @param PDO $pdo Database connection
 * @param string $name Department name
 * @param string $description Department description
 * @param int $created_by User ID of creator
 * @return int Department ID or false on failure
 */
function create_department($pdo, $name, $description, $created_by) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO departments (name, description, created_by)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$name, $description, $created_by]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating department: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all departments
 * @param PDO $pdo Database connection
 * @return array List of departments with member counts
 */
function get_all_departments($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT
                d.*,
                COUNT(DISTINCT ud.user_id) as member_count,
                COUNT(DISTINCT cd.course_id) as course_count,
                u.name as creator_name
            FROM departments d
            LEFT JOIN user_departments ud ON d.id = ud.department_id
            LEFT JOIN course_departments cd ON d.id = cd.department_id
            LEFT JOIN users u ON d.created_by = u.id
            GROUP BY d.id
            ORDER BY d.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get department by ID
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return array|null Department data or null if not found
 */
function get_department_by_id($pdo, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                d.*,
                COUNT(DISTINCT ud.user_id) as member_count,
                COUNT(DISTINCT cd.course_id) as course_count,
                u.name as creator_name
            FROM departments d
            LEFT JOIN user_departments ud ON d.id = ud.department_id
            LEFT JOIN course_departments cd ON d.id = cd.department_id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ?
            GROUP BY d.id
        ");
        $stmt->execute([$department_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching department: " . $e->getMessage());
        return null;
    }
}

/**
 * Update department
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @param string $name New name
 * @param string $description New description
 * @return bool Success status
 */
function update_department($pdo, $department_id, $name, $description) {
    try {
        $stmt = $pdo->prepare("
            UPDATE departments
            SET name = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$name, $description, $department_id]);
    } catch (PDOException $e) {
        error_log("Error updating department: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete department
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return bool Success status
 */
function delete_department($pdo, $department_id) {
    try {
        $pdo->beginTransaction();

        // Delete associated course assignments
        $stmt = $pdo->prepare("DELETE FROM course_departments WHERE department_id = ?");
        $stmt->execute([$department_id]);

        // Delete associated user assignments
        $stmt = $pdo->prepare("DELETE FROM user_departments WHERE department_id = ?");
        $stmt->execute([$department_id]);

        // Delete department
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $result = $stmt->execute([$department_id]);

        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting department: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// USER-DEPARTMENT MANAGEMENT FUNCTIONS
// ============================================================

/**
 * Assign user to department
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $department_id Department ID
 * @param int $assigned_by User ID of assigner
 * @return bool Success status
 */
function assign_user_to_department($pdo, $user_id, $department_id, $assigned_by) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_departments (user_id, department_id, assigned_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE assigned_date = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$user_id, $department_id, $assigned_by]);
    } catch (PDOException $e) {
        error_log("Error assigning user to department: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove user from department
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $department_id Department ID
 * @return bool Success status
 */
function remove_user_from_department($pdo, $user_id, $department_id) {
    try {
        $pdo->beginTransaction();

        // Remove the user -> department link first
        $stmt = $pdo->prepare("
            DELETE FROM user_departments
            WHERE user_id = ? AND department_id = ?
        ");
        $stmt->execute([$user_id, $department_id]);

        // Smart removal: Only remove training assignments that came from this specific department
        // Use the new assignment_source field to precisely target department-sourced assignments
        $remove_assignments_stmt = $pdo->prepare("
            DELETE FROM user_training_assignments
            WHERE user_id = ?
            AND assignment_source = 'department'
            AND department_id = ?
        ");
        $remove_assignments_stmt->execute([$user_id, $department_id]);
        $removed_assignments = $remove_assignments_stmt->rowCount();

        // Also remove training progress for assignments that were removed
        if ($removed_assignments > 0) {
            // Get the course IDs that were removed to clean up progress
            $removed_courses_stmt = $pdo->prepare("
                SELECT course_id FROM user_training_assignments
                WHERE user_id = ?
                AND assignment_source = 'department'
                AND department_id = ?
            ");
            $removed_courses_stmt->execute([$user_id, $department_id]);
            $removed_courses = $removed_courses_stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($removed_courses)) {
                // Remove progress for the specific courses that were removed
                $placeholders = str_repeat('?,', count($removed_courses) - 1) . '?';
                $progress_delete_stmt = $pdo->prepare("
                    DELETE FROM training_progress
                    WHERE user_id = ?
                    AND course_id IN ($placeholders)
                ");
                $progress_params = array_merge([$user_id], $removed_courses);
                $progress_delete_stmt->execute($progress_params);
            }
        }

        // Check if user has any remaining training assignments and clear flag if none
        remove_training_if_none_remaining($pdo, $user_id);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error removing user from department: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's departments
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array List of departments
 */
function get_user_departments($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*,
                   COUNT(DISTINCT ud2.user_id) as member_count
            FROM departments d
            JOIN user_departments ud ON d.id = ud.department_id
            LEFT JOIN user_departments ud2 ON d.id = ud2.department_id
            WHERE ud.user_id = ?
            GROUP BY d.id
            ORDER BY d.name ASC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get department members
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return array List of users in department
 */
function get_department_members($pdo, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*,
                   ud.assigned_date,
                   admin.name as assigned_by_name,
                   COUNT(DISTINCT p.id) as post_count,
                   COUNT(DISTINCT r.id) as reply_count
            FROM users u
            JOIN user_departments ud ON u.id = ud.user_id
            LEFT JOIN users admin ON ud.assigned_by = admin.id
            LEFT JOIN posts p ON u.id = p.user_id
            LEFT JOIN replies r ON u.id = r.user_id
            WHERE ud.department_id = ?
            AND u.is_active = 1
            GROUP BY u.id
            ORDER BY u.name ASC
        ");
        $stmt->execute([$department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching department members: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user is in department
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $department_id Department ID
 * @return bool True if user is in department
 */
function is_user_in_department($pdo, $user_id, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM user_departments
            WHERE user_id = ? AND department_id = ?
        ");
        $stmt->execute([$user_id, $department_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking user department: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// COURSE-DEPARTMENT MANAGEMENT FUNCTIONS
// ============================================================

/**
 * Assign course to department
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param int $department_id Department ID
 * @param int $assigned_by User ID of assigner
 * @return bool Success status
 */
function assign_course_to_department($pdo, $course_id, $department_id, $assigned_by) {
    try {
        $pdo->beginTransaction();

        // Insert course-department mapping
        $stmt = $pdo->prepare("
            INSERT INTO course_departments (course_id, department_id, assigned_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE assigned_date = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$course_id, $department_id, $assigned_by]);

        // Get all users in this department
        $dept_stmt = $pdo->prepare("SELECT user_id FROM user_departments WHERE department_id = ?");
        $dept_stmt->execute([$department_id]);
        $department_users = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

        // Assign course to each user in the department using the training helper function with department context
        if (!empty($department_users)) {
            assign_course_to_users($pdo, $course_id, $department_users, $assigned_by, $department_id);
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error assigning course to department: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove course from department
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param int $department_id Department ID
 * @return bool Success status
 */
function remove_course_from_department($pdo, $course_id, $department_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM course_departments
            WHERE course_id = ? AND department_id = ?
        ");
        return $stmt->execute([$course_id, $department_id]);
    } catch (PDOException $e) {
        error_log("Error removing course from department: " . $e->getMessage());
        return false;
    }
}

/**
 * Get courses assigned to department
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return array List of courses
 */
function get_department_courses($pdo, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*,
                   u.name as creator_name,
                   COUNT(DISTINCT uta.user_id) as assigned_users,
                   COUNT(DISTINCT CASE WHEN uta.status = 'completed' THEN uta.user_id END) as completed_users
            FROM training_courses tc
            JOIN course_departments cd ON tc.id = cd.course_id
            LEFT JOIN users u ON tc.created_by = u.id
            LEFT JOIN user_training_assignments uta ON tc.id = uta.course_id
            WHERE cd.department_id = ?
            GROUP BY tc.id
            ORDER BY tc.name ASC
        ");
        $stmt->execute([$department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching department courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if course is assigned to department
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param int $department_id Department ID
 * @return bool True if course is assigned to department
 */
function is_course_in_department($pdo, $course_id, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM course_departments
            WHERE course_id = ? AND department_id = ?
        ");
        $stmt->execute([$course_id, $department_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking course department: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all users in a department's courses (for bulk assignment)
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return array List of users who should take department courses
 */
function get_department_users_for_courses($pdo, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.role, u.is_in_training
            FROM users u
            JOIN user_departments ud ON u.id = ud.user_id
            WHERE ud.department_id = ?
            AND u.is_active = 1
            ORDER BY u.name ASC
        ");
        $stmt->execute([$department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching department users for courses: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// DEPARTMENT ASSIGNMENT WHEN USER/COURSE ADDED
// ============================================================

/**
 * When a new user is added to a department, assign them to existing department courses
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $department_id Department ID
 * @param int $assigned_by User ID making assignment
 * @return int Number of courses assigned
 */
function assign_user_to_department_courses($pdo, $user_id, $department_id, $assigned_by) {
    try {
        // Enhanced debug logging
        error_log("DEBUG: Starting department course assignment - User ID: $user_id, Department ID: $department_id, Assigned by: $assigned_by");

        // Verify inputs
        if (empty($user_id) || empty($department_id) || empty($assigned_by)) {
            error_log("ERROR: Invalid parameters for department course assignment");
            return 0;
        }

        // Check if required tables exist
        $tables_to_check = ['course_departments', 'training_courses', 'user_training_assignments', 'users'];
        foreach ($tables_to_check as $table) {
            try {
                $pdo->query("SELECT 1 FROM $table LIMIT 1");
            } catch (PDOException $e) {
                error_log("ERROR: Required table '$table' does not exist");
                return 0;
            }
        }

        $pdo->beginTransaction();

        // Get all courses in this department (modern mapping table)
        $course_stmt = $pdo->prepare("
            SELECT DISTINCT course_id FROM course_departments
            WHERE department_id = ?
        ");
        $course_stmt->execute([$department_id]);
        $courses = $course_stmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("DEBUG: Found " . count($courses) . " courses in course_departments table");

        // Fallback: also include legacy training_courses.department matches
        $legacy_courses = [];
        $dept_name_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $dept_name_stmt->execute([$department_id]);
        $department_name = $dept_name_stmt->fetchColumn();

        if (!empty($department_name)) {
            $legacy_stmt = $pdo->prepare("
                SELECT id
                FROM training_courses
                WHERE department = ?
            ");
            $legacy_stmt->execute([$department_name]);
            $legacy_courses = $legacy_stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("DEBUG: Found " . count($legacy_courses) . " courses via legacy department name '$department_name'");
        }

        if (!empty($legacy_courses)) {
            // Merge and de-duplicate courses from both mapping methods
            $courses = array_values(array_unique(array_merge($courses, $legacy_courses)));
        }

        error_log("DEBUG: Total unique courses to assign: " . count($courses));

        if (empty($courses)) {
            error_log("DEBUG: No courses found for department $department_id");
            $pdo->commit();
            return 0;
        }

        $assigned_count = 0;
        $failed_assignments = [];

        // Assign each course to the user using the training helper function with department context
        foreach ($courses as $course_id) {
            try {
                error_log("DEBUG: Attempting to assign course $course_id to user $user_id");

                // Check if training helper function exists
                if (!function_exists('assign_course_to_users')) {
                    error_log("ERROR: assign_course_to_users function not found");
                    throw new Exception("Training helper function not available");
                }

                // Use the updated assign_course_to_users function with department_id parameter
                $result = assign_course_to_users($pdo, $course_id, [$user_id], $assigned_by, $department_id);

                if ($result > 0) {
                    $assigned_count++;
                    error_log("DEBUG: Successfully assigned course $course_id to user $user_id");
                } else {
                    error_log("WARNING: assign_course_to_users returned 0 for course $course_id, user $user_id");
                    $failed_assignments[] = $course_id;
                }
            } catch (Exception $course_error) {
                error_log("ERROR: Failed to assign course $course_id to user $user_id: " . $course_error->getMessage());
                $failed_assignments[] = $course_id;
            }
        }

        // Set is_in_training flag if courses were assigned
        if ($assigned_count > 0) {
            $flag_stmt = $pdo->prepare("
                UPDATE users
                SET is_in_training = 1
                WHERE id = ?
            ");
            $result = $flag_stmt->execute([$user_id]);

            // Enhanced debug logging
            error_log("DEBUG: Department course assignment SUMMARY - User ID: $user_id, Department ID: $department_id, Assigned Count: $assigned_count, Failed Count: " . count($failed_assignments) . ", Flag Update Result: " . ($result ? 'SUCCESS' : 'FAILED'));

            if (!empty($failed_assignments)) {
                error_log("DEBUG: Failed course assignments: " . implode(', ', $failed_assignments));
            }

            // Update session if this is the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                $_SESSION['user_is_in_training'] = 1;
                error_log("DEBUG: Updated session training flag for user $user_id");
            }
        } else {
            error_log("DEBUG: No courses assigned to user $user_id from department $department_id. All assignments failed or no courses found.");
        }

        $pdo->commit();
        error_log("DEBUG: Department course assignment transaction committed successfully");
        return $assigned_count;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("ERROR: PDO Exception in department course assignment: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
        return 0;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERROR: General Exception in department course assignment: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
        return 0;
    }
}

?>
