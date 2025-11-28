<?php
/**
 * Training System Helper Functions
 * Created: 2025-11-05
 * Updated: 2025-11-06 22:02:00 UTC - Enhanced assignment debugging and content count
 * Author: Claude Code Assistant
 *
 * This file contains all the core functions for the training system
 * including role checking, progress tracking, and course management.
 */


/**
 * Training System Helper Functions
 * Created: 2025-11-05
 * Updated: 2025-11-06 22:02:00 UTC - Enhanced assignment debugging and content count
 * Author: Claude Code Assistant
 *
 * This file contains all the core functions for the training system
 * including role checking, progress tracking, and course management.
 */

// --- BEGIN REPLACEMENT ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$HERE = __DIR__;
$ROOT = realpath($HERE . '/..');

// Load user helpers robustly (works when included or hit via AJAX)
if (file_exists($HERE . '/user_helpers.php')) {
    require_once $HERE . '/user_helpers.php';
} elseif (file_exists($ROOT . '/includes/user_helpers.php')) {
    require_once $ROOT . '/includes/user_helpers.php';
} elseif (file_exists($ROOT . '/user_helpers.php')) {
    require_once $ROOT . '/user_helpers.php';
} else {
    // If an AJAX call hits this file directly and user_helpers is missing, return JSON error
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'user_helpers.php not found']);
    exit;
}

// Ensure there is a PDO connection when called directly via fetch()
if (!isset($pdo) || !$pdo) {
    if (file_exists($HERE . '/db_connect.php')) {
        require_once $HERE . '/db_connect.php';
    } elseif (file_exists($ROOT . '/includes/db_connect.php')) {
        require_once $ROOT . '/includes/db_connect.php';
    } elseif (file_exists($ROOT . '/db_connect.php')) {
        require_once $ROOT . '/db_connect.php';
    } else {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'db_connect.php not found']);
        exit;
    }
}
// --- END REPLACEMENT ---


// ============================================================
// UNIFIED DEBUG LOGGING
// ============================================================

/**
 * Unified debug logging function that writes to view_debug_log.php
 * @param string $message Debug message to log
 * @param string $level Debug level (INFO, ERROR, DEBUG)
 */
function log_debug($message, $level = 'DEBUG') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$level] $message\n";

    // Also write to standard error log for server admins
    error_log("TRAINING DEBUG: $message");

    // Write to unified debug log file
    file_put_contents(__DIR__ . '/assignment_debug.log', $timestamp . " - " . $log_message, FILE_APPEND | LOCK_EX);
}

// ============================================================
// AUTOMATIC ROLE MANAGEMENT
// ============================================================

/**
 * Automatically manage user roles based on training assignments
 * This function should be called on every page load for authenticated users
 * @param PDO $pdo Database connection
 * @param int $user_id User ID (optional, defaults to current user)
 * @return array Status of role changes
 */
function auto_manage_user_roles($pdo, $user_id = null) {
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    if (!$user_id) {
        return ['status' => 'no_user', 'changes' => []];
    }

    try {
        $user_stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['status' => 'user_not_found', 'changes' => []];
        }

        $changes = [];

        // Never auto-change roles for Admins or Super Admins (but still manage flags)
        $role_lc = strtolower(trim((string)$user['role']));
        $normalized_role = str_replace(' ', '_', $role_lc);
        $is_privileged = ($normalized_role === 'admin' || $normalized_role === 'super_admin');
        if ($is_privileged) {
            log_debug("Auto-manage running for privileged user {$user['id']} (role={$user['role']})", 'INFO');
        }

        // --- PHASE 3: CHECK FOR RETEST ELIGIBILITY ---
        // This runs on every page load to auto-enable retests when period expires
        $retest_result = check_and_enable_retests($pdo, $user_id);
        if ($retest_result['status'] === 'success' && $retest_result['retests_enabled'] > 0) {
            $changes[] = "User {$user['name']} → {$retest_result['retests_enabled']} retest(s) now eligible";
        }

        // Refresh course completion to keep assignment statuses in sync with real progress
        $course_stmt = $pdo->prepare("
            SELECT DISTINCT uta.course_id
            FROM user_training_assignments uta
            JOIN training_courses tc ON uta.course_id = tc.id
            WHERE uta.user_id = ?
              AND tc.is_active = 1
        ");
        $course_stmt->execute([$user_id]);
        $course_ids = array_map('intval', $course_stmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($course_ids as $course_id) {
            update_course_completion_status($pdo, $user_id, $course_id);
        }

        // Count active (incomplete) training assignments after refreshing completion
        $assignment_stmt = $pdo->prepare("
            SELECT COUNT(*) AS active_assignments
            FROM user_training_assignments uta
            JOIN training_courses tc ON uta.course_id = tc.id
            WHERE uta.user_id = ?
            AND tc.is_active = 1
            AND uta.status != 'completed'
        ");
        $assignment_stmt->execute([$user_id]);
        $active_assignments = (int) $assignment_stmt->fetchColumn();

        // Also count retestable quizzes to determine training flag
        $retestable_quizzes = get_retestable_quizzes($pdo, $user_id);
        $has_retests = count($retestable_quizzes) > 0;

        // Determine if the user has finished all assigned content
        $progress = get_overall_training_progress($pdo, $user_id);
        $all_training_complete = ($progress['total_items'] > 0 && $progress['percentage'] === 100);

        $should_be_training = ($active_assignments > 0 && !$all_training_complete) || $has_retests;
        $new_flag_value = $should_be_training ? 1 : 0;

        // Update the flag based on active assignments AND retests
        $flag_stmt = $pdo->prepare("
            UPDATE users
            SET is_in_training = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $flag_stmt->execute([$new_flag_value, $user_id]);

        // Update session cache if current user
        if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
            $_SESSION['user_is_in_training'] = $new_flag_value;
        }

        // Manage role (training <-> user) for non-privileged users
        if (!$is_privileged) {
            $desired_role = $new_flag_value ? 'training' : 'user';
            if ($desired_role !== $normalized_role) {
                $role_update_stmt = $pdo->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
                $role_update_stmt->execute([$desired_role, $user_id]);

                if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                    $_SESSION['user_role'] = $desired_role;
                }

                $changes[] = "User {$user['name']} role → {$desired_role}";
                log_debug("Auto-manage updated role for user {$user_id} to {$desired_role}", 'INFO');
            }
        }

        $flag_name = $new_flag_value ? 'in training' : 'not in training';
        if ($all_training_complete && !$has_retests) {
            $changes[] = "User {$user['name']} → $flag_name (all assigned training completed)";
        } elseif ($active_assignments > 0) {
            $changes[] = "User {$user['name']} → $flag_name ({$active_assignments} active assignment(s))";
        } elseif ($has_retests) {
            $changes[] = "User {$user['name']} → $flag_name (" . count($retestable_quizzes) . " retest(s) available)";
        } elseif ($active_assignments === 0 && !$has_retests) {
            $changes[] = "User {$user['name']} → $flag_name (no active assignments or retests)";
        }

        log_debug("Auto-manage user {$user_id}: is_in_training=$new_flag_value, active_assignments=$active_assignments, has_retests=$has_retests", 'INFO');

        return [
            'status' => 'success',
            'changes' => $changes,
            'user_id' => $user_id,
            'role' => $user['role'],  // Role never changes
            'is_in_training' => $new_flag_value,
            'active_assignments' => $active_assignments,
        ];
    } catch (PDOException $e) {
        log_debug("Error in auto_manage_user_roles: " . $e->getMessage(), 'ERROR');
        return ['status' => 'error', 'message' => $e->getMessage(), 'changes' => []];
    }
}
// --- END REPLACEMENT ---


// Call automatic role management for authenticated users
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] && isset($_SESSION['user_id']) && isset($pdo) && $pdo) {
    $role_status = auto_manage_user_roles($pdo, $_SESSION['user_id']);
    if (!empty($role_status['changes'])) {
        log_debug("Automatic role management: " . implode('; ', $role_status['changes']), 'INFO');
    }
}

