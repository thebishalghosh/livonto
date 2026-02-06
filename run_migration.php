<?php
// run_migration.php
// Safe Migration Runner for Production
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/database.php';

// Helper for colored output (CLI only)
function printColor($text, $color = 'white') {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'white' => "\033[0m",
        'reset' => "\033[0m"
    ];

    // Check if running in browser
    if (php_sapi_name() !== 'cli') {
        $htmlColors = [
            'green' => 'color: #28a745;',
            'red' => 'color: #dc3545;',
            'yellow' => 'color: #ffc107;',
            'blue' => 'color: #0d6efd;',
            'white' => 'color: #212529;',
            'reset' => ''
        ];
        echo "<span style=\"{$htmlColors[$color]}\">" . htmlspecialchars($text) . "</span>";
    } else {
        echo $colors[$color] . $text . $colors['reset'];
    }
}

function printLine($text = '', $color = 'white') {
    printColor($text, $color);
    if (php_sapi_name() !== 'cli') {
        echo "<br>";
    } else {
        echo "\n";
    }
}

// Start HTML output if in browser
if (php_sapi_name() !== 'cli') {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Database Migration</title>
        <style>
            body { font-family: monospace; background: #f8f9fa; padding: 2rem; line-height: 1.5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h1 { margin-top: 0; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; }
        </style>
    </head>
    <body>
    <div class="container">
    <h1>Database Migration Runner</h1>
    <pre>';
}

$db = db();
printLine("Starting Safe Migration...", 'blue');
printLine("----------------------------------------", 'white');

// ---------------------------------------------------------
// 1. Migration: Room Type Enum (migration_room_type_enum.sql)
// ---------------------------------------------------------
printLine("\n[1/4] Checking Room Type ENUM...", 'blue');
try {
    // Check if '4 sharing' is already in the ENUM
    $columnInfo = $db->fetchOne("SHOW COLUMNS FROM room_configurations LIKE 'room_type'");
    if ($columnInfo && strpos($columnInfo['Type'], "'4 sharing'") === false) {
        printLine("  ➜ Updating ENUM to include '4 sharing'...", 'yellow');
        $db->execute("ALTER TABLE room_configurations MODIFY COLUMN room_type ENUM('single sharing', 'double sharing', 'triple sharing', '4 sharing')");
        printLine("  ✓ Done.", 'green');
    } else {
        printLine("  ✓ Already up to date.", 'green');
    }
} catch (Exception $e) {
    printLine("  ✗ Error: " . $e->getMessage(), 'red');
}

// ---------------------------------------------------------
// 2. Migration: Security Deposit (migration_security_deposit.sql)
// ---------------------------------------------------------
printLine("\n[2/4] Checking Security Deposit Column...", 'blue');
try {
    $columnExists = $db->fetchValue("SHOW COLUMNS FROM listings LIKE 'security_deposit_months'");
    if (!$columnExists) {
        printLine("  ➜ Adding 'security_deposit_months' column...", 'yellow');
        $db->execute("ALTER TABLE listings ADD COLUMN security_deposit_months INT DEFAULT 1 AFTER security_deposit_amount");
        printLine("  ✓ Done.", 'green');
    } else {
        printLine("  ✓ Already exists.", 'green');
    }
} catch (Exception $e) {
    printLine("  ✗ Error: " . $e->getMessage(), 'red');
}

// ---------------------------------------------------------
// 3. Migration: Manual Override (migration_manual_override.sql)
// ---------------------------------------------------------
printLine("\n[3/4] Checking Manual Override Column...", 'blue');
try {
    $columnExists = $db->fetchValue("SHOW COLUMNS FROM room_configurations LIKE 'is_manual_availability'");
    if (!$columnExists) {
        printLine("  ➜ Adding 'is_manual_availability' column...", 'yellow');
        $db->execute("ALTER TABLE room_configurations ADD COLUMN is_manual_availability BOOLEAN DEFAULT FALSE");
        printLine("  ✓ Done.", 'green');
    } else {
        printLine("  ✓ Already exists.", 'green');
    }
} catch (Exception $e) {
    printLine("  ✗ Error: " . $e->getMessage(), 'red');
}

// ---------------------------------------------------------
// 4. Migration: Remove Unique Owner Email (migration_remove_unique_owner_email.sql)
// ---------------------------------------------------------
printLine("\n[4/4] Checking Owner Email Unique Constraint...", 'blue');
try {
    // Check if the index exists
    $indexExists = $db->fetchValue("SHOW INDEX FROM listings WHERE Key_name = 'unique_owner_email'");
    if ($indexExists) {
        printLine("  ➜ Removing UNIQUE constraint from owner_email...", 'yellow');
        $db->execute("ALTER TABLE listings DROP INDEX unique_owner_email");
        printLine("  ✓ Done.", 'green');
    } else {
        printLine("  ✓ Constraint already removed.", 'green');
    }
} catch (Exception $e) {
    printLine("  ✗ Error: " . $e->getMessage(), 'red');
}

printLine("\n----------------------------------------", 'white');
printLine("Migration Process Completed.", 'blue');

if (php_sapi_name() !== 'cli') {
    echo '</pre></div></body></html>';
}
?>
