<?php
/**
 * Department Management Page
 * Admin interface for managing departments
 * Only accessible by admin users
 *
 * Created: 2025-11-19
 * Author: Claude Code Assistant
 * Version: 1.0
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
    $error_message = "Departments table doesn't exist. Please run the database migration first.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $departments_table_exists) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'create_department':
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';

            if (empty($name)) {
                $error_message = 'Department name is required.';
            } elseif (strlen($name) > 255) {
                $error_message = 'Department name must be 255 characters or less.';
            } else {
                $dept_id = create_department($pdo, $name, $description, $_SESSION['user_id']);
                if ($dept_id) {
                    $success_message = 'Department created successfully!';
                } else {
                    $error_message = 'Error creating department. It may already exist.';
                }
            }
            break;

        case 'update_department':
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
                if (update_department($pdo, $dept_id, $name, $description)) {
                    $success_message = 'Department updated successfully!';
                } else {
                    $error_message = 'Error updating department.';
                }
            }
            break;

        case 'delete_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;

            if ($dept_id <= 0) {
                $error_message = 'Invalid department ID.';
            } else {
                if (delete_department($pdo, $dept_id)) {
                    $success_message = 'Department deleted successfully!';
                } else {
                    $error_message = 'Error deleting department.';
                }
            }
            break;

        case 'add_user_to_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            if ($dept_id <= 0 || $user_id <= 0) {
                $error_message = 'Invalid department or user ID.';
            } else {
                if (assign_user_to_department($pdo, $user_id, $dept_id, $_SESSION['user_id'])) {
                    // Also assign user to existing department courses
                    assign_user_to_department_courses($pdo, $user_id, $dept_id, $_SESSION['user_id']);
                    $success_message = 'User added to department and assigned to department courses!';
                } else {
                    $error_message = 'Error adding user to department.';
                }
            }
            break;

        case 'remove_user_from_department':
            $dept_id = isset($_POST['dept_id']) ? intval($_POST['dept_id']) : 0;
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

            if ($dept_id <= 0 || $user_id <= 0) {
                $error_message = 'Invalid department or user ID.';
            } else {
                if (remove_user_from_department($pdo, $user_id, $dept_id)) {
                    $success_message = 'User removed from department!';
                } else {
                    $error_message = 'Error removing user from department.';
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

// Fetch all users
$all_users = [];
try {
    $stmt = $pdo->query("
        SELECT id, name, role, is_active
        FROM users
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Continue without users list
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="/index.php">Home</a>
        <span>></span>
        <a href="/admin/manage_quizzes.php">Admin</a>
        <span>></span>
        <span class="current">Department Management</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🏢 Department Management</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-primary" onclick="showCreateDepartmentModal()">➕ Create New Department</button>
                <a href="/admin/manage_quizzes.php" class="btn btn-secondary">← Back to Admin</a>
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
                    <p style="color: #856404; margin: 0;">The departments table doesn't exist in your database. Please run the migration first:</p>
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
                        <p>There are no departments yet. Click "Create New Department" to add the first one.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Department Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Description</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Members</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Courses</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 12px; font-weight: 500;"><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td style="padding: 12px; color: #6c757d; font-size: 14px;">
                                            <?php echo htmlspecialchars($dept['description'] ?? '—'); ?>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <span style="background: #e9ecef; padding: 4px 12px; border-radius: 12px; font-weight: 600; color: #495057;">
                                                <?php echo intval($dept['member_count']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <span style="background: #e9ecef; padding: 4px 12px; border-radius: 12px; font-weight: 600; color: #495057;">
                                                <?php echo intval($dept['course_count']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <button type="button" class="btn btn-sm" style="background: #17a2b8; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showManageMembersModal(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>')">👥 Members</button>

                                                <button type="button" class="btn btn-sm" style="background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showEditDepartmentModal(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>', '<?php echo htmlspecialchars($dept['description'] ?? ''); ?>')">Edit</button>

                                                <button type="button" class="btn btn-sm" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>')">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
                <textarea name="description" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-height: 100px; resize: vertical;"></textarea>
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
            <input type="hidden" name="action" value="update_department">
            <input type="hidden" id="edit_dept_id" name="dept_id">

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Department Name *</label>
                <input type="text" id="edit_name" name="name" required maxlength="255" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Description</label>
                <textarea id="edit_description" name="description" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-height: 100px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideEditDepartmentModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Update Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Members Modal -->
<div id="manageMembersModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
        <h3 style="margin: 0 0 16px 0;">👥 Manage Department Members</h3>

        <div id="membersContent">
            <p>Loading members...</p>
        </div>

        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #ddd; display: flex; gap: 8px; justify-content: flex-end;">
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

function deleteDepartment(deptId, deptName) {
    if (confirm(`Are you sure you want to delete the department "${deptName}"? This action cannot be undone.`)) {
        if (confirm(`WARNING: This will remove all user and course associations. Are you absolutely sure?`)) {
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

function showManageMembersModal(deptId, deptName) {
    document.getElementById('manageMembersModal').style.display = 'block';
    document.querySelector('#manageMembersModal h3').textContent = `👥 ${deptName} - Members`;

    // Load members via fetch
    loadDepartmentMembers(deptId);
}

function hideManageMembersModal() {
    document.getElementById('manageMembersModal').style.display = 'none';
}

async function loadDepartmentMembers(deptId) {
    try {
        const response = await fetch(`/admin/api/get_department_members.php?dept_id=${deptId}`);
        const data = await response.json();

        if (data.success) {
            let html = '<div style="margin-bottom: 16px;">';

            // Current members
            if (data.members && data.members.length > 0) {
                html += '<h4 style="margin: 0 0 12px 0;">Current Members</h4>';
                html += '<div style="border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden;">';

                data.members.forEach(member => {
                    html += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #dee2e6;">
                            <div>
                                <strong>${escapeHtml(member.name)}</strong>
                                <div style="font-size: 12px; color: #6c757d;">
                                    ${escapeHtml(member.role)} • ${member.post_count + member.reply_count} posts
                                </div>
                            </div>
                            <form method="POST" action="manage_departments.php" style="margin: 0;">
                                <input type="hidden" name="action" value="remove_user_from_department">
                                <input type="hidden" name="dept_id" value="${deptId}">
                                <input type="hidden" name="user_id" value="${member.id}">
                                <button type="submit" style="padding: 4px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">Remove</button>
                            </form>
                        </div>
                    `;
                });
                html += '</div>';
            } else {
                html += '<p style="color: #6c757d;">No members in this department yet.</p>';
            }

            // Add members dropdown
            html += '<h4 style="margin: 16px 0 12px 0;">Add Member</h4>';
            html += '<form method="POST" action="manage_departments.php" style="display: flex; gap: 8px;">';
            html += '<input type="hidden" name="action" value="add_user_to_department">';
            html += '<input type="hidden" name="dept_id" value="' + deptId + '">';
            html += '<select name="user_id" required style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
            html += '<option value="">Select a user to add...</option>';

            if (data.available_users && data.available_users.length > 0) {
                data.available_users.forEach(user => {
                    html += `<option value="${user.id}">${escapeHtml(user.name)} (${user.role})</option>`;
                });
            }

            html += '</select>';
            html += '<button type="submit" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Add User</button>';
            html += '</form>';
            html += '</div>';

            document.getElementById('membersContent').innerHTML = html;
        } else {
            document.getElementById('membersContent').innerHTML = `<p style="color: #dc3545;">Error loading members: ${escapeHtml(data.message)}</p>`;
        }
    } catch (error) {
        document.getElementById('membersContent').innerHTML = `<p style="color: #dc3545;">Error loading members: ${escapeHtml(error.message)}</p>`;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
