<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/department_helpers.php';

header('Content-Type: application/json');

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
    $courses = get_department_courses($pdo, $dept_id);

    echo json_encode([
        'success' => true,
        'courses' => $courses,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