// Handle AJAX requests for live progress updates
if (isset($_GET['action']) && $_GET['action'] === 'get_training_progress') {
    header('Content-Type: application/json');

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];

    if (function_exists('log_debug')) {
        log_debug("AJAX progress request - User ID: $user_id");
    }

    if (should_show_training_progress($pdo, $user_id)) {
        $progress = get_overall_training_progress($pdo, $user_id);

        if (function_exists('log_debug')) {
            log_debug("Progress data: " . json_encode($progress));
        }

        echo json_encode([
            'success' => true,
            'progress' => $progress,
            'percentage' => $progress['percentage'],
            'completed_items' => $progress['completed_items'],
            'total_items' => $progress['total_items']
        ]);
    } else {
        if (function_exists('log_debug')) {
            log_debug("should_show_training_progress returned false for user $user_id");
        }
        echo json_encode([
            'success' => false,
            'message' => 'No training progress available'
        ]);
    }
    exit;
}

// Handle AJAX requests for updating time spent
if (isset($_GET['action']) && $_GET['action'] === 'update_time_spent') {
    header('Content-Type: application/json');

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];
    $content_id = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
    $time_spent = isset($_GET['time_spent']) ? intval($_GET['time_spent']) : 0;

    if ($user_id > 0 && $content_id > 0 && $time_spent > 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE training_progress
                SET time_spent_minutes = time_spent_minutes + ?, updated_at = NOW()
                WHERE user_id = ? AND content_type = 'post' AND content_id = ?
            ");
            $stmt->execute([$time_spent, $user_id, $content_id]);

            echo json_encode([
                'success' => true,
                'time_added' => $time_spent
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid parameters'
        ]);
    }
    exit;
}

// Handle AJAX requests for marking content as complete
if (isset($_GET['action']) && $_GET['action'] === 'mark_complete') {
    header('Content-Type: application/json');

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];
    $content_id = isset($_GET['content_id']) ? intval($_GET['content_id']) : 0;
    $content_type = isset($_GET['content_type']) ? $_GET['content_type'] : 'post';

    if ($user_id > 0 && $content_id > 0) {
        try {
            $success = mark_content_complete($pdo, $user_id, $content_type, $content_id, 0);

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Content marked as complete' : 'Failed to mark complete'
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid parameters'
        ]);
    }
    exit;
}

// ============================================================
// ROLE CHECKING FUNCTIONS
// ============================================================

/**
 * Check if current user is in training (based on is_in_training flag)
 * @return bool True if user is in training
 */
function is_training_user() {
    return isset($_SESSION['user_is_in_training']) && $_SESSION['user_is_in_training'] == 1;
}

/**
 * Check if current user can create posts
 * @return bool True if user can create posts
 */
function can_create_posts() {
    return is_admin() || is_super_admin();
}

/**
 * Check if current user can create categories
 * @return bool True if user can create categories
 */
function can_create_categories() {
    return is_admin() || is_super_admin();
}

/**
 * Check if current user can create subcategories
 * @return bool True if user can create subcategories
 */
function can_create_subcategories() {
    return is_admin() || is_super_admin();
}

/**
 * Check if current user can access specific content
 * @param PDO $pdo Database connection
 * @param int $content_id Content ID
 * @param string $content_type Content type (post, category, subcategory)
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool True if user can access content
 */
function can_access_content($pdo, $content_id, $content_type, $user_id = null) {
    $user_id = $user_id ?? $_SESSION['user_id'];

    // Super admins can access everything
    if (is_super_admin()) {
        return true;
    }

    // --- PHASE 5: CLEANUP ---
    // Use only the new flag-based system
    if (is_in_training()) {
        return is_assigned_training_content($pdo, $user_id, $content_id, $content_type);
    }

    // Regular users and admins have normal access
    return true;
}

// ============================================================
// TRAINING COURSE MANAGEMENT FUNCTIONS
// ============================================================

/**
 * Create a new training course
 * @param PDO $pdo Database connection
 * @param string $name Course name
 * @param string $description Course description
 * @param string $department Department (optional)
 * @param int $created_by User ID of creator
 * @param float $estimated_hours Estimated hours to complete the course
 * @return int Course ID or false on failure
 */
function create_training_course($pdo, $name, $description, $department, $created_by, $estimated_hours = 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO training_courses (name, description, department, estimated_hours, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $department, $estimated_hours, $created_by]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating training course: " . $e->getMessage());
        return false;
    }
}

/**
 * Add content to a training course
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param string $content_type Content type (category, subcategory, post)
 * @param int $content_id Content ID
 * @param int $time_required Time required in minutes
 * @param string $admin_notes Admin notes (optional)
 * @param int $training_order Order in training sequence
 * @return bool Success status
 */
function add_content_to_course($pdo, $course_id, $content_type, $content_id, $time_required = 0, $admin_notes = '', $training_order = 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO training_course_content
            (course_id, content_type, content_id, time_required_minutes, admin_notes, training_order)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            time_required_minutes = VALUES(time_required_minutes),
            admin_notes = VALUES(admin_notes),
            training_order = VALUES(training_order)
        ");
        return $stmt->execute([$course_id, $content_type, $content_id, $time_required, $admin_notes, $training_order]);
    } catch (PDOException $e) {
        error_log("Error adding content to course: " . $e->getMessage());
        return false;
    }
}

/**
 * Assign course to users
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @param array $user_ids Array of user IDs
 * @param int $assigned_by User ID making the assignment
 * @param int $department_id Department ID (optional, null for direct assignments)
 * @return bool Success status
 */
