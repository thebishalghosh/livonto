<?php
// run_migration.php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/database.php';

try {
    $db = db();
    $sql = file_get_contents(__DIR__ . '/sql/migration_room_type_enum.sql');

    // Split by semicolon to run multiple statements if needed
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $db->execute($stmt);
            echo "Executed: " . substr($stmt, 0, 50) . "...\n";
        }
    }

    echo "Migration completed successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
