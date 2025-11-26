<?php
/**
 * Department Assignment System Diagnostic Script
 *
 * This script will test the complete department-based training assignment workflow
 * to identify where the system is failing according to the user's requirements:
 * 1. User is assigned to a department
 * 2. User automatically inherits all training courses assigned to that department
 * 3. User's is_in_training flag is set to 1, enabling training access
 * 4. User role is changed to 'training' (except for admins/super admins)
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "<h1>🔍 Department Assignment System Diagnostic</h1>\n";

// Test 1: Database Structure Verification
echo "<h2>📋 Test 1: Database Structure Verification</h2>\n";

$required_tables = [
    'departments',
    'user_departments',
    'course_departments',
    'user_training_assignments',
    'training_courses',
    'users'
];

$all_tables_exist = true;

foreach ($required_tables as $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "✅ Table '$table' exists<br>\n";
    } catch (PDOException $e) {
        echo "❌ Table '$table' MISSING: " . $e->getMessage() . "<br>\n";
        $all_tables_exist = false;
    }
}

// Test specific columns
if ($all_tables_exist) {
    echo "<br><strong>Checking critical columns:</strong><br>\n";

    // Check users.is_in_training column
    try {
        $stmt = $pdo->query("SELECT is_in_training FROM users LIMIT 1");
        echo "✅ users.is_in_training column exists<br>\n";
    } catch (PDOException $e) {
        echo "❌ users.is_in_training column MISSING<br>\n";
    }

    // Check user_training_assignments.assignment_source column
    try {
        $stmt = $pdo->query("SELECT assignment_source FROM user_training_assignments LIMIT 1");
        echo "✅ user_training_assignments.assignment_source column exists<br>\n";
    } catch (PDOException $e) {
        echo "❌ user_training_assignments.assignment_source column MISSING<br>\n";
    }

    // Check user_training_assignments.department_id column
    try {
        $stmt = $pdo->query("SELECT department_id FROM user_training_assignments LIMIT 1");
        echo "✅ user_training_assignments.department_id column exists<br>\n";
    } catch (PDOException $e) {
        echo "❌ user_training_assignments.department_id column MISSING<br>\n";
    }
}

// Test 2: Sample Data Verification
echo "<h2>📊 Test 2: Sample Data Verification</h2>\n";

if ($all_tables_exist) {
    // Check departments
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
        $dept_count = $stmt->fetch()['count'];
        echo "📁 Found $dept_count departments in database<br>\n";

        if ($dept_count > 0) {
            $stmt = $pdo->query("SELECT id, name FROM departments LIMIT 3");
            $depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($depts as $dept) {
                echo "  - Department ID {$dept['id']}: {$dept['name']}<br>\n";
            }
        }
    } catch (PDOException $e) {
        echo "❌ Error checking departments: " . $e->getMessage() . "<br>\n";
    }

    // Check training courses
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM training_courses WHERE is_active = 1");
        $course_count = $stmt->fetch()['count'];
        echo "📚 Found $course_count active training courses<br>\n";

        if ($course_count > 0) {
            $stmt = $pdo->query("SELECT id, name, department FROM training_courses WHERE is_active = 1 LIMIT 3");
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($courses as $course) {
                echo "  - Course ID {$course['id']}: {$course['name']} (dept: " . ($course['department'] ?? 'None') . ")<br>\n";
            }
        }
    } catch (PDOException $e) {
        echo "❌ Error checking courses: " . $e->getMessage() . "<br>\n";
    }

    // Check course-department assignments
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM course_departments");
        $course_dept_count = $stmt->fetch()['count'];
        echo "🔗 Found $course_dept_count course-department assignments<br>\n";

        if ($course_dept_count > 0) {
            $stmt = $pdo->query("
                SELECT cd.course_id, cd.department_id, tc.name as course_name, d.name as dept_name
                FROM course_departments cd
                LEFT JOIN training_courses tc ON cd.course_id = tc.id
                LEFT JOIN departments d ON cd.department_id = d.id
                LIMIT 5
            ");
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($assignments as $assignment) {
                echo "  - Course '{$assignment['course_name']}' -> Department '{$assignment['dept_name']}'<br>\n";
            }
        }
    } catch (PDOException $e) {
        echo "❌ Error checking course-department assignments: " . $e->getMessage() . "<br>\n";
    }

    // Check users
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
        $user_count = $stmt->fetch()['count'];
        echo "👥 Found $user_count active users<br>\n";

        // Check users in training
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND is_in_training = 1");
        $training_count = $stmt->fetch()['count'];
        echo "  - $training_count users currently in training<br>\n";
    } catch (PDOException $e) {
        echo "❌ Error checking users: " . $e->getMessage() . "<br>\n";
    }
}

// Test 3: Manual Function Testing
echo "<h2>🧪 Test 3: Manual Function Testing</h2>\n";

if ($all_tables_exist) {
    // Find a test department and course
    $test_dept_id = null;
    $test_course_id = null;
    $test_user_id = null;

    try {
        // Get first department
        $stmt = $pdo->query("SELECT id, name FROM departments LIMIT 1");
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dept) {
            $test_dept_id = $dept['id'];
            echo "📁 Using test department: {$dept['name']} (ID: $test_dept_id)<br>\n";
        }

        // Get first active course
        $stmt = $pdo->query("SELECT id, name FROM training_courses WHERE is_active = 1 LIMIT 1");
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($course) {
            $test_course_id = $course['id'];
            echo "📚 Using test course: {$course['name']} (ID: $test_course_id)<br>\n";
        }

        // Get first active user (not admin)
        $stmt = $pdo->query("SELECT id, name, role, is_in_training FROM users WHERE is_active = 1 AND role NOT IN ('admin', 'super_admin') LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $test_user_id = $user['id'];
            echo "👥 Using test user: {$user['name']} (Role: {$user['role']}, In Training: " . ($user['is_in_training'] ? 'Yes' : 'No') . ")<br>\n";
        }
    } catch (PDOException $e) {
        echo "❌ Error getting test data: " . $e->getMessage() . "<br>\n";
    }

    if ($test_dept_id && $test_course_id && $test_user_id) {
        echo "<br><strong>Testing function chain:</strong><br>\n";

        try {
            // Test 1: Assign course to department
            echo "1️⃣ Testing assign_course_to_department()...<br>\n";
            if (!function_exists('assign_course_to_department')) {
                require_once __DIR__ . '/includes/department_helpers.php';
            }
            $result = assign_course_to_department($pdo, $test_course_id, $test_dept_id, 1); // Assume user ID 1 is admin
            echo $result ? "✅ assign_course_to_department() succeeded<br>\n" : "❌ assign_course_to_department() failed<br>\n";

            // Test 2: Assign user to department
            echo "2️⃣ Testing assign_user_to_department()...<br>\n";
            $result = assign_user_to_department($pdo, $test_user_id, $test_dept_id, 1);
            echo $result ? "✅ assign_user_to_department() succeeded<br>\n" : "❌ assign_user_to_department() failed<br>\n";

            // Test 3: Assign user to department courses
            echo "3️⃣ Testing assign_user_to_department_courses()...<br>\n";
            $assigned_count = assign_user_to_department_courses($pdo, $test_user_id, $test_dept_id, 1);
            echo "📊 assign_user_to_department_courses() assigned $assigned_count courses<br>\n";

            // Test 4: Check if user got training flag
            echo "4️⃣ Checking training flag status...<br>\n";
            $stmt = $pdo->prepare("SELECT is_in_training, role FROM users WHERE id = ?");
            $stmt->execute([$test_user_id]);
            $user_after = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "🚩 User training flag: " . ($user_after['is_in_training'] ? 'YES' : 'NO') . "<br>\n";
            echo "👔 User role: " . $user_after['role'] . "<br>\n";

            // Test 5: Check training assignments
            echo "5️⃣ Checking training assignments...<br>\n";
            $stmt = $pdo->prepare("
                SELECT uta.*, tc.name as course_name, d.name as dept_name
                FROM user_training_assignments uta
                LEFT JOIN training_courses tc ON uta.course_id = tc.id
                LEFT JOIN departments d ON uta.department_id = d.id
                WHERE uta.user_id = ?
            ");
            $stmt->execute([$test_user_id]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($assignments)) {
                echo "❌ No training assignments found for user<br>\n";
            } else {
                echo "✅ Found " . count($assignments) . " training assignments:<br>\n";
                foreach ($assignments as $assignment) {
                    echo "  - Course: {$assignment['course_name']} (Status: {$assignment['status']}, Source: {$assignment['assignment_source']}, Dept: " . ($assignment['dept_name'] ?? 'None') . ")<br>\n";
                }
            }

        } catch (Exception $e) {
            echo "❌ Error during function testing: " . $e->getMessage() . "<br>\n";
        }
    } else {
        echo "⚠️ Cannot run function tests - missing test data (need department, course, and non-admin user)<br>\n";
    }
}

// Test 4: Debug Log Analysis
echo "<h2>📋 Test 4: Debug Log Analysis</h2>\n";

$debug_log_file = __DIR__ . '/includes/assignment_debug.log';
if (file_exists($debug_log_file)) {
    $log_content = file_get_contents($debug_log_file);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -20); // Last 20 lines

    echo "<strong>Recent debug entries (last 20 lines):</strong><br>\n";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; font-size: 12px; max-height: 300px; overflow-y: auto;'>";
    foreach ($recent_lines as $line) {
        if (!empty(trim($line))) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "ℹ️ No debug log file found at: $debug_log_file<br>\n";
}

// Test 5: Verify User Workflow (Specific to user's reported issue)
echo "<h2>👤 Test 5: User-Specific Workflow Test</h2>\n";

// Look for users who might be experiencing the issue
try {
    $stmt = $pdo->query("
        SELECT u.id, u.name, u.role, u.is_in_training,
               COUNT(ud.user_id) as dept_assignments,
               COUNT(uta.id) as training_assignments
        FROM users u
        LEFT JOIN user_departments ud ON u.id = ud.user_id
        LEFT JOIN user_training_assignments uta ON u.id = uta.user_id
        WHERE u.is_active = 1 AND u.role NOT IN ('admin', 'super_admin')
        GROUP BY u.id
        ORDER BY u.name
        LIMIT 10
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($users) {
        echo "<strong>User Analysis (looking for potential issues):</strong><br>\n";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Name</th><th>Role</th><th>In Training</th><th>Dept Assignments</th><th>Training Assignments</th><th>Status</th></tr>";

        foreach ($users as $user) {
            $status = "✅ OK";
            if ($user['dept_assignments'] > 0 && $user['training_assignments'] == 0) {
                $status = "❌ PROBLEM: Has department but no training";
            } elseif ($user['dept_assignments'] > 0 && $user['is_in_training'] == 0) {
                $status = "❌ PROBLEM: Has department but training flag not set";
            } elseif ($user['training_assignments'] > 0 && $user['is_in_training'] == 0) {
                $status = "❌ PROBLEM: Has training assignments but flag not set";
            }

            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['name']) . "</td>";
            echo "<td>" . $user['role'] . "</td>";
            echo "<td>" . ($user['is_in_training'] ? 'YES' : 'NO') . "</td>";
            echo "<td>" . $user['dept_assignments'] . "</td>";
            echo "<td>" . $user['training_assignments'] . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "❌ Error analyzing users: " . $e->getMessage() . "<br>\n";
}

echo "<h2>✅ Diagnostic Complete</h2>\n";
echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>📝 Summary and Recommendations:</h3>";
echo "<ol>";
echo "<li><strong>Database Structure:</strong> " . ($all_tables_exist ? "✅ All required tables exist" : "❌ Missing tables - run migrations") . "</li>";
echo "<li><strong>Expected Workflow:</strong> User → Department → Course Assignment → Training Flag → Training Access</li>";
echo "<li><strong>If issues found:</strong> Check the debug log above and look for database errors</li>";
echo "<li><strong>Common fixes:</strong> Run migration files, verify course-department assignments, check error logs</li>";
echo "</ol>";
echo "</div>";
echo "<p><small>Run this script whenever the department assignment system is not working as expected.</small></p>\n";
?>