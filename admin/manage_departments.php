<?php
/**
 * Department Management Page
 * Admin interface for managing departments - create, edit, delete, manage members
 * Only accessible by admin users
 *
 * Created: 2025-11-19
 * Author: Claude Code Assistant
 * Version: 1.0.0
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/department_helpers.php';

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$page_title = 'Department Management';
$success_message = '';
$error_message = '';

// Check if departments table exists
$departments_table_exists = false;
try {
    $pdo->query("SELECT id FROM departments LIMIT 1");
    $departments_table_exists = true;
} catch (PDOException $e) {
    $error_message = "Departments table doesn't exist. Please run the migration first.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $departments_table_exists) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'create_department':
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';

            // Validation
            if (empty($name)) {
                $error_message = 'Department name is required.';
            } elseif (strlen($name) > 255) {
                $error_message = 'Department name must be 255 characters or less.';
            } else {
                try {
                    $result = create_department($pdo, $name, $description, $_SESSION['user_id']);
                    if ($result) {
                        $success_message = 'Department created successfully!';
                    } else {
                        $error_message = 'Error creating department. The name may already exist.';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error creating department: ' . $e->getMessage();
                }
            }
            break;

        case 'edit_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';

            if ($dept_id <= 0) {
                $error_message = 'Invalid department ID.';
            } elseif (empty($name)) {
                $error_message = 'Department name is required.';
            } elseif (strlen($name) > 255) {
                $error_message = 'Department name must be 255 characters or less.';
            } else {
                try {
                    if (update_department($pdo, $dept_id, $name, $description)) {
                        $success_message = 'Department updated successfully!';
                    } else {
                        $error_message = 'Error updating department.';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error updating department: ' . $e->getMessage();
                }
            }
            break;

        case 'delete_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;

            if ($dept_id <= 0) {
                $error_message = 'Invalid department ID.';
            } else {
                try {
                    if (delete_department($pdo, $dept_id)) {
                        $success_message = 'Department deleted successfully!';
                    } else {
                        $error_message = 'Error deleting department.';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error deleting department: ' . $e->getMessage();
                }
            }
            break;

        case 'add_user_to_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            if ($dept_id <= 0 || $user_id <= 0) {
                $error_message = 'Invalid department or user ID.';
            } else {
                try {
                    if (assign_user_to_department($pdo, $user_id, $dept_id, $_SESSION['user_id'])) {
                        // Also assign user to existing department courses
                        $courses_assigned = assign_user_to_department_courses($pdo, $user_id, $dept_id, $_SESSION['user_id']);
                        if ($courses_assigned > 0) {
                            $success_message = "User added to department and assigned to $courses_assigned course(s)!";
                        } else {
                            $success_message = 'User added to department successfully!';
                        }
                    } else {
                        $error_message = 'Error adding user to department.';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error adding user: ' . $e->getMessage();
                }
            }
            break;

        case 'bulk_add_users_to_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;
            $user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids'])
                ? array_values(array_unique(array_map('intval', $_POST['user_ids'])))
                : [];

            if ($dept_id <= 0 || empty($user_ids)) {
                $error_message = 'Please select at least one user to add.';
            } else {
                $added_count = 0;
                $assigned_courses = 0;

                foreach ($user_ids as $user_id) {
                    if ($user_id <= 0) {
                        continue;
                    }

                    try {
                        if (assign_user_to_department($pdo, $user_id, $dept_id, $_SESSION['user_id'])) {
                            $added_count++;
                            $assigned_courses += assign_user_to_department_courses($pdo, $user_id, $dept_id, $_SESSION['user_id']);
                        }
                    } catch (PDOException $e) {
                        error_log('Error adding user to department: ' . $e->getMessage());
                    }
                }

                if ($added_count > 0) {
                    $success_message = "Added $added_count user(s) to the department.";
                    if ($assigned_courses > 0) {
                        $success_message .= " Assigned $assigned_courses course enrollment(s).";
                    }
                } else {
                    $error_message = 'No users were added to the department.';
                }
            }
            break;

        case 'remove_user_from_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            if ($dept_id <= 0 || $user_id <= 0) {
                $error_message = 'Invalid department or user ID.';
            } else {
                try {
                    if (remove_user_from_department($pdo, $user_id, $dept_id)) {
                        $success_message = 'User removed from department successfully!';
                    } else {
                        $error_message = 'Error removing user from department.';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Error removing user: ' . $e->getMessage();
                }
            }
            break;

        case 'bulk_remove_users_from_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;
            $user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids'])
                ? array_values(array_unique(array_map('intval', $_POST['user_ids'])))
                : [];

            if ($dept_id <= 0 || empty($user_ids)) {
                $error_message = 'Please select at least one user to remove.';
            } else {
                $removed_count = 0;

                foreach ($user_ids as $user_id) {
                    if ($user_id <= 0) {
                        continue;
                    }

                    try {
                        if (remove_user_from_department($pdo, $user_id, $dept_id)) {
                            $removed_count++;
                        }
                    } catch (PDOException $e) {
                        error_log('Error removing user from department: ' . $e->getMessage());
                    }
                }

                if ($removed_count > 0) {
                    $success_message = "Removed $removed_count user(s) from the department.";
                } else {
                    $error_message = 'No users were removed from the department.';
                }
            }
            break;
    }
}

// Fetch all departments
$departments = [];
if ($departments_table_exists) {
    $departments = get_all_departments($pdo);
}

// Fetch all active users
$all_users = [];
try {
    $stmt = $pdo->query("SELECT id, name, role, is_in_training FROM users WHERE is_active = 1 ORDER BY name ASC");
    $all_users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="/index.php">Home</a>
        <span>></span>
        <a href="/admin/manage_users.php">Admin</a>
        <span>></span>
        <span class="current">Department Management</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🏢 Department Management</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-primary" onclick="showCreateDepartmentModal()">➕ Create New Department</button>
                <a href="/admin/manage_users.php" class="btn btn-secondary">← Back to Admin</a>
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

        <?php if (!$departments_table_exists): ?>
            <div class="card-content" style="padding: 20px;">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                    <h3 style="color: #856404; margin: 0 0 8px 0;">⚠️ Database Setup Required</h3>
                    <p style="color: #856404; margin: 0;">The departments table doesn't exist in your database. Please run the following migration first:</p>
                    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; margin: 8px 0; font-family: monospace; font-size: 12px;">
                        migrations/add_departments.sql
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card-content" style="padding: 0;">
                <?php if (empty($departments)): ?>
                    <div style="padding: 40px; text-align: center; color: #6c757d;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🏢</div>
                        <h3>No Departments Found</h3>
                        <p>There are no departments yet. Click "Create New Department" to add one.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Description</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Members</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Courses</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Created By</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 12px; font-weight: 500;"><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td style="padding: 12px; color: #6c757d; font-size: 14px;">
                                            <?php echo htmlspecialchars(substr($dept['description'] ?? '', 0, 50)); ?>
                                            <?php if (strlen($dept['description'] ?? '') > 50): ?>...<?php endif; ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: #e7f3ff; color: #0066cc; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                                👥 <?php echo intval($dept['member_count']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px;">
                                            <span style="background: #f0f0f0; color: #333; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                                📚 <?php echo intval($dept['course_count']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; color: #6c757d; font-size: 14px;">
                                            <?php echo htmlspecialchars($dept['creator_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <button type="button" class="btn btn-sm" style="background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showManageMembersModal(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>')">Manage</button>
                                                <button type="button" class="btn btn-sm" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showEditDepartmentModal(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>', '<?php echo htmlspecialchars($dept['description'] ?? ''); ?>')">Edit</button>
                                                <button type="button" class="btn btn-sm" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>')">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="padding: 16px; background: #f8f9fa; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">
                        <strong>💡 Tip:</strong> Use the "Manage" button to add users to a department. When you add a user, they will automatically be assigned to all existing department courses.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Department Modal -->
<div id="createDepartmentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 16px 0;">➕ Create New Department</h3>
        <form method="POST" action="manage_departments.php">
            <input type="hidden" name="action" value="create_department">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Department Name *</label>
                <input type="text" name="name" required maxlength="255" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Description</label>
                <textarea name="description" maxlength="1000" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; height: 100px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideCreateDepartmentModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Create Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Department Modal -->
<div id="editDepartmentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 16px 0;">✏️ Edit Department</h3>
        <form method="POST" action="manage_departments.php">
            <input type="hidden" name="action" value="edit_department">
            <input type="hidden" id="edit_dept_id" name="dept_id">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Department Name *</label>
                <input type="text" id="edit_name" name="name" required maxlength="255" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Description</label>
                <textarea id="edit_description" name="description" maxlength="1000" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; height: 100px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideEditDepartmentModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Update Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Members Modal -->
<div id="manageMembersModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
        <h3 style="margin: 0 0 16px 0;">👥 Manage Department Members</h3>
        <div id="manageMembersContent"></div>
        <div style="margin-top: 16px;">
            <button type="button" onclick="hideManageMembersModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Close</button>
        </div>
    </div>
</div>

<script>
function showCreateDepartmentModal() {
    document.getElementById('createDepartmentModal').style.display = 'block';
}

function hideCreateDepartmentModal() {
    document.getElementById('createDepartmentModal').style.display = 'none';
}

function showEditDepartmentModal(deptId, name, description) {
    document.getElementById('edit_dept_id').value = deptId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('editDepartmentModal').style.display = 'block';
}

function hideEditDepartmentModal() {
    document.getElementById('editDepartmentModal').style.display = 'none';
}

function showManageMembersModal(deptId, deptName) {
    // Fetch members for this department
    fetch(`/admin/api/get_department_members.php?dept_id=${deptId}`)
        .then(response => response.json())
        .then(data => {
            let html = `<h4 style="margin-top: 0;">Department: <strong>${deptName}</strong></h4>`;

            // Department courses block with toggle button
            html += '<div style="margin-bottom: 12px;">';
            html += '<button type="button" id="toggleCoursesBtn" style="background: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">View Department Courses</button>';
            html += '</div>';
            html += '<div id="departmentCourses" style="display: none; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 12px; margin-bottom: 16px;">';
            html += '<h5 style="margin-top: 0;">Department Courses</h5>';
            if (!data.courses || data.courses.length === 0) {
                html += '<p style="color: #6c757d;">No courses are assigned to this department yet.</p>';
            } else {
                html += '<ul style="padding-left: 18px; color: #333; margin: 0;">';
            // Department courses
            html += '<h5>Department Courses:</h5>';
            if (!data.courses || data.courses.length === 0) {
                html += '<p style="color: #6c757d;">No courses are assigned to this department yet.</p>';
            } else {
                html += '<ul style="padding-left: 18px; color: #333;">';
                data.courses.forEach(course => {
                    html += `<li><strong>${course.name}</strong> <small style="color: #6c757d;">(Assigned users: ${course.assigned_users}, Completed: ${course.completed_users})</small></li>`;
                });
                html += '</ul>';
            }
            html += '</div>';

            // Bulk add users section with checkboxes
            html += '<h5>Bulk Add Users to Department:</h5>';
            const availableUsers = data.all_users.filter(user => !data.member_ids.includes(user.id));
            if (availableUsers.length === 0) {
                html += '<p style="color: #6c757d;">No available users to add.</p>';
            } else {
                html += '<form id="bulkAddForm" method="POST" action="manage_departments.php" style="margin-bottom: 16px;">';
                html += '<input type="hidden" name="action" value="bulk_add_users_to_department">';
                html += `<input type="hidden" name="dept_id" value="${deptId}">`;
                html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">';
                html += '<label style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #495057;">';
                html += '<input type="checkbox" id="bulkAddSelectAll"> Select All';
                html += '</label>';
                html += '</div>';
                html += '<div style="overflow-x: auto;">';
                html += '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead>';
                html += '<tr style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">';
                html += '<th style="padding: 8px; width: 50px;"></th>';
                html += '<th style="padding: 8px; text-align: left;">Name</th>';
                html += '<th style="padding: 8px; text-align: left;">Role</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                availableUsers.forEach(user => {
                    html += `<tr style="border-bottom: 1px solid #f1f1f1;">
                        <td style="padding: 8px; text-align: center;"><input type="checkbox" class="bulk-add-checkbox" name="user_ids[]" value="${user.id}"></td>
                        <td style="padding: 8px;">${user.name}</td>
                        <td style="padding: 8px; color: #6c757d;">${user.role}</td>
                    </tr>`;
                });
                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                html += '<div style="margin-top: 8px; display: flex; justify-content: flex-end; gap: 8px; align-items: center;">';
                html += '<button type="submit" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Add Selected Users</button>';
                html += '</div>';
                html += '</form>';
            }

            // Bulk remove users section with checkboxes
            html += '<h5>Bulk Remove Users from Department:</h5>';

            // Current members
            html += '<h5>Current Members:</h5>';
            if (data.members.length === 0) {
                html += '<p style="color: #6c757d;">No members available to remove.</p>';
            } else {
                html += '<form id="bulkRemoveForm" method="POST" action="manage_departments.php">';
                html += '<input type="hidden" name="action" value="bulk_remove_users_from_department">';
                html += `<input type="hidden" name="dept_id" value="${deptId}">`;
                html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">';
                html += '<label style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #495057;">';
                html += '<input type="checkbox" id="bulkRemoveSelectAll"> Select All';
                html += '</label>';
                html += '</div>';
                html += '<div style="overflow-x: auto;">';
                html += '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead>';
                html += '<tr style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">';
                html += '<th style="padding: 8px; width: 50px;"></th>';
                html += '<th style="padding: 8px; text-align: left;">Name</th>';
                html += '<th style="padding: 8px; text-align: left;">Role</th>';
                html += '<th style="padding: 8px; text-align: left;">Added</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                data.members.forEach(member => {
                    const addedDate = member.assigned_date ? new Date(member.assigned_date).toLocaleDateString() : '';
                    html += `<tr style="border-bottom: 1px solid #f1f1f1;">
                        <td style="padding: 8px; text-align: center;"><input type="checkbox" class="bulk-remove-checkbox" name="user_ids[]" value="${member.id}"></td>
                        <td style="padding: 8px;">${member.name}</td>
                        <td style="padding: 8px; color: #6c757d;">${member.role}</td>
                        <td style="padding: 8px; color: #6c757d;">${addedDate}</td>
                    </tr>`;
                });
                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                html += '<div style="margin-top: 8px; display: flex; justify-content: flex-end; gap: 8px; align-items: center;">';
                html += '<button type="submit" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Remove Selected Users</button>';
                html += '</div>';
                html += '</form>';
            }

            const contentEl = document.getElementById('manageMembersContent');
            contentEl.innerHTML = html;

            const coursesBtn = document.getElementById('toggleCoursesBtn');
            const coursesPanel = document.getElementById('departmentCourses');
            if (coursesBtn && coursesPanel) {
                coursesBtn.addEventListener('click', () => {
                    const isHidden = coursesPanel.style.display === 'none';
                    coursesPanel.style.display = isHidden ? 'block' : 'none';
                    coursesBtn.textContent = isHidden ? 'Hide Department Courses' : 'View Department Courses';
                });
            }

            function setupSelectAll(selectAllId, checkboxSelector) {
                const selectAllEl = document.getElementById(selectAllId);
                const checkboxes = Array.from(document.querySelectorAll(checkboxSelector));

                if (!selectAllEl || checkboxes.length === 0) {
                    return;
            // Bulk add users section
            html += '<h5>Bulk Add Users to Department:</h5>';
            html += '<form method="POST" action="manage_departments.php" style="margin-bottom: 16px;">';
            html += '<input type="hidden" name="action" value="bulk_add_users_to_department">';
            html += '<input type="hidden" name="dept_id" value="' + deptId + '">';
            html += '<div style="display: flex; gap: 8px; align-items: center;">';
            html += '<select name="user_ids[]" multiple required size="6" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';

            data.all_users.forEach(user => {
                if (!data.member_ids.includes(user.id)) {
                    html += `<option value="${user.id}">${user.name} (${user.role})</option>`;
                }

                selectAllEl.addEventListener('change', () => {
                    checkboxes.forEach(cb => {
                        cb.checked = selectAllEl.checked;
                    });
                });

                checkboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        const allChecked = checkboxes.every(item => item.checked);
                        const noneChecked = checkboxes.every(item => !item.checked);

                        if (allChecked) {
                            selectAllEl.checked = true;
                            selectAllEl.indeterminate = false;
                        } else if (noneChecked) {
                            selectAllEl.checked = false;
                            selectAllEl.indeterminate = false;
                        } else {
                            selectAllEl.indeterminate = true;
                        }
                    });
                });
            }

            function setupFormValidation(formId, checkboxSelector, message) {
                const formEl = document.getElementById(formId);
                if (!formEl) {
                    return;
                }

                formEl.addEventListener('submit', (event) => {
                    const hasSelection = Array.from(formEl.querySelectorAll(checkboxSelector)).some(cb => cb.checked);
                    if (!hasSelection) {
                        event.preventDefault();
                        alert(message);
                    }
                });
            }

            setupSelectAll('bulkAddSelectAll', '.bulk-add-checkbox');
            setupSelectAll('bulkRemoveSelectAll', '.bulk-remove-checkbox');
            setupFormValidation('bulkAddForm', '.bulk-add-checkbox', 'Please select at least one user to add.');
            setupFormValidation('bulkRemoveForm', '.bulk-remove-checkbox', 'Please select at least one user to remove.');

            html += '</select>';
            html += '<div style="display: flex; flex-direction: column; gap: 8px;">';
            html += '<button type="submit" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Add Selected</button>';
            html += '<small style="color: #6c757d;">Hold Ctrl (Cmd on Mac) to select multiple users.</small>';
            html += '</div>';
            html += '</div>';
            html += '</form>';

            // Bulk remove users section
            html += '<h5>Bulk Remove Users from Department:</h5>';
            if (data.members.length === 0) {
                html += '<p style="color: #6c757d;">No members available to remove.</p>';
            } else {
                html += '<form method="POST" action="manage_departments.php">';
                html += '<input type="hidden" name="action" value="bulk_remove_users_from_department">';
                html += '<input type="hidden" name="dept_id" value="' + deptId + '">';
                html += '<div style="display: flex; gap: 8px; align-items: center;">';
                html += '<select name="user_ids[]" multiple required size="6" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';

                data.members.forEach(member => {
                    html += `<option value="${member.id}">${member.name} (${member.role})</option>`;
                });

                html += '</select>';
                html += '<div style="display: flex; flex-direction: column; gap: 8px;">';
                html += '<button type="submit" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Remove Selected</button>';
                html += '<small style="color: #6c757d;">Hold Ctrl (Cmd on Mac) to select multiple users.</small>';
                html += '</div>';
                html += '</div>';
                html += '</form>';
            }

            document.getElementById('manageMembersContent').innerHTML = html;
            document.getElementById('manageMembersModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('manageMembersContent').innerHTML = '<p style="color: #dc3545;">Error loading members.</p>';
        });
}

function hideManageMembersModal() {
    document.getElementById('manageMembersModal').style.display = 'none';
}

function deleteDepartment(deptId, deptName) {
    if (confirm(`Are you sure you want to delete the department "${deptName}"?`)) {
        if (confirm('This will also remove all users from this department. Are you absolutely sure?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_departments.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_department';

            const deptIdInput = document.createElement('input');
            deptIdInput.type = 'hidden';
            deptIdInput.name = 'dept_id';
            deptIdInput.value = deptId;

            form.appendChild(actionInput);
            form.appendChild(deptIdInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.id === 'createDepartmentModal') {
        hideCreateDepartmentModal();
    } else if (event.target.id === 'editDepartmentModal') {
        hideEditDepartmentModal();
    } else if (event.target.id === 'manageMembersModal') {
        hideManageMembersModal();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
