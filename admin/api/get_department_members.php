<?php
/**
 * API Endpoint: Get Department Members
 * Returns JSON data for department members modal
 * Called via AJAX from manage_departments.php
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/department_helpers.php';

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$dept_id = isset($_GET['dept_id']) ? intval($_GET['dept_id']) : 0;

if ($dept_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
    exit;
}

try {
    // Get department members
    $members = get_department_members($pdo, $dept_id);

    // Get all active users
    $stmt = $pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name ASC");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get member IDs for easy filtering
    $member_ids = array_column($members, 'id');

    echo json_encode([
        'success' => true,
        'members' => $members,
        'all_users' => $all_users,
        'member_ids' => $member_ids
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