function assign_course_to_users($pdo, $course_id, $user_ids, $assigned_by, $department_id = null) {
    $started_transaction = false;

    try {
        error_log("DEBUG: assign_course_to_users called with course_id=$course_id, user_ids=" . json_encode($user_ids) . ", assigned_by=$assigned_by, department_id=" . ($department_id ?? 'null'));

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $started_transaction = true;
        }

        $assigned_count = 0;

        $stmt = $pdo->prepare("
            INSERT INTO user_training_assignments (user_id, course_id, assigned_by, assigned_date, assignment_source, department_id)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
            ON DUPLICATE KEY UPDATE
            assigned_date = CURRENT_TIMESTAMP,
            assigned_by = VALUES(assigned_by),
            assignment_source = VALUES(assignment_source),
            department_id = VALUES(department_id),
            status = CASE WHEN status = 'completed' THEN 'not_started' ELSE status END,
            completion_date = CASE WHEN status = 'completed' THEN NULL ELSE completion_date END,
            retest_exempt = 0
        ");

        // Get user info for role conversion
        $user_info_stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");

        // Determine assignment source and department ID
        $assignment_source = $department_id ? 'department' : 'direct';
        $dept_id = $department_id ?: null;

        foreach ($user_ids as $user_id) {
            error_log("DEBUG: Processing user_id=$user_id for course_id=$course_id");

            try {
                $stmt->execute([$user_id, $course_id, $assigned_by, $assignment_source, $dept_id]);
                $rows_affected = $stmt->rowCount();
                error_log("DEBUG: INSERT/UPDATE rows affected for user_id=$user_id: $rows_affected");
            } catch (PDOException $e) {
                error_log("DEBUG: Database error for user_id=$user_id: " . $e->getMessage());
                throw $e; // Re-throw to be caught by outer try-catch
            }

            if ($rows_affected > 0) {
                $assigned_count++;
            }

            // Set is_in_training flag for any user getting training assignments
            $user_info_stmt->execute([$user_id]);
            $user_data = $user_info_stmt->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: User data for user_id=$user_id: " . json_encode($user_data));

            // Always set is_in_training flag for any user getting training assignments
            if ($user_data) {
                $normalized_role = strtolower(str_replace(' ', '_', (string)$user_data['role']));
                $flag_stmt = $pdo->prepare("
                    UPDATE users
                    SET is_in_training = 1
                    WHERE id = ?
                ");
                $flag_stmt->execute([$user_id]);
                error_log("DEBUG: Set is_in_training flag for user_id=$user_id");

                // Move user into training role unless privileged
                if ($normalized_role !== 'admin' && $normalized_role !== 'super_admin' && $normalized_role !== 'training') {
                    $role_stmt = $pdo->prepare("UPDATE users SET role = 'training', updated_at = NOW() WHERE id = ?");
                    $role_stmt->execute([$user_id]);
                    log_debug("Updated user {$user_id} role to training after assignment (course {$course_id})", 'INFO');

                    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                        $_SESSION['user_role'] = 'training';
                    }
                }

                // Update session if current user
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                    $_SESSION['user_is_in_training'] = 1;
                }
            }
        }

        if ($started_transaction && $pdo->inTransaction()) {
            $pdo->commit();
        }

        error_log("DEBUG: assign_course_to_users returning assigned_count=$assigned_count");
        return $assigned_count;
    } catch (PDOException $e) {
        if ($started_transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("DEBUG: assign_course_to_users ERROR: " . $e->getMessage());
            return 0;
        }

        error_log("DEBUG: assign_course_to_users ERROR (propagating): " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get all training courses
 * @param PDO $pdo Database connection
 * @param bool $active_only Only show active courses
 * @return array List of courses
 */
function get_training_courses($pdo, $active_only = true) {
    try {
        $sql = "
            SELECT tc.*, u.name as creator_name,
                   COUNT(DISTINCT uta.user_id) as assigned_users,
                   COUNT(DISTINCT CASE WHEN uta.status = 'completed' THEN uta.user_id END) as completed_users,
                   COUNT(DISTINCT CASE WHEN tcc.content_type = 'post' THEN tcc.id END) as content_count
            FROM training_courses tc
            LEFT JOIN users u ON tc.created_by = u.id
            LEFT JOIN user_training_assignments uta ON tc.id = uta.course_id
            LEFT JOIN training_course_content tcc ON tc.id = tcc.course_id
        ";

        if ($active_only) {
            $sql .= " WHERE tc.is_active = TRUE";
        }

        $sql .= " GROUP BY tc.id ORDER BY tc.name";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting training courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get content assigned to a course
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID
 * @return array List of course content
 */
function get_course_content($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT tcc.*,
                   CASE tcc.content_type
                       WHEN 'category' THEN c.name
                       WHEN 'subcategory' THEN sc.name
                       WHEN 'post' THEN p.title
                   END as content_name
            FROM training_course_content tcc
            LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
            LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
            LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
            WHERE tcc.course_id = ?
            ORDER BY tcc.training_order, tcc.content_type, tcc.content_id
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting course content: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// USER TRAINING ASSIGNMENT FUNCTIONS
// ============================================================

/**
 * Get courses assigned to a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array List of assigned courses
 */
function get_user_assigned_courses($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*, uta.status as assignment_status, uta.assigned_date, uta.completion_date,
                   uta.assignment_source, d.name as department_name, uta.due_date,
                   0 as progress_percentage
            FROM training_courses tc
            JOIN user_training_assignments uta ON tc.id = uta.course_id
            LEFT JOIN departments d ON uta.department_id = d.id
            WHERE uta.user_id = ? AND tc.is_active = TRUE
            ORDER BY uta.assigned_date, tc.name
        ");
        $stmt->execute([$user_id]);

        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate progress for each course
        foreach ($courses as &$course) {
            $course['progress_percentage'] = calculate_course_progress($pdo, $user_id, $course['id']);
        }

        return $courses;
    } catch (PDOException $e) {
        error_log("Error getting user assigned courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Remove training flag when user has no remaining training assignments
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool True if flag was cleared, false if still has assignments
 */
function remove_training_if_none_remaining($pdo, $user_id) {
    if (!$pdo) {
        error_log("remove_training_if_none_remaining: No database connection available");
        return false;
    }

    try {
        // Check if user has any training assignments
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as assignment_count
            FROM user_training_assignments
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $assignment_count = (int)$stmt->fetch()['assignment_count'];

        if ($assignment_count === 0) {
            // Clear the is_in_training flag
            $update_stmt = $pdo->prepare("
                UPDATE users
                SET is_in_training = 0, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $update_stmt->execute([$user_id]);

            // Move non-privileged users back to standard role if they were in training
            $role_stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $role_stmt->execute([$user_id]);
            $current_role = strtolower(str_replace(' ', '_', (string)$role_stmt->fetchColumn()));

            if ($current_role === 'training') {
                $downgrade_stmt = $pdo->prepare("UPDATE users SET role = 'user', updated_at = NOW() WHERE id = ?");
                $downgrade_stmt->execute([$user_id]);

                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                    $_SESSION['user_role'] = 'user';
                }

                log_debug("Downgraded user {$user_id} to role user (no remaining assignments)", 'INFO');
            }

            // Update session if current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                $_SESSION['user_is_in_training'] = 0;
            }

            log_debug("Cleared is_in_training flag for user $user_id (no remaining assignments)", 'INFO');
            return $result;
        } else {
            log_debug("User $user_id still has $assignment_count training assignments, keeping flag", 'DEBUG');
            return false;
        }
    } catch (PDOException $e) {
        error_log("Error in remove_training_if_none_remaining for user $user_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if content is assigned to user's training
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $content_id Content ID
 * @param string $content_type Content type
 * @return bool True if content is in user's training
 */
function is_assigned_training_content($pdo, $user_id, $content_id, $content_type) {
    try {
        $normalized_type = strtolower(trim((string) $content_type));
        if ($normalized_type === '') {
            $normalized_type = 'post';
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT tcc.id) as count
            FROM training_course_content tcc
            JOIN user_training_assignments uta ON tcc.course_id = uta.course_id
            WHERE uta.user_id = ?
              AND uta.status != 'completed'
              AND tcc.content_id = ?
              AND (
                    LOWER(COALESCE(tcc.content_type, '')) = ?
                 OR (LOWER(COALESCE(tcc.content_type, '')) = '' AND ? = 'post')
              )
        ");
        $stmt->execute([$user_id, $content_id, $normalized_type, $normalized_type]);
        return $stmt->fetch()['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking training content assignment: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// PROGRESS TRACKING FUNCTIONS
// ============================================================

/**
 * Calculate overall training progress for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Progress data
 */
function get_overall_training_progress($pdo, $user_id) {
    try {
        // Count only POSTS for training progress (not categories/subcategories)
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT tcc.id) as total_items,
                COUNT(DISTINCT CASE
                    WHEN tp.status = 'completed'
                      OR tp.quiz_completed = 1
                      OR (uqa.passed = 1 AND COALESCE(qrt.retest_enabled, 0) = 0) THEN tcc.id END) as completed_items,
                COUNT(DISTINCT CASE WHEN tp.status = 'in_progress' THEN tcc.id END) as in_progress_items,
                COUNT(DISTINCT uta.course_id) as total_courses,
                COUNT(DISTINCT CASE WHEN uta.status = 'completed' THEN uta.course_id END) as completed_courses
            FROM user_training_assignments uta
            JOIN training_courses tc ON uta.course_id = tc.id
            JOIN training_course_content tcc ON uta.course_id = tcc.course_id
            LEFT JOIN training_progress tp ON tcc.content_id = tp.content_id
                AND tp.user_id = ?
                AND (tcc.content_type = tp.content_type OR tp.content_type = '' OR tp.content_type IS NULL OR tp.content_type ='' OR tp.content_type IS NULL)
            LEFT JOIN training_quizzes tq
                   ON tq.content_id = tcc.content_id
                  AND LOWER(COALESCE(tq.content_type,'')) IN ('post','')
            LEFT JOIN quiz_retest_tracking qrt
                   ON qrt.quiz_id = tq.id
                  AND qrt.user_id = ?
            LEFT JOIN (
                SELECT
                    quiz_id,
                    MAX(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) AS passed
                FROM user_quiz_attempts
                WHERE user_id = ?
                GROUP BY quiz_id
            ) uqa
                   ON uqa.quiz_id = tq.id
            WHERE uta.user_id = ?
            AND tc.is_active = 1
            AND tcc.content_type = 'post'  -- Only count posts
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_items = (int)$data['total_items'];
        $completed_items = (int)$data['completed_items'];
        $in_progress_items = (int)$data['in_progress_items'];
        $total_courses = (int)$data['total_courses'];

        // Recompute completed courses based on actual content progress to avoid stale assignment statuses
        $courses_stmt = $pdo->prepare("
            SELECT tc.id
            FROM training_courses tc
            JOIN user_training_assignments uta ON tc.id = uta.course_id
            WHERE uta.user_id = ?
              AND tc.is_active = 1
        ");
        $courses_stmt->execute([$user_id]);
        $course_ids = array_map('intval', $courses_stmt->fetchAll(PDO::FETCH_COLUMN));
        $total_courses = count($course_ids);

        $completed_courses = 0;
        foreach ($course_ids as $course_id) {
            $course_progress = calculate_course_progress($pdo, $user_id, $course_id);
            if ($course_progress === 100) {
                $completed_courses++;
            }
        }

        $percentage = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;

        // Debug logging
        log_debug("Training progress for user $user_id - Total: $total_items, Completed: $completed_items, Percentage: $percentage");

        return [
            'total_items' => $total_items,
            'completed_items' => $completed_items,
            'in_progress_items' => $in_progress_items,
            'total_courses' => $total_courses,
            'completed_courses' => $completed_courses,
            'percentage' => $percentage
        ];
    } catch (PDOException $e) {
        error_log("Error calculating overall progress: " . $e->getMessage());
        return [
            'total_items' => 0,
            'completed_items' => 0,
            'in_progress_items' => 0,
            'total_courses' => 0,
            'completed_courses' => 0,
            'percentage' => 0
        ];
    }
}

/**
 * Calculate progress for a specific course
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $course_id Course ID
 * @return array Progress data
 */
function calculate_course_progress($pdo, $user_id, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT tcc.id) as total_items,
                COUNT(DISTINCT CASE
                    WHEN tp.status = 'completed'
                      OR tp.quiz_completed = 1
                      OR (uqa.passed = 1 AND COALESCE(qrt.retest_enabled, 0) = 0) THEN tcc.id
                END) as completed_items,
                COUNT(DISTINCT CASE WHEN tp.status = 'in_progress' THEN tcc.id END) as in_progress_items
            FROM training_course_content tcc
            JOIN user_training_assignments uta ON uta.course_id = tcc.course_id AND uta.user_id = ?
            LEFT JOIN training_progress tp ON tcc.content_id = tp.content_id
                AND tp.course_id = tcc.course_id
                AND tp.user_id = uta.user_id
                AND (tcc.content_type = tp.content_type OR tp.content_type = '' OR tp.content_type IS NULL)
            LEFT JOIN training_quizzes tq
                   ON tq.content_id = tcc.content_id
                  AND LOWER(COALESCE(tq.content_type,'')) IN ('post','')
            LEFT JOIN quiz_retest_tracking qrt
                   ON qrt.quiz_id = tq.id
                  AND qrt.user_id = ?
            LEFT JOIN (
                SELECT
                    quiz_id,
                    MAX(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) AS passed
                FROM user_quiz_attempts
                WHERE user_id = ?
                GROUP BY quiz_id
            ) uqa
                   ON uqa.quiz_id = tq.id
            WHERE tcc.course_id = ?
              AND tcc.content_type = 'post'
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $course_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_items = (int)$data['total_items'];
        $completed_items = (int)$data['completed_items'];

        return $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
    } catch (PDOException $e) {
        error_log("Error calculating course progress: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark content as completed for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $content_type Content type
 * @param int $content_id Content ID
 * @param int $time_spent Time spent in minutes
 * @return bool Success status
 */
function mark_content_complete($pdo, $user_id, $content_type, $content_id, $time_spent = 0) {
    try {
        $pdo->beginTransaction();

        // Get course ID for this content
        $course_stmt = $pdo->prepare("
            SELECT course_id FROM training_course_content
            WHERE content_type = ? AND content_id = ?
            LIMIT 1
        ");
        $course_stmt->execute([$content_type, $content_id]);
        $course_data = $course_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course_data) {
            $pdo->rollBack();
            return false;
        }

        $course_id = $course_data['course_id'];

        // Update or insert progress record
        $progress_stmt = $pdo->prepare("
            INSERT INTO training_progress
            (user_id, course_id, content_type, content_id, status, completion_date, time_spent_minutes, time_started)
            VALUES (?, ?, ?, ?, 'completed', CURRENT_TIMESTAMP, ?,
                COALESCE((SELECT time_started FROM training_progress
                         WHERE user_id = ? AND content_type = ? AND content_id = ?), CURRENT_TIMESTAMP))
            ON DUPLICATE KEY UPDATE
            status = 'completed',
            completion_date = CURRENT_TIMESTAMP,
            time_spent_minutes = time_spent_minutes + VALUES(time_spent_minutes),
            updated_at = CURRENT_TIMESTAMP
        ");
        $progress_stmt->execute([$user_id, $course_id, $content_type, $content_id, $time_spent, $user_id, $content_type, $content_id]);

        // Save to permanent history
        save_to_training_history($pdo, $user_id, $course_id, $content_type, $content_id, $time_spent);

        // Check if course is now complete
        update_course_completion_status($pdo, $user_id, $course_id);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error marking content complete: " . $e->getMessage());
        return false;
    }
}

/**
 * Save completion to permanent training history
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $course_id Course ID
 * @param string $content_type Content type
 * @param int $content_id Content ID
 * @param int $time_spent Time spent in minutes
 * @return bool Success status
 */
function save_to_training_history($pdo, $user_id, $course_id, $content_type, $content_id, $time_spent) {
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO training_history
            (user_id, course_id, content_type, content_id, completion_date, time_spent_minutes, original_assignment_date)
            SELECT ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, assigned_date
            FROM user_training_assignments
            WHERE user_id = ? AND course_id = ?
        ");
        return $stmt->execute([$user_id, $course_id, $content_type, $content_id, $time_spent, $user_id, $course_id]);
    } catch (PDOException $e) {
        error_log("Error saving to training history: " . $e->getMessage());
        return false;
    }
}

/**
 * Update course completion status
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $course_id Course ID
 * @return bool Success status
 */
function update_course_completion_status($pdo, $user_id, $course_id) {
    try {
        // Check if all content is completed

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT tcc.id) AS total_items,
           COUNT(DISTINCT CASE
                WHEN tp.status = 'completed'
                     OR tp.quiz_completed = 1
                     OR uqa.passed = 1 THEN tcc.id
           END) AS completed_items
    FROM training_course_content tcc
    LEFT JOIN training_progress tp
      ON tp.user_id     = ?
     AND tcc.content_id = tp.content_id
     AND (
            tcc.content_type = tp.content_type
         OR tp.content_type = ''
         OR tp.content_type IS NULL
         )
    LEFT JOIN training_quizzes tq
           ON tq.content_id = tcc.content_id
          AND LOWER(COALESCE(tq.content_type,'')) IN ('post','')
    LEFT JOIN (
        SELECT quiz_id,
               MAX(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) AS passed
        FROM user_quiz_attempts
        WHERE user_id = ?
        GROUP BY quiz_id
    ) uqa
           ON uqa.quiz_id = tq.id
    WHERE tcc.course_id = ?
      AND tcc.content_type = 'post'
");

        $stmt->execute([$user_id, $user_id, $course_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_items = (int)$data['total_items'];
        $completed_items = (int)$data['completed_items'];

        if ($total_items > 0 && $completed_items === $total_items) {
            // Mark course as completed
            $update_stmt = $pdo->prepare("
                UPDATE user_training_assignments
                SET status = 'completed', completion_date = CURRENT_TIMESTAMP
                WHERE user_id = ? AND course_id = ?
            ");
            $update_stmt->execute([$user_id, $course_id]);

            if (function_exists('log_debug')) {
                log_debug("Marked course {$course_id} as completed for user {$user_id}", 'INFO');
            }

            // Update history with course completion date
            $history_stmt = $pdo->prepare("
                UPDATE training_history
                SET course_completed_date = CURRENT_TIMESTAMP
                WHERE user_id = ? AND course_id = ? AND course_completed_date IS NULL
            ");
            $history_stmt->execute([$user_id, $course_id]);

            // Check if user has completed all assigned courses
            promote_user_if_training_complete($pdo, $user_id);
        }

        return true;
    } catch (PDOException $e) {
        error_log("Error updating course completion: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has completed training and promote to user role
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool True if user was promoted
 */
// --- BEGIN REPLACEMENT (promote_user_if_training_complete: flag-based system) ---
function promote_user_if_training_complete($pdo, $user_id) {
    try {
        // Only consider users who are currently in training (check flag)
        $flag_stmt = $pdo->prepare("SELECT is_in_training FROM users WHERE id = ?");
        $flag_stmt->execute([$user_id]);
        $is_in_training = $flag_stmt->fetchColumn();
        if ($is_in_training != 1) {
            return false;
        }

        // All assigned courses completed?
        $stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_courses,
        COUNT(CASE WHEN uta.status != 'completed' THEN 1 END) AS incomplete_courses
    FROM user_training_assignments uta
    JOIN training_courses tc
      ON tc.id = uta.course_id
    WHERE uta.user_id = ?
      AND tc.is_active = 1
");
$stmt->execute([$user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$data['total_courses'] > 0 && (int)$data['incomplete_courses'] === 0) {
            // --- BEGIN REPLACEMENT (flag-only system) ---
$update_stmt = $pdo->prepare("
    UPDATE users
       SET is_in_training = 0,
           original_training_completion = CURRENT_TIMESTAMP,
           updated_at = NOW()
     WHERE id = ? AND is_in_training = 1
");
$ok = $update_stmt->execute([$user_id]);

if ($ok && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
    $_SESSION['user_is_in_training'] = 0;
}

return $ok;
// --- END REPLACEMENT ---

        }
        return false;
    } catch (PDOException $e) {
        error_log("Error promoting user: " . $e->getMessage());
        return false;
    }
}
// --- END REPLACEMENT ---


/**
 * Get next required training item for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array|null Next item data or null
 */
function get_next_training_item($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT tcc.*,
                   CASE tcc.content_type
                       WHEN 'category' THEN c.name
                       WHEN 'subcategory' THEN sc.name
                       WHEN 'post' THEN p.title
                   END as content_name,
                   CASE tcc.content_type
                       WHEN 'category' THEN CONCAT('category.php?id=', c.id)
                       WHEN 'subcategory' THEN CONCAT('/categories/subcategory.php?id=', sc.id)
                       WHEN 'post' THEN CONCAT('/posts/post.php?id=', p.id)
                   END as content_url
            FROM training_course_content tcc
            JOIN user_training_assignments uta ON tcc.course_id = uta.course_id
            LEFT JOIN categories c ON tcc.content_type = 'category' AND tcc.content_id = c.id
            LEFT JOIN subcategories sc ON tcc.content_type = 'subcategory' AND tcc.content_id = sc.id
            LEFT JOIN posts p ON tcc.content_type = 'post' AND tcc.content_id = p.id
            LEFT JOIN training_progress tp ON tcc.content_type = tp.content_type
                AND tcc.content_id = tp.content_id
                AND tp.user_id = ?
            WHERE uta.user_id = ?
            AND uta.status != 'completed'
            AND (tp.status IS NULL OR tp.status != 'completed')
            ORDER BY tcc.training_order, tcc.content_type, tcc.content_id
            LIMIT 1
        ");
        $stmt->execute([$user_id, $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Error getting next training item: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// TRAINING HISTORY FUNCTIONS
// ============================================================

/**
 * Check if content is already completed in training history
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $content_type Content type
 * @param int $content_id Content ID
 * @return bool True if already completed
 */
function is_already_completed_in_history($pdo, $user_id, $content_type, $content_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM training_history
            WHERE user_id = ? AND content_type = ? AND content_id = ?
        ");
        $stmt->execute([$user_id, $content_type, $content_id]);
        return $stmt->fetch()['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking training history: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's training history
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Training history
 */
function get_training_history($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT th.*, tc.name as course_name,
                   CASE th.content_type
                       WHEN 'category' THEN c.name
                       WHEN 'subcategory' THEN sc.name
                       WHEN 'post' THEN p.title
                   END as content_name
            FROM training_history th
            JOIN training_courses tc ON th.course_id = tc.id
            LEFT JOIN categories c ON th.content_type = 'category' AND th.content_id = c.id
            LEFT JOIN subcategories sc ON th.content_type = 'subcategory' AND th.content_id = sc.id
            LEFT JOIN posts p ON th.content_type = 'post' AND th.content_id = p.id
            WHERE th.user_id = ?
            ORDER BY th.completion_date DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting training history: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// TRAINING REVERSION FUNCTIONS
// ============================================================

/**
 * Revert user to training role when new content is added
 * @param PDO $pdo Database connection
 * @param int $course_id Course ID with new content
 * @return int Number of users reverted
 */
function handle_new_training_content($pdo, $course_id) {
    try {
        // Get users who completed this course
        $stmt = $pdo->prepare("
            SELECT DISTINCT uta.user_id, u.name, u.role
            FROM user_training_assignments uta
            JOIN users u ON uta.user_id = u.id
            WHERE uta.course_id = ? AND uta.status = 'completed' AND u.role != 'training'
        ");
        $stmt->execute([$course_id]);
        $completed_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reverted_count = 0;

        foreach ($completed_users as $user) {
            if (revert_user_to_training($pdo, $user['user_id'], $course_id, "New content added to course ID: $course_id")) {
                $reverted_count++;
            }
        }

        return $reverted_count;
    } catch (PDOException $e) {
        error_log("Error handling new training content: " . $e->getMessage());
        return 0;
    }
}

/**
 * Revert a specific user to training role
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $course_id Course ID with new content
 * @param string $reason Reason for reversion
 * @return bool Success status
 */
// --- BEGIN REPLACEMENT (revert_user_to_training: flag-only system) ---
function revert_user_to_training($pdo, $user_id, $course_id, $reason) {
    try {
        $pdo->beginTransaction();

        // Only set the is_in_training flag (preserve existing role)
        $user_stmt = $pdo->prepare("
            UPDATE users
               SET training_revert_reason = ?,
                   is_in_training = 1,
                   original_training_completion = NOW()
             WHERE id = ?
        ");
        $user_stmt->execute([$reason, $user_id]);

        // Update session if current user
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
            $_SESSION['user_is_in_training'] = 1;
        }

        // Reset assignment + progress for that course (safe for any role)
        $assignment_stmt = $pdo->prepare("
            UPDATE user_training_assignments
               SET status = 'in_progress',
                   completion_date = NULL
             WHERE user_id = ? AND course_id = ?
        ");
        $assignment_stmt->execute([$user_id, $course_id]);

        $progress_stmt = $pdo->prepare("
            DELETE FROM training_progress
             WHERE user_id = ?
               AND course_id = ?
               AND status = 'completed'
        ");
        $progress_stmt->execute([$user_id, $course_id]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error reverting user to training: " . $e->getMessage());
        return false;
    }
}
// --- END REPLACEMENT ---


// ============================================================
// TRAINING VISIBILITY AND PROGRESS FUNCTIONS
// ============================================================

/**
 * Check if user should see training progress bar
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool True if user should see progress bar
 */
function should_show_training_progress($pdo, $user_id = null) {
    $user_id = $user_id ?? $_SESSION['user_id'];

    if (function_exists('log_debug')) {
        log_debug("should_show_training_progress called - User ID: $user_id, In training: " . (is_in_training() ? 'yes' : 'no'));
    }

    // --- PHASE 5: CLEANUP ---
    // Use only the new flag-based system
    if (is_in_training()) {
        if (function_exists('log_debug')) {
            log_debug("User is in training - showing progress bar");
        }
        return true;
    }

    // Show for admins/super admins if they have active training assignments
    if (is_admin() || is_super_admin()) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM user_training_assignments uta
                WHERE uta.user_id = ?
                AND uta.status IN ('not_started', 'in_progress')
                AND EXISTS (
                    SELECT 1 FROM training_courses tc
                    WHERE tc.id = uta.course_id AND tc.is_active = 1
                )
            ");
            $stmt->execute([$user_id]);
            $active_assignments = $stmt->fetchColumn();

            if (function_exists('log_debug')) {
                log_debug("Admin user has $active_assignments active assignments");
            }

            return $active_assignments > 0;
        } catch (PDOException $e) {
            if (function_exists('log_debug')) {
                log_debug("Database error in should_show_training_progress: " . $e->getMessage());
            }
            return false;
        }
    }

    if (function_exists('log_debug')) {
        log_debug("User is not training user or admin - not showing progress bar");
    }

    return false;
}

/**
 * Get user's assigned content for visibility filtering
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Array of assigned content IDs by type
 */
function get_user_assigned_content_ids($pdo, $user_id) {
    $assigned_content = [
        'category' => [],
        'subcategory' => [],
        'post' => []
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT tcc.content_type, tcc.content_id
            FROM user_training_assignments uta
            JOIN training_course_content tcc ON uta.course_id = tcc.course_id
            WHERE uta.user_id = ?
            AND uta.status IN ('not_started', 'in_progress', 'completed')
            AND EXISTS (
                SELECT 1 FROM training_courses tc
                WHERE tc.id = uta.course_id AND tc.is_active = 1
            )
        ");
        $stmt->execute([$user_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $assigned_content[$row['content_type']][] = $row['content_id'];
        }

        // Remove duplicates
        foreach ($assigned_content as $type => &$ids) {
            $ids = array_unique($ids);
        }

    } catch (PDOException $e) {
        error_log("Error getting assigned content IDs: " . $e->getMessage());
    }

    return $assigned_content;
}

/**
 * Filter content based on training assignments
 * @param PDO $pdo Database connection
 * @param array $content_items Array of content items
 * @param string $content_type Type of content (category, subcategory, post)
 * @param int $user_id User ID (optional, defaults to current user)
 * @return array Filtered content items
 */
function filter_content_for_training_user($pdo, $content_items, $content_type, $user_id = null) {
    $user_id = $user_id ?? $_SESSION['user_id'];

    // --- PHASE 5: CLEANUP ---
    // Use only the new flag-based system
    if (!is_in_training()) {
        return $content_items;
    }

    // Get assigned content IDs
    $assigned_ids = get_user_assigned_content_ids($pdo, $user_id);
    $type_assigned_ids = $assigned_ids[$content_type] ?? [];

    // Filter content to only show assigned items
    return array_filter($content_items, function($item) use ($type_assigned_ids) {
        return in_array($item['id'], $type_assigned_ids);
    });
}

/**
 * Check if user has completed all training and should be promoted
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return bool True if user completed all training
 */
function has_completed_all_training($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_courses,
                   COUNT(CASE WHEN status != 'completed' THEN 1 END) as incomplete_courses
            FROM user_training_assignments
            WHERE user_id = ?
            AND EXISTS (
                SELECT 1 FROM training_courses tc
                WHERE tc.id = course_id AND tc.is_active = 1
            )
        ");
        $stmt->execute([$user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)$data['total_courses'] > 0 && (int)$data['incomplete_courses'] === 0;
    } catch (PDOException $e) {
        error_log("Error checking training completion: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// PHASE 3: RETEST ELIGIBILITY CHECK
// ============================================================

/**
 * Check if a quiz is eligible for retest based on retest period
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $quiz_id Quiz ID
 * @return array Status with retest_eligible flag and days until next retest
 */
function check_quiz_retest_eligibility($pdo, $user_id, $quiz_id) {
    try {
        // Get quiz retest period
        $quiz_stmt = $pdo->prepare("SELECT retest_period_months FROM training_quizzes WHERE id = ?");
        $quiz_stmt->execute([$quiz_id]);
        $quiz = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) {
            return ['status' => 'error', 'message' => 'Quiz not found'];
        }

        $retest_months = (int)($quiz['retest_period_months'] ?? 0);

        // If no retest period, user can always retake
        if ($retest_months === 0) {
            return [
                'status' => 'success',
                'retest_eligible' => false,
                'reason' => 'no_retest_period',
                'next_retest_date' => null
            ];
        }

        // Get last completed attempt (allow legacy "completed" status too)
        $attempt_stmt = $pdo->prepare("
            SELECT id, completed_at, status
              FROM user_quiz_attempts
             WHERE user_id = ?
               AND quiz_id = ?
               AND status IN ('passed', 'completed')
          ORDER BY completed_at DESC
             LIMIT 1
        ");
        $attempt_stmt->execute([$user_id, $quiz_id]);
        $last_attempt = $attempt_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$last_attempt) {
            return [
                'status' => 'success',
                'retest_eligible' => false,
                'reason' => 'no_previous_attempt',
                'next_retest_date' => null
            ];
        }

        // Calculate retest date
        $completed_date = new DateTime($last_attempt['completed_at']);
        $completed_date->modify("+{$retest_months} months");
        $retest_date = $completed_date->format('Y-m-d H:i:s');
        $retest_timestamp = $completed_date->getTimestamp();

        $now_timestamp = time();
        $retest_eligible = $now_timestamp >= $retest_timestamp;

        $days_until = ceil(($retest_timestamp - $now_timestamp) / 86400);

        return [
            'status' => 'success',
            'retest_eligible' => $retest_eligible,
            'reason' => $retest_eligible ? 'retest_available' : 'retest_not_yet_available',
            'next_retest_date' => $retest_date,
            'days_until_retest' => max(0, $days_until),
            'last_attempt_date' => $last_attempt['completed_at'],
            'retest_period_months' => $retest_months
        ];
    } catch (PDOException $e) {
        error_log("Error checking quiz retest eligibility: " . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Upsert retest tracking for a completed quiz attempt
 *
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $quiz_id Quiz ID
 * @param string|null $completed_at Completion datetime (falls back to latest passed/completed attempt)
 * @return array Status payload
 */
function upsert_quiz_retest_tracking(PDO $pdo, int $user_id, int $quiz_id, ?string $completed_at = null): array
{
    try {
        $quiz_stmt = $pdo->prepare("SELECT retest_period_months FROM training_quizzes WHERE id = ?");
        $quiz_stmt->execute([$quiz_id]);
        $quiz = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) {
            return ['status' => 'error', 'message' => 'Quiz not found'];
        }

        $retest_months = (int) ($quiz['retest_period_months'] ?? 0);

        if ($retest_months <= 0) {
            return [
                'status' => 'success',
                'skipped' => true,
                'reason' => 'no_retest_period'
            ];
        }

        if ($completed_at === null) {
            $attempt_stmt = $pdo->prepare("SELECT completed_at FROM user_quiz_attempts WHERE user_id = ? AND quiz_id = ? AND status IN ('passed', 'completed') AND completed_at IS NOT NULL ORDER BY completed_at DESC LIMIT 1");
            $attempt_stmt->execute([$user_id, $quiz_id]);
            $attempt = $attempt_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$attempt) {
                return ['status' => 'error', 'message' => 'No completed attempt found'];
            }

            $completed_at = $attempt['completed_at'];
        }

        $completed_date = new DateTime($completed_at);
        $eligible_date = clone $completed_date;
        $eligible_date->modify("+{$retest_months} months");

        $retest_enabled = (time() >= $eligible_date->getTimestamp()) ? 1 : 0;

        $tracking_stmt = $pdo->prepare("INSERT INTO quiz_retest_tracking (user_id, quiz_id, last_passed_date, retest_eligible_date, retest_enabled) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_passed_date = VALUES(last_passed_date), retest_eligible_date = VALUES(retest_eligible_date), retest_enabled = VALUES(retest_enabled), updated_at = CURRENT_TIMESTAMP");

        $tracking_stmt->execute([
            $user_id,
            $quiz_id,
            $completed_date->format('Y-m-d'),
            $eligible_date->format('Y-m-d'),
            $retest_enabled
        ]);

        if (function_exists('log_debug')) {
            log_debug(
                "Retest tracking updated - User {$user_id}, Quiz {$quiz_id}, Eligible {$eligible_date->format('Y-m-d')}, Enabled {$retest_enabled}",
                'INFO'
            );
        }

        return [
            'status' => 'success',
            'retest_eligible_date' => $eligible_date->format('Y-m-d'),
            'retest_enabled' => $retest_enabled
        ];
    } catch (Exception $e) {
        error_log('Error updating quiz retest tracking: ' . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Enable retests for user when retest period expires
 * Auto-sets is_in_training flag for users with eligible retests
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Status with retests enabled and is_in_training flag status
 */
function check_and_enable_retests($pdo, $user_id) {
    try {
        $pdo->beginTransaction();

        $retests_enabled = [];
        $flag_updated = false;

        // Get all quizzes user has passed
        $quiz_stmt = $pdo->prepare("
            SELECT DISTINCT
                   tq.id,
                   tq.retest_period_months,
                   uqa.completed_at,
                   uqa.id              AS attempt_id,
                   tcc.content_id,
                   LOWER(COALESCE(tcc.content_type, '')) AS content_type,
                   tcc.course_id
              FROM training_quizzes tq
              JOIN user_quiz_attempts uqa
                ON tq.id = uqa.quiz_id
              JOIN training_course_content tcc
                ON tq.content_id = tcc.content_id
               AND (LOWER(COALESCE(tq.content_type, '')) = LOWER(COALESCE(tcc.content_type, ''))
                 OR COALESCE(tq.content_type, '') = '')
             WHERE uqa.user_id = ?
               AND uqa.status = 'passed'
             ORDER BY uqa.completed_at DESC
        ");
        $quiz_stmt->execute([$user_id]);
        $quizzes = $quiz_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($quizzes as $quiz) {
            $retest_months = (int)($quiz['retest_period_months'] ?? 0);

            // Skip if no retest period
            if ($retest_months === 0) {
                continue;
            }

            // Calculate retest date
            $completed_date = new DateTime($quiz['completed_at']);
            $completed_date->modify("+{$retest_months} months");
            $retest_timestamp = $completed_date->getTimestamp();
            $now_timestamp = time();

            // If retest period has passed, enable retesting
            if ($now_timestamp >= $retest_timestamp) {
                $retests_enabled[] = [
                    'quiz_id' => $quiz['id'],
                    'retest_date' => $completed_date->format('Y-m-d H:i:s')
                ];

                // Ensure retest tracking reflects the expired period
                if (function_exists('upsert_quiz_retest_tracking')) {
                    upsert_quiz_retest_tracking($pdo, $user_id, (int)$quiz['id'], $quiz['completed_at']);
                }

                // Create new "pending" attempt to allow retaking
                $new_attempt_stmt = $pdo->prepare("
                    INSERT INTO user_quiz_attempts
                    (user_id, quiz_id, status, started_at, created_at)
                    VALUES (?, ?, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                    status = 'pending',
                    started_at = CURRENT_TIMESTAMP
                ");
                $new_attempt_stmt->execute([$user_id, $quiz['id']]);

                // Re-open the related training progress so this quiz no longer counts toward completion
                $norm_ct = $quiz['content_type'] ?: 'post';
                $progress_stmt = $pdo->prepare("
                    UPDATE training_progress
                       SET status = 'in_progress',
                           quiz_completed = 0,
                           completion_date = NULL,
                           quiz_completed_at = NULL
                     WHERE user_id = ?
                       AND content_id = ?
                       AND (content_type = ? OR content_type = '' OR content_type IS NULL)
                ");
                $progress_stmt->execute([$user_id, $quiz['content_id'], $norm_ct]);

                // Re-open the parent course assignment if it was marked completed
                if (!empty($quiz['course_id'])) {
                    $assignment_stmt = $pdo->prepare("
                        UPDATE user_training_assignments
                           SET status = 'in_progress',
                               completion_date = NULL
                         WHERE user_id = ?
                           AND course_id = ?
                           AND status = 'completed'
                    ");
                    $assignment_stmt->execute([$user_id, $quiz['course_id']]);
                }

                log_debug("Retest enabled for user $user_id on quiz {$quiz['id']} (progress reopened)", 'INFO');
            }
        }

        // If any retests were enabled, set is_in_training flag
        if (!empty($retests_enabled)) {
            $flag_stmt = $pdo->prepare("
                UPDATE users
                SET is_in_training = 1, updated_at = NOW()
                WHERE id = ?
            ");
            $flag_stmt->execute([$user_id]);
            $flag_updated = true;

            // Update session
            if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
                $_SESSION['user_is_in_training'] = 1;
            }

            log_debug("Set is_in_training = 1 for user $user_id ({count($retests_enabled)} retests available)", 'INFO');
        }

        $pdo->commit();

        return [
            'status' => 'success',
            'retests_enabled' => count($retests_enabled),
            'flag_updated' => $flag_updated,
            'retests' => $retests_enabled
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error checking and enabling retests: " . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Get all quizzes available for retest for a user
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array List of quizzes available for retest
 */
function get_retestable_quizzes($pdo, $user_id) {
    try {
        $retestable = [];

        // Get all completed/passed quizzes with retest periods
        $stmt = $pdo->prepare("
            SELECT
                tq.id,
                tq.quiz_title AS title,
                tq.retest_period_months,
                MAX(uqa.completed_at) AS completed_at
            FROM training_quizzes tq
            JOIN user_quiz_attempts uqa
              ON tq.id = uqa.quiz_id
             AND uqa.user_id = ?
             AND uqa.status IN ('passed', 'completed')
            WHERE tq.retest_period_months > 0
            GROUP BY tq.id, tq.quiz_title, tq.retest_period_months
            ORDER BY tq.quiz_title
        ");
        $stmt->execute([$user_id]);
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($quizzes as $quiz) {
            $eligibility = check_quiz_retest_eligibility($pdo, $user_id, $quiz['id']);

            if ($eligibility['status'] === 'success') {
                $retestable[] = array_merge($quiz, $eligibility);
            }
        }

        return $retestable;
    } catch (PDOException $e) {
        error_log("Error getting retestable quizzes: " . $e->getMessage());
        return [];
    }
}


