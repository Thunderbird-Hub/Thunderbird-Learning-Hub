<?php
/**
 * Training Course Management Page
 * Admin interface for managing training courses, content, and assignments
 * Only accessible by admin users
 *
 * Created: 2025-11-05
 * Updated: 2025-11-06 22:02:00 UTC - Added content count display and enhanced debugging
 * Author: Claude Code Assistant
 * Version: 2.4.6 (Enhanced with debugging)
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/training_helpers.php';
require_once __DIR__ . '/../includes/department_helpers.php';

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$page_title = 'Training Course Management';
$success_message = '';
$error_message = '';

// Check if training tables exist
$training_tables_exist = false;
try {
    $pdo->query("SELECT id FROM training_courses LIMIT 1");
    $training_tables_exist = true;
} catch (PDOException $e) {
    $error_message = "Training tables don't exist. Please import the add_training_system.sql file first.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $training_tables_exist) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // DEBUG: Log that POST request was received
    $debug_msg = "=== ASSIGNMENT FORM SUBMISSION ===\n";
    $debug_msg .= "DEBUG: POST request received - action: $action\n";
    $debug_msg .= "DEBUG: POST data: " . json_encode($_POST) . "\n";
    $debug_msg .= "DEBUG: Session data: " . json_encode($_SESSION) . "\n";
    $debug_msg .= "DEBUG: Training tables exist: " . ($training_tables_exist ? 'YES' : 'NO') . "\n";
    $debug_msg .= "DEBUG: Request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
    $debug_msg .= "DEBUG: Debug mode: " . (isset($_POST['debug_mode']) ? $_POST['debug_mode'] : 'NOT SET') . "\n";
    $debug_msg .= "=== END ASSIGNMENT FORM DEBUG ===\n";

    error_log($debug_msg);

    // Also write to debug file for easier access
    file_put_contents(__DIR__ . '/assignment_debug.log', date('Y-m-d H:i:s') . " - " . $debug_msg, FILE_APPEND | LOCK_EX);

    switch ($action) {
        case 'create_course':
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $department = isset($_POST['department']) ? trim($_POST['department']) : '';
            $estimated_hours = isset($_POST['estimated_hours']) ? floatval($_POST['estimated_hours']) : 0;

            // Validation
            if (empty($name)) {
                $error_message = 'Course name is required.';
            } elseif (strlen($name) > 255) {
                $error_message = 'Course name must be 255 characters or less.';
            } elseif ($estimated_hours < 0 || $estimated_hours > 999.9) {
                $error_message = 'Estimated hours must be between 0 and 999.9.';
            } else {
                $course_id = create_training_course($pdo, $name, $description, $department, $_SESSION['user_id']);
                if ($course_id) {
                    $success_message = 'Training course created successfully!';
                } else {
                    $error_message = 'Error creating training course.';
                }
            }
            break;

        case 'assign_departments_to_course':
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            $dept_ids = isset($_POST['dept_ids']) ? $_POST['dept_ids'] : [];

            if ($course_id <= 0) {
                $error_message = 'Invalid course ID.';
            } else {
                try {
                    // Delete existing department assignments for this course
                    $delete_stmt = $pdo->prepare("DELETE FROM course_departments WHERE course_id = ?");
                    $delete_stmt->execute([$course_id]);

                    // Add new department assignments
                    $assigned_count = 0;
                    if (!empty($dept_ids)) {
                        $insert_stmt = $pdo->prepare("INSERT INTO course_departments (course_id, department_id) VALUES (?, ?)");
                        foreach ($dept_ids as $dept_id) {
                            $dept_id = intval($dept_id);
                            if ($dept_id > 0) {
                                $insert_stmt->execute([$course_id, $dept_id]);
                                $assigned_count++;
                            }
                        }
                    }

                    if ($assigned_count > 0) {
                        $success_message = "Course assigned to $assigned_count department(s) successfully!";
                    } else {
                        $success_message = 'Course removed from all departments.';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error assigning course to departments: ' . $e->getMessage();
                }
            }
            break;

        case 'assign_course':
            $error_message = 'Direct user assignments have been disabled. Assign courses through departments instead.';
            break;

        case 'delete_course':
            $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

            if ($course_id <= 0) {
                $error_message = 'Invalid course ID.';
            } else {
                try {
                    // Check if course has any assigned users
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_training_assignments WHERE course_id = ?");
                    $check_stmt->execute([$course_id]);
                    $assigned_count = $check_stmt->fetch()['count'];

                    if ($assigned_count > 0) {
                        $error_message = 'Cannot delete course with assigned users. Please unassign all users first.';
                    } else {
                        /**
 * Flip quizzes to "unassigned" for any content that belonged to this course,
 * then remove course content rows, then delete the course.
 * We normalize empty content_type as 'post' to match legacy data.
 */
