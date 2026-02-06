<?php
// debug_db.php
// Run this on the server to diagnose the database error

require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Debugger</h1>";

try {
    $db = db();
    echo "<p style='color:green'>✓ Database connection successful.</p>";

    // 1. Check Table Schema
    echo "<h3>1. Schema Check: 'listings' table</h3>";
    $columns = $db->fetchAll("DESCRIBE listings");

    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";

    $hasOwnerEmail = false;
    $hasOwnerPass = false;

    foreach ($columns as $col) {
        $bg = '';
        if ($col['Field'] === 'owner_email') {
            $hasOwnerEmail = true;
            $bg = 'background:#e6fffa';
        }
        if ($col['Field'] === 'owner_password_hash') {
            $hasOwnerPass = true;
            $bg = 'background:#e6fffa';
        }

        echo "<tr style='$bg'>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if (!$hasOwnerEmail) echo "<p style='color:red'>✗ Missing column: owner_email</p>";
    if (!$hasOwnerPass) echo "<p style='color:red'>✗ Missing column: owner_password_hash</p>";

    // 2. Test Update Query (Dry Run)
    echo "<h3>2. Test Update Query</h3>";

    // Get a valid ID
    $id = $db->fetchValue("SELECT id FROM listings LIMIT 1");

    if ($id) {
        echo "<p>Testing update on Listing ID: $id</p>";

        $sql = "UPDATE listings SET
                title = ?,
                owner_email = ?,
                owner_password_hash = ?
                WHERE id = ?";

        $params = [
            'Test Title ' . time(),
            'test' . time() . '@example.com',
            password_hash('password', PASSWORD_DEFAULT),
            $id
        ];

        echo "<pre>SQL: $sql</pre>";
        echo "<pre>Params: " . print_r($params, true) . "</pre>";

        try {
            // We wrap in transaction and rollback so we don't actually change data
            $db->beginTransaction();
            $db->execute($sql, $params);
            echo "<p style='color:green'>✓ Query executed successfully (Rolled back).</p>";
            $db->rollBack();
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ Query Failed: " . $e->getMessage() . "</p>";
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
        }
    } else {
        echo "<p style='color:orange'>No listings found to test update.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Critical Error: " . $e->getMessage() . "</p>";
}
?>
