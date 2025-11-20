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

        // Find courses tied to this department
        $course_stmt = $pdo->prepare("
            SELECT course_id FROM course_departments
            WHERE department_id = ?
        ");
        $course_stmt->execute([$department_id]);
        $dept_courses = $course_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($dept_courses)) {
            // Check if the user still has any other reason to keep each course
            $other_dept_stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM course_departments cd
                JOIN user_departments ud ON cd.department_id = ud.department_id
                WHERE cd.course_id = ? AND ud.user_id = ?
            ");

            // Check if the assignment exists before attempting removals
            $assignment_exists_stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM user_training_assignments
                WHERE user_id = ? AND course_id = ?
            ");

            // A direct assignment exists when the user/course pair has no department link
            $direct_assignment_stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM user_training_assignments uta
                WHERE uta.user_id = ? AND uta.course_id = ?
                  AND NOT EXISTS (
                      SELECT 1
                      FROM course_departments cd
                      JOIN user_departments ud ON cd.department_id = ud.department_id
                      WHERE cd.course_id = uta.course_id AND ud.user_id = uta.user_id
                  )
            ");

            $delete_assignment_stmt = $pdo->prepare("
                DELETE FROM user_training_assignments
                WHERE user_id = ? AND course_id = ?
            ");

            $delete_progress_stmt = $pdo->prepare("
                DELETE FROM training_progress
                WHERE user_id = ? AND course_id = ?
            ");

            foreach ($dept_courses as $course_id) {
                // Skip if the user isn't assigned to this course
                $assignment_exists_stmt->execute([$user_id, $course_id]);
                if ((int)$assignment_exists_stmt->fetchColumn() === 0) {
                    continue;
                }

                // Keep the course if another department still links the user to it
                $other_dept_stmt->execute([$course_id, $user_id]);
                $other_dept_count = (int)$other_dept_stmt->fetchColumn();
                if ($other_dept_count > 0) {
                    continue;
                }

                // Keep the course if it's directly assigned outside of departments
                $direct_assignment_stmt->execute([$user_id, $course_id]);
                $direct_count = (int)$direct_assignment_stmt->fetchColumn();
                if ($direct_count > 0) {
                    continue;
                }

                // Otherwise, remove department-only training assignments and progress
                $delete_assignment_stmt->execute([$user_id, $course_id]);
                $delete_progress_stmt->execute([$user_id, $course_id]);
            }
        }

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

        // Assign course to each user in the department
        if (!empty($department_users)) {
            assign_course_to_users($pdo, $course_id, $department_users, $assigned_by);
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
        $pdo->beginTransaction();

        // Get all courses in this department
        $course_stmt = $pdo->prepare("
            SELECT course_id FROM course_departments
            WHERE department_id = ?
        ");
        $course_stmt->execute([$department_id]);
        $courses = $course_stmt->fetchAll(PDO::FETCH_COLUMN);

        $assigned_count = 0;

        // Assign each course to the user
        foreach ($courses as $course_id) {
            $assign_stmt = $pdo->prepare("
                INSERT INTO user_training_assignments (user_id, course_id, assigned_by, assigned_date)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE assigned_date = CURRENT_TIMESTAMP
            ");
            if ($assign_stmt->execute([$user_id, $course_id, $assigned_by])) {
                $assigned_count++;
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

            // Debug logging
            error_log("DEBUG: Department course assignment - User ID: $user_id, Assigned Count: $assigned_count, Flag Update Result: " . ($result ? 'SUCCESS' : 'FAILED'));

            // Update session if this is the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                $_SESSION['user_is_in_training'] = 1;
                error_log("DEBUG: Updated session training flag for user $user_id");
            }
        } else {
            error_log("DEBUG: No courses assigned to user $user_id from department $department_id");
        }

        $pdo->commit();
        return $assigned_count;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error assigning user to department courses: " . $e->getMessage());
        return 0;
    }
}

?>