$pdo->beginTransaction();

try {
    // 1) Mark related quizzes as unassigned (do NOT delete quizzes)
    $mark_unassigned = $pdo->prepare("
        UPDATE training_quizzes tq
        JOIN training_course_content tcc
          ON tq.content_id = tcc.content_id
         AND COALESCE(NULLIF(tq.content_type,''), 'post') = COALESCE(NULLIF(tcc.content_type,''), 'post')
        SET tq.is_assigned = 0
        WHERE tcc.course_id = ?
    ");
    $mark_unassigned->execute([$course_id]);

     if (function_exists('log_debug')) {
        log_debug("Course delete: set quizzes unassigned for course_id={$course_id}");
    }

    // 2) Delete course content mapping
    $delete_content = $pdo->prepare("DELETE FROM training_course_content WHERE course_id = ?");
    $delete_content->execute([$course_id]);

    // 3) Delete the course itself
    $delete_course = $pdo->prepare("DELETE FROM training_courses WHERE id = ?");
    $delete_course->execute([$course_id]);

    $pdo->commit();

    if ($delete_course->rowCount() > 0) {
        $success_message = 'Training course deleted successfully!';
    } else {
        $error_message = 'Course not found.';
    }
} catch (PDOException $e) {
    $pdo->rollBack();
    $error_message = 'Database error: ' . $e->getMessage();
}

                        if ($delete_course->rowCount() > 0) {
                            $success_message = 'Training course deleted successfully!';
                        } else {
                            $error_message = 'Course not found.';
                        }
                    }
                } catch (PDOException $e) {
                    $error_message = 'Database error: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Handle AJAX requests (none at this time for this page)

// Handle GET requests (like delete)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $training_tables_exist && isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'delete_course') {
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

        if ($course_id <= 0) {
            $error_message = 'Invalid course ID.';
        } else {
            try {
                // Check if course has any assigned users
                $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_training_assignments WHERE course_id = ?");
                $check_stmt->execute([$course_id]);
                $assigned_count = $check_stmt->fetch()['count'];

                if ($assigned_count > 0) {
                    $error_message = 'Cannot delete course with assigned users. Please unassign all users first.';
                } else {
                    $pdo->beginTransaction();

try {
    // 1) Mark related quizzes as unassigned (do NOT delete quizzes)
    $mark_unassigned = $pdo->prepare("
        UPDATE training_quizzes tq
        JOIN training_course_content tcc
          ON tq.content_id = tcc.content_id
         AND COALESCE(NULLIF(tq.content_type,''), 'post') = COALESCE(NULLIF(tcc.content_type,''), 'post')
        SET tq.is_assigned = 0
        WHERE tcc.course_id = ?
    ");
    $mark_unassigned->execute([$course_id]);

    // 2) Delete course content mapping
    $delete_content = $pdo->prepare("DELETE FROM training_course_content WHERE course_id = ?");
    $delete_content->execute([$course_id]);

    // 3) Delete the course itself
    $delete_course = $pdo->prepare("DELETE FROM training_courses WHERE id = ?");
    $delete_course->execute([$course_id]);

    $pdo->commit();

    if ($delete_course->rowCount() > 0) {
        $success_message = 'Training course deleted successfully!';
    } else {
        $error_message = 'Course not found.';
    }
} catch (PDOException $e) {
    $pdo->rollBack();
    $error_message = 'Database error: ' . $e->getMessage();
}

                    if ($delete_course->rowCount() > 0) {
                        $success_message = 'Training course deleted successfully!';
                    } else {
                        $error_message = 'Course not found.';
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch data
$courses = [];
$all_departments = [];

if ($training_tables_exist) {
    try {
        // Fetch all departments for the dropdown
        $all_departments = get_all_departments($pdo);

        // --- BEGIN REPLACEMENT ---
    /**
     * Accurate stats per project rules using ONLY correlated subqueries (no outer refs in FROM):
     * - Completion: assigned user has completed ALL post items in the course.
     * - Post-only logic, legacy-safe: treat NULL/'' content_type as 'post'.
     */
    $sql = "
        SELECT
            c.id,
            c.name,
            c.department,
            c.description,
            c.estimated_hours,
            c.is_active,

            /* total assigned users (distinct) */
            (
                SELECT COUNT(DISTINCT uta.user_id)
                FROM user_training_assignments uta
                WHERE uta.course_id = c.id
            ) AS assigned_total_users,

            /* total post items (denominator for completion) */
            (
                SELECT COUNT(*)
                FROM training_course_content tcc
                WHERE tcc.course_id = c.id
                  AND COALESCE(NULLIF(tcc.content_type, ''), 'post') = 'post'
            ) AS total_post_items,

            /* completed users (NOT EXISTS any uncompleted post for that user) */
            (
                SELECT COUNT(DISTINCT uta.user_id)
                FROM user_training_assignments uta
                WHERE uta.course_id = c.id
                  /* require at least 1 post in course */
                  AND EXISTS (
                      SELECT 1
                      FROM training_course_content tcc0
                      WHERE tcc0.course_id = c.id
                        AND COALESCE(NULLIF(tcc0.content_type, ''), 'post') = 'post'
                  )
                  /* user has NO missing completions among course posts */
                  AND NOT EXISTS (
                      SELECT 1
                      FROM training_course_content tccp
                      WHERE tccp.course_id = c.id
                        AND COALESCE(NULLIF(tccp.content_type, ''), 'post') = 'post'
                        AND NOT EXISTS (
                            SELECT 1
                            FROM training_progress tp
                            WHERE tp.user_id = uta.user_id
                              AND tp.content_id = tccp.content_id
                              AND COALESCE(NULLIF(tp.content_type, ''), 'post')
                                  = COALESCE(NULLIF(tccp.content_type, ''), 'post')
                              AND tp.status = 'completed'
                        )
                  )
            ) AS completed_users,

            /* content_count for display = number of post items */
            (
                SELECT COUNT(*)
                FROM training_course_content tcc
                WHERE tcc.course_id = c.id
                  AND COALESCE(NULLIF(tcc.content_type, ''), 'post') = 'post'
            ) AS content_count

        FROM training_courses c
        ORDER BY c.name
    ";
    $courses = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    /* Derive active = assigned_total - completed_users, floor at 0 */
    foreach ($courses as &$courseRow) {
        $assignedTotal = (int)($courseRow['assigned_total_users'] ?? 0);
        $completedUsers = (int)($courseRow['completed_users'] ?? 0);
        $courseRow['assigned_active_users'] = max(0, $assignedTotal - $completedUsers);
    }
    unset($courseRow);

// --- END REPLACEMENT ---

    } catch (PDOException $e) {
        $error_message = 'Error fetching data: ' . $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="/index.php">Home</a>
        <span>></span>
        <span class="current">Training Course Management</span>
    </div>


    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🎓 Training Course Management</h2>
            <div class="card-actions">
                <a href="/index.php" class="btn btn-secondary">← Back to Home</a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="success-message" style="margin: 20px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; color: #155724;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="error-message" style="margin: 20px; padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$training_tables_exist): ?>
            <div class="card-content" style="padding: 20px;">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                    <h3 style="color: #856404; margin: 0 0 8px 0;">⚠️ Database Setup Required</h3>
                    <p style="color: #856404; margin: 0;">The training tables don't exist in your database. Please import the following SQL file first:</p>
                    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; margin: 8px 0; font-family: monospace; font-size: 12px;">
                        add_training_system.sql
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Create New Course Form -->
            <div class="card-content" style="padding: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    <h3 style="margin: 0; color: #495057;">➕ Create New Training Course</h3>
                    <button
                        type="button"
                        id="toggleCreateCourse"
                        style="background: #f8f9fa; color: #495057; border: 1px solid #ced4da; padding: 8px 12px; border-radius: 4px; font-size: 13px; cursor: pointer; min-width: 120px;"
                    >
                        Show Form ▾
                    </button>
                </div>

                <div id="createCoursePanel" style="margin-top: 16px; display: none;">
                    <form method="POST" action="manage_training_courses.php" style="margin: 0;">
                        <input type="hidden" name="action" value="create_course">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #495057;">
                                    Course Name <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" name="name" required maxlength="255"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;"
                                       placeholder="e.g., New Employee Orientation">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #495057;">
                                    Department
                                </label>
                                <select name="department"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($all_departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept['id']); ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #495057;">
                                Description
                            </label>
                            <textarea name="description" rows="3" maxlength="1000"
                                      style="width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; resize: vertical;"
                                      placeholder="Course description and objectives..."></textarea>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #495057;">
                                Estimated Hours
                            </label>
                            <input type="number" name="estimated_hours" min="0" max="999.9" step="0.1"
                                   style="width: 150px; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;"
                                   placeholder="0.0">
                            <span style="margin-left: 8px; color: #6c757d; font-size: 13px;">Optional: Estimated completion time</span>
                        </div>

                        <div style="text-align: right;">
                            <button type="submit" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; font-size: 14px; cursor: pointer; font-weight: 500;">
                                🎓 Create Course
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($training_tables_exist): ?>
        <div class="card" style="margin-top: 16px;">
            <div class="card-header">
                <h3 class="card-title" style="margin: 0;">📚 Existing Training Courses</h3>
            </div>

            <div class="card-content" style="padding: 0;">
                <?php if (empty($courses)): ?>
                    <div style="padding: 40px; text-align: center; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🎓</div>
                        <h3>No Training Courses Found</h3>
                        <p>There are no training courses in the database.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Course Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Department</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Assigned Users</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Completion Rate</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr style="border-bottom: 1px solid #dee2e6; <?php echo !$course['is_active'] ? 'background: #f8f9fa;' : ''; ?>">
                                        <td style="padding: 12px;">
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($course['name']); ?> <span style="color: #6c757d; font-size: 12px; font-weight: normal;">(<?php echo $course['content_count'] ?? 0; ?> items)</span></div>
                                            <?php if (!empty($course['description'])): ?>
                                                <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">
                                                    <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($course['estimated_hours'] > 0): ?>
                                                <div style="font-size: 11px; color: #17a2b8; margin-top: 2px;">
                                                    ⏱️ <?php echo $course['estimated_hours']; ?> hours
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php if (!empty($course['department'])): ?>
                                                <span style="background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 12px; font-size: 11px;">
                                                    <?php echo htmlspecialchars($course['department']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999; font-style: italic;">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <div style="font-weight: 500;">
                                                <?php echo (int)$course['assigned_active_users']; ?> active
                                                <span style="color: #6c757d; font-size: 12px; font-weight: normal;">
                                                    (<?php echo (int)$course['assigned_total_users']; ?> total)
                                                </span>
                                            </div>
                                            <div style="font-size: 11px; color: #28a745;">
                                                <?php echo (int)$course['completed_users']; ?> completed
                                            </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php
$denominator = (int)$course['assigned_total_users'];
$total_posts = (int)($course['total_post_items'] ?? 0);
$completion_rate = ($denominator > 0 && $total_posts > 0)
    ? (int)round(((int)$course['completed_users'] / $denominator) * 100)
    : 0;
?>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="flex: 1; background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden;">
                                                    <div style="background: <?php echo $completion_rate >= 75 ? '#28a745' : ($completion_rate >= 50 ? '#ffc107' : '#dc3545'); ?>; height: 100%; width: <?php echo $completion_rate; ?>%; transition: width 0.3s ease;"></div>
                                                </div>
                                                <span style="font-size: 12px; color: #6c757d; min-width: 40px;"><?php echo $completion_rate; ?>%</span>
                                            </div>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                                                <button type="button" class="btn btn-sm" style="background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="window.location='manage_course_content.php?course_id=<?php echo $course['id']; ?>'">📚 Content</button>
                                                <button type="button" class="btn btn-sm" style="background: #ffc107; color: #212529; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="window.location='edit_course.php?id=<?php echo $course['id']; ?>'">✏️ Edit</button>
                                                <?php if ((int)$course['assigned_total_users'] === 0): ?>
                                                <button type="button" class="btn btn-sm" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="if(confirm('Are you sure you want to delete this course? This action cannot be undone.')) { window.location='manage_training_courses.php?action=delete_course&course_id=<?php echo $course['id']; ?>'; }">🗑️ Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleCreateCourse');
    const panel = document.getElementById('createCoursePanel');

    if (toggleBtn && panel) {
        const setButtonLabel = (isOpen) => {
            toggleBtn.textContent = isOpen ? 'Hide Form ▴' : 'Show Form ▾';
        };

        setButtonLabel(false);

        toggleBtn.addEventListener('click', function() {
            const shouldOpen = panel.style.display === 'none';
            panel.style.display = shouldOpen ? 'block' : 'none';
            setButtonLabel(shouldOpen);
        });
    }
});

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
