<?php
/**
 * API Endpoint: Get Course Departments
 * Returns departments assigned to a course in JSON format
 * Used by manage_training_courses.php modal
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

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid course ID']);
    exit;
}

try {
    // Get departments assigned to this course
    $stmt = $pdo->prepare("
        SELECT d.id, d.name
        FROM departments d
        JOIN course_departments cd ON d.id = cd.department_id
        WHERE cd.course_id = ?
        ORDER BY d.name
    ");
    $stmt->execute([$course_id]);
    $assigned_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all departments for the dropdown
    $all_departments = get_all_departments($pdo);

    // Get assigned department IDs for filtering
    $assigned_dept_ids = array_column($assigned_departments, 'id');

    echo json_encode([
        'success' => true,
        'assigned_departments' => $assigned_departments,
        'all_departments' => $all_departments,
        'assigned_dept_ids' => $assigned_dept_ids
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
