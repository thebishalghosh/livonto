<?php
/**
 * Password Reset Migration
 * Adds password reset token columns to users table
 */

require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/functions.php';

try {
    $db = db();
    
    // Check if columns already exist
    $columns = $db->fetchAll("SHOW COLUMNS FROM users LIKE 'password_reset%'");
    
    if (count($columns) >= 2) {
        echo "Password reset columns already exist.\n";
        exit(0);
    }
    
    // Add columns
    $db->execute("ALTER TABLE users 
                  ADD COLUMN password_reset_token VARCHAR(255) NULL AFTER password_hash,
                  ADD COLUMN password_reset_expires DATETIME NULL AFTER password_reset_token,
                  ADD INDEX idx_password_reset_token (password_reset_token)");
    
    echo "Migration successful! Password reset columns added.\n";
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

