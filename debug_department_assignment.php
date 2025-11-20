<?php
/**
 * Debug script to check department assignments and training flag issues
 * Run this script to diagnose why the training flag isn't being set
 */

require_once __DIR__ . '/includes/db_connect.php';

echo "<h2>🔍 Department Assignment Debug</h2>";

// Step 1: Check if Test 1 user exists and their current state
echo "<h3>User 'Test 1' Current State:</h3>";
try {
    $stmt = $pdo->prepare("SELECT id, name, role, is_in_training FROM users WHERE name LIKE ?");
    $stmt->execute(['%Test 1%']);
    $user = $stmt->fetch();

    if ($user) {
        echo "<pre>";
        echo "ID: " . $user['id'] . "\n";
        echo "Name: " . $user['name'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "is_in_training: " . $user['is_in_training'] . "\n";
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>❌ User 'Test 1' not found</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Step 2: Check departments
echo "<h3>All Departments:</h3>";
try {
    $stmt = $pdo->query("SELECT id, name, member_count, course_count FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Member Count</th><th>Course Count</th></tr>";
    foreach ($departments as $dept) {
        echo "<tr>";
        echo "<td>" . $dept['id'] . "</td>";
        echo "<td>" . htmlspecialchars($dept['name']) . "</td>";
        echo "<td>" . $dept['member_count'] . "</td>";
        echo "<td>" . $dept['course_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Step 3: Check user_department assignments
echo "<h3>User-Department Assignments for 'Test 1':</h3>";
if (isset($user)) {
    try {
        $stmt = $pdo->prepare("
            SELECT ud.*, d.name as department_name
            FROM user_departments ud
            JOIN departments d ON ud.department_id = d.id
            WHERE ud.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($assignments) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>User ID</th><th>Department ID</th><th>Department Name</th><th>Assigned Date</th></tr>";
            foreach ($assignments as $assignment) {
                echo "<tr>";
                echo "<td>" . $assignment['user_id'] . "</td>";
                echo "<td>" . $assignment['department_id'] . "</td>";
                echo "<td>" . htmlspecialchars($assignment['department_name']) . "</td>";
                echo "<td>" . $assignment['assigned_date'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ No department assignments found for Test 1</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

// Step 4: Check course_departments relationships
echo "<h3>Course-Department Relationships:</h3>";
try {
    $stmt = $pdo->query("
        SELECT cd.*, tc.name as course_name, d.name as department_name
        FROM course_departments cd
        JOIN training_courses tc ON cd.course_id = tc.id
        JOIN departments d ON cd.department_id = d.id
        ORDER BY d.name, tc.name
    ");
    $relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($relationships) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Course ID</th><th>Course Name</th><th>Department ID</th><th>Department Name</th><th>Assigned Date</th></tr>";
        foreach ($relationships as $rel) {
            echo "<tr>";
            echo "<td>" . $rel['course_id'] . "</td>";
            echo "<td>" . htmlspecialchars($rel['course_name']) . "</td>";
            echo "<td>" . $rel['department_id'] . "</td>";
            echo "<td>" . htmlspecialchars($rel['department_name']) . "</td>";
            echo "<td>" . $rel['assigned_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ No course-department relationships found</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Step 5: Check Induction course details
echo "<h3>'Induction' Course Details:</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM training_courses WHERE name LIKE ?");
    $stmt->execute(['%Induction%']);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($course) {
        echo "<pre>";
        echo "ID: " . $course['id'] . "\n";
        echo "Name: " . $course['name'] . "\n";
        echo "Department (legacy field): " . $course['department'] . "\n";
        echo "Description: " . $course['description'] . "\n";
        echo "Is Active: " . ($course['is_active'] ? 'Yes' : 'No') . "\n";
        echo "</pre>";

        // Check if this course is in course_departments table
        $stmt2 = $pdo->prepare("SELECT * FROM course_departments WHERE course_id = ?");
        $stmt2->execute([$course['id']]);
        $dept_links = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        if ($dept_links) {
            echo "<h4>Course Department Links:</h4>";
            echo "<pre>";
            foreach ($dept_links as $link) {
                echo "Department ID: " . $link['department_id'] . "\n";
                echo "Assigned Date: " . $link['assigned_date'] . "\n";
            }
            echo "</pre>";
        } else {
            echo "<p style='color: orange;'>⚠️ No department links found for Induction course</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ 'Induction' course not found</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Step 6: Check training assignments for Test 1
echo "<h3>Training Assignments for 'Test 1':</h3>";
if (isset($user)) {
    try {
        $stmt = $pdo->prepare("
            SELECT uta.*, tc.name as course_name, d.name as department_name
            FROM user_training_assignments uta
            JOIN training_courses tc ON uta.course_id = tc.id
            LEFT JOIN course_departments cd ON tc.id = cd.course_id
            LEFT JOIN departments d ON cd.department_id = d.id
            WHERE uta.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($assignments) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Assignment ID</th><th>Course Name</th><th>Status</th><th>Assigned Date</th><th>Department</th></tr>";
            foreach ($assignments as $assignment) {
                echo "<tr>";
                echo "<td>" . $assignment['id'] . "</td>";
                echo "<td>" . htmlspecialchars($assignment['course_name']) . "</td>";
                echo "<td>" . $assignment['status'] . "</td>";
                echo "<td>" . $assignment['assigned_date'] . "</td>";
                echo "<td>" . htmlspecialchars($assignment['department_name'] ?? 'None') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ No training assignments found for Test 1</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Run the migration: <code>mysql -u username -p database_name < migrations/fix_course_department_relationships.sql</code></li>";
echo "<li>Check the course-department relationships in the table above</li>";
echo "<li>Verify Test 1 is assigned to the correct department</li>";
echo "<li>Check error logs for debugging messages</li>";
echo "</ol>";
?>