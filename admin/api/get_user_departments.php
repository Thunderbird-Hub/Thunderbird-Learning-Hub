<?php
/**
 * API endpoint to get user's departments
 * Returns JSON response with user's department IDs
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/department_helpers.php';

// Only allow admin users
if (!is_admin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

try {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }

    // Get user's departments
    $departments = get_user_departments($pdo, $user_id);

    // Extract just the department IDs
    $department_ids = array_column($departments, 'id');

    echo json_encode([
        'success' => true,
        'departments' => $department_ids,
        'user_id' => $user_id
    ]);

} catch (Exception $e) {
    error_log("Error getting user departments: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>