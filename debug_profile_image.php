<?php
// debug_profile_image.php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    die("Please login as admin first.");
}

$adminId = $_SESSION['user_id'];
$db = db();
$admin = $db->fetchOne("SELECT id, name, profile_image FROM users WHERE id = ?", [$adminId]);

echo "<h1>Profile Image Debugger</h1>";
echo "Admin ID: " . $admin['id'] . "<br>";
echo "Name: " . htmlspecialchars($admin['name']) . "<br>";
echo "Raw DB Value: " . htmlspecialchars(var_export($admin['profile_image'], true)) . "<br>";

if (!empty($admin['profile_image'])) {
    $imagePath = $admin['profile_image'];

    if (strpos($imagePath, 'http') === 0) {
        echo "Type: External URL<br>";
        echo "URL: <a href='$imagePath' target='_blank'>$imagePath</a><br>";
    } else {
        echo "Type: Local File<br>";
        $fullPath = __DIR__ . '/' . ltrim($imagePath, '/');
        echo "Full Server Path: " . $fullPath . "<br>";

        if (file_exists($fullPath)) {
            echo "File Exists: <span style='color:green'>YES</span><br>";
            echo "Permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4) . "<br>";
        } else {
            echo "File Exists: <span style='color:red'>NO</span><br>";
        }

        $url = app_url($imagePath);
        echo "Generated URL: <a href='$url' target='_blank'>$url</a><br>";
    }
} else {
    echo "No profile image set.<br>";
}
?>
