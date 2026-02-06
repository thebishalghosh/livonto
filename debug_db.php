<?php
// debug_db.php
// Run this on the server to diagnose the database error

require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Debugger - Phase 2</h1>";

try {
    $db = db();
    echo "<p style='color:green'>✓ Database connection successful.</p>";

    // 1. Check Indexes
    echo "<h3>1. Index Check: 'listings' table</h3>";
    $indexes = $db->fetchAll("SHOW INDEX FROM listings");

    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Non_unique</th></tr>";

    $emailUnique = false;

    foreach ($indexes as $idx) {
        $bg = '';
        if ($idx['Column_name'] === 'owner_email') {
            $emailUnique = ($idx['Non_unique'] == 0);
            $bg = 'background:#e6fffa';
        }

        echo "<tr style='$bg'>";
        echo "<td>" . htmlspecialchars($idx['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($idx['Column_name']) . "</td>";
        echo "<td>" . htmlspecialchars($idx['Non_unique']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if ($emailUnique) {
        echo "<p style='color:orange'>ℹ 'owner_email' has a UNIQUE constraint.</p>";
    }

    // 2. Test Duplicate Email Update
    echo "<h3>2. Test Duplicate Email Update</h3>";

    // Get two different listings
    $listings = $db->fetchAll("SELECT id, owner_email FROM listings LIMIT 2");

    if (count($listings) >= 2) {
        $id1 = $listings[0]['id'];
        $email1 = $listings[0]['owner_email'];
        $id2 = $listings[1]['id'];

        // If first listing has no email, give it one for the test
        if (empty($email1)) {
            $email1 = 'test_dup_' . time() . '@example.com';
            // We can't easily set this up without modifying data, so we'll simulate the conflict
            // by trying to set ID2's email to a known existing email if possible,
            // or just warn that we can't fully test without existing data.
             echo "<p>Listing #$id1 has no email. Using fake email '$email1' for simulation.</p>";
        } else {
             echo "<p>Listing #$id1 has email: <strong>$email1</strong></p>";
        }

        echo "<p>Attempting to update Listing #$id2 with email: <strong>$email1</strong></p>";

        $sql = "UPDATE listings SET owner_email = ? WHERE id = ?";
        $params = [$email1, $id2];

        try {
            $db->beginTransaction();
            // We intentionally try to cause a conflict here if the email exists
            // If email1 was fake/empty, this might pass, but if it was real, it should fail
            $db->execute($sql, $params);

            echo "<p style='color:green'>✓ Query executed successfully (No duplicate conflict found).</p>";
            $db->rollBack();
        } catch (Exception $e) {
            echo "<p style='color:red'>✗ Query Failed (Expected if Unique): " . $e->getMessage() . "</p>";
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
        }
    } else {
        echo "<p style='color:orange'>Not enough listings to test duplicate constraint.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Critical Error: " . $e->getMessage() . "</p>";
}
?>
