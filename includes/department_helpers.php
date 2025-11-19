<?php
/**
 * Department Helper Functions
 * Phase 1 of Enhanced Training System with Departments
 * Created: 2025-11-19
 *
 * This file contains all functions for managing departments,
 * user-department assignments, and course-department associations.
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/db_connect.php';
}

// ============================================================
// DEPARTMENT MANAGEMENT FUNCTIONS
// ============================================================

/**
 * Create a new department
 * @param PDO $pdo Database connection
 * @param string $name Department name
 * @param string $description Department description
 * @param int $created_by User ID of creator
 * @return int|false Department ID on success, false on failure
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
 * @param bool $active_only Only show departments with members
 * @return array List of departments with member counts
 */
function get_all_departments($pdo, $active_only = false) {
    try {
        $sql = "
            SELECT d.*,
                   COUNT(DISTINCT ud.user_id) as member_count,
                   COUNT(DISTINCT cd.course_id) as course_count,
                   u.name as created_by_name
            FROM departments d
            LEFT JOIN user_departments ud ON d.id = ud.department_id
            LEFT JOIN course_departments cd ON d.id = cd.department_id
            LEFT JOIN users u ON d.created_by = u.id
        ";

        if ($active_only) {
            $sql .= " WHERE COUNT(DISTINCT ud.user_id) > 0";
        }

        $sql .= " GROUP BY d.id ORDER BY d.name ASC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a single department by ID with full details
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return array|null Department data or null if not found
 */
function get_department_by_id($pdo, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*,
                   COUNT(DISTINCT ud.user_id) as member_count,
                   COUNT(DISTINCT cd.course_id) as course_count,
                   u.name as created_by_name
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
        error_log("Error getting department: " . $e->getMessage());
        return null;
    }
}

/**
 * Update department information
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @param string $name Department name
 * @param string $description Department description
 * @return bool Success status
 */
function update_department($pdo, $department_id, $name, $description) {
    try {
        $stmt = $pdo->prepare("
            UPDATE departments
            SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$name, $description, $department_id]);
    } catch (PDOException $e) {
        error_log("Error updating department: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a department
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return bool Success status
 */
function delete_department($pdo, $department_id) {
    try {
        // Cascading deletes are handled by database foreign keys
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        return $stmt->execute([$department_id]);
    } catch (PDOException $e) {
        error_log("Error deleting department: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// USER-DEPARTMENT FUNCTIONS
// ============================================================

/**
 * Get departments for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array List of departments user is in
 */
function get_user_departments($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*,
                   COUNT(DISTINCT ud.user_id) as member_count
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
        error_log("Error getting user departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all members of a department
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return array List of users in department
 */
function get_department_members($pdo, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*,
                   ud.assigned_date,
                   a.name as assigned_by_name
            FROM users u
            JOIN user_departments ud ON u.id = ud.user_id
            LEFT JOIN users a ON ud.assigned_by = a.id
            WHERE ud.department_id = ?
            ORDER BY u.name ASC
        ");
        $stmt->execute([$department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting department members: " . $e->getMessage());
        return [];
    }
}

/**
 * Assign a user to a department
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $department_id Department ID
 * @param int $assigned_by User ID of person making assignment
 * @return bool Success status
 */
function assign_user_to_department($pdo, $user_id, $department_id, $assigned_by) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_departments (user_id, department_id, assigned_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by)
        ");
        return $stmt->execute([$user_id, $department_id, $assigned_by]);
    } catch (PDOException $e) {
        error_log("Error assigning user to department: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove a user from a department
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $department_id Department ID
 * @return bool Success status
 */
function remove_user_from_department($pdo, $user_id, $department_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM user_departments
            WHERE user_id = ? AND department_id = ?
        ");
        return $stmt->execute([$user_id, $department_id]);
    } catch (PDOException $e) {
        error_log("Error removing user from department: " . $e->getMessage());
        return false;
    }
}

/**
 * Assign multiple users to a department
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @param array $user_ids Array of user IDs
 * @param int $assigned_by User ID of person making assignments
 * @return int Number of users assigned
 */
function assign_users_to_department($pdo, $department_id, $user_ids, $assigned_by) {
    try {
        $pdo->beginTransaction();
        $assigned_count = 0;

        $stmt = $pdo->prepare("
            INSERT INTO user_departments (user_id, department_id, assigned_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by)
        ");

        foreach ($user_ids as $user_id) {
            if ($stmt->execute([$user_id, $department_id, $assigned_by])) {
                $assigned_count++;
            }
        }

        $pdo->commit();
        return $assigned_count;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error assigning users to department: " . $e->getMessage());
        return 0;
    }
}

// ============================================================
// COURSE-DEPARTMENT FUNCTIONS
// ============================================================

/**
 * Get departments for a course
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return array List of departments course is assigned to
 */
function get_course_departments($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.*,
                   COUNT(DISTINCT ud.user_id) as member_count
            FROM departments d
            JOIN course_departments cd ON d.id = cd.department_id
            LEFT JOIN user_departments ud ON d.id = ud.department_id
            WHERE cd.course_id = ?
            GROUP BY d.id
            ORDER BY d.name ASC
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting course departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get courses for a department
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return array List of courses assigned to department
 */
function get_department_courses($pdo, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*,
                   COUNT(DISTINCT uta.user_id) as assigned_users,
                   COUNT(DISTINCT cd.department_id) as department_count,
                   u.name as creator_name
            FROM training_courses tc
            JOIN course_departments cd ON tc.id = cd.course_id
            LEFT JOIN user_training_assignments uta ON tc.id = uta.course_id
            LEFT JOIN users u ON tc.created_by = u.id
            WHERE cd.department_id = ?
            GROUP BY tc.id
            ORDER BY tc.name ASC
        ");
        $stmt->execute([$department_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting department courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Assign a course to a department
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param int $department_id Department ID
 * @param int $assigned_by User ID of person making assignment
 * @return bool Success status
 */
function assign_course_to_department($pdo, $course_id, $department_id, $assigned_by) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO course_departments (course_id, department_id, assigned_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by)
        ");
        return $stmt->execute([$course_id, $department_id, $assigned_by]);
    } catch (PDOException $e) {
        error_log("Error assigning course to department: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove a course from a department
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
 * Auto-assign course to all department members
 * This is called when assigning a course to a department
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param int $department_id Department ID
 * @param int $assigned_by User ID of person making assignment
 * @return int Number of users assigned the course
 */
function auto_assign_course_to_department_members($pdo, $course_id, $department_id, $assigned_by) {
    try {
        $pdo->beginTransaction();

        // Get all members of the department
        $members = get_department_members($pdo, $department_id);
        $user_ids = array_column($members, 'id');

        if (empty($user_ids)) {
            $pdo->commit();
            return 0;
        }

        // Use existing assign_course_to_users function
        require_once __DIR__ . '/training_helpers.php';
        $assigned_count = assign_course_to_users($pdo, $course_id, $user_ids, $assigned_by);

        $pdo->commit();
        return $assigned_count;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error auto-assigning course to department members: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if user is in a department
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
        return $stmt->fetch()['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking user department membership: " . $e->getMessage());
        return false;
    }
}

?>
