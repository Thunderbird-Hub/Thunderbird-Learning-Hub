<?php
/**
 * Migration: Clean up training roles and set is_in_training flags
 * This script converts any "training" roles to "user" and sets is_in_training=1
 * Run once to clean up the database after role system simplification
 */

require_once __DIR__ . '/../includes/db_connect.php';

echo "<h2>🧹 Cleaning up Training Roles</h2>";

try {
    // Step 1: Convert any "training" roles to "user" and set training flag
    $stmt = $pdo->prepare("
        UPDATE users
        SET role = 'user', is_in_training = 1
        WHERE LOWER(role) = 'training'
    ");
    $result = $stmt->execute();
    $affected = $stmt->rowCount();

    echo "<p>✅ Converted $affected users from 'training' role to 'user' role with is_in_training=1</p>";

    // Step 2: Verify the changes
    $stmt = $pdo->query("
        SELECT role, COUNT(*) as count, SUM(is_in_training) as training_count
        FROM users
        GROUP BY role
        ORDER BY role
    ");

    echo "<h3>📊 Current Role Distribution:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th>Role</th><th>Count</th><th>In Training</th></tr>";

    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>" . $row['training_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Step 3: Check for users with training assignments
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as users_with_assignments
        FROM users u
        JOIN user_training_assignments uta ON u.id = uta.user_id
        WHERE u.is_in_training = 0
    ");

    $result = $stmt->fetch();
    if ($result['users_with_assignments'] > 0) {
        echo "<p>⚠️ Found {$result['users_with_assignments']} users with training assignments but is_in_training=0. Fixing...</p>";

        $stmt = $pdo->prepare("
            UPDATE users u
            SET is_in_training = 1
            WHERE EXISTS (
                SELECT 1 FROM user_training_assignments uta
                WHERE uta.user_id = u.id
            )
            AND u.is_in_training = 0
        ");
        $stmt->execute();
        $fixed = $stmt->rowCount();
        echo "<p>✅ Fixed $fixed users by setting is_in_training=1</p>";
    }

    echo "<h3>✅ Migration Complete!</h3>";
    echo "<p>The role system now uses only 3 roles: user, admin, super_admin</p>";
    echo "<p>Training status is determined by the is_in_training flag</p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>