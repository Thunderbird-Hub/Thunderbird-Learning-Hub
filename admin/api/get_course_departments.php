<?php
/**
 * API Endpoint: Get Course Departments
 * Returns departments assigned to a course in JSON format
 * Used by manage_training_courses.php modal
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/user_helpers.php';

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
    // Get current department assignments for this course
    $stmt = $pdo->prepare("
        SELECT department_id
        FROM course_departments
        WHERE course_id = ?
    ");
    $stmt->execute([$course_id]);
    $assigned_dept_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Get all departments
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
    $all_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'assigned_dept_ids' => $assigned_dept_ids,
        'all_departments' => $all_departments
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
