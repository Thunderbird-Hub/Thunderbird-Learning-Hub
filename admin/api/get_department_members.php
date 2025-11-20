<?php
/**
 * API Endpoint: Get Department Members
 * Returns department members and available users in JSON format
 * Used by manage_departments.php modal
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/department_helpers.php';

header('Content-Type: application/json');

// Only allow admins
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$dept_id = isset($_GET['dept_id']) ? intval($_GET['dept_id']) : 0;

if ($dept_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid department ID']);
    exit;
}

try {
    // Get current members
    $members = get_department_members($pdo, $dept_id);

    // Get courses assigned to this department
    $courses = get_department_courses($pdo, $dept_id);

    // Get all active users
    $stmt = $pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name ASC");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get member IDs for filtering
    $member_ids = array_column($members, 'id');

    echo json_encode([
        'success' => true,
        'members' => $members,
        'courses' => $courses,
        'all_users' => $all_users,
        'member_ids' => $member_ids
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
