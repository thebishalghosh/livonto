<?php
// debug_url.php
// Upload this to your server root to debug URL and Rewrite issues

echo "<h1>Livonto URL Debugger</h1>";

// 1. Check .htaccess Rewrite
// We check if a special query param is passed. If accessed via /debug-rewrite, it should work if .htaccess is active.
$isRewrite = isset($_GET['url']) ? 'YES' : 'NO';
echo "<h3>1. Rewrite Engine Check</h3>";
echo "Is .htaccess routing working? <strong>" . $isRewrite . "</strong><br>";
echo "<small>(If you accessed this file directly as debug_url.php, this should be NO. If you accessed it via a rewritten URL, it might be YES)</small><br>";

// 2. Load Config to see detection
echo "<h3>2. Base URL Detection</h3>";
require_once __DIR__ . '/app/config.php';
// app/config.php returns the config array, but also sets $baseUrl variable internally if we are in the same scope
// Let's re-run the detection logic here to see what it finds
$detectedBaseUrl = getenv('LIVONTO_BASE_URL');
echo "Environment LIVONTO_BASE_URL: " . var_export($detectedBaseUrl, true) . "<br>";

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$projectRoot = __DIR__;

echo "<h3>3. Server Variables</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><td>DOCUMENT_ROOT</td><td>" . htmlspecialchars($documentRoot) . "</td></tr>";
echo "<tr><td>Project Root (__DIR__)</td><td>" . htmlspecialchars($projectRoot) . "</td></tr>";
echo "<tr><td>SCRIPT_NAME</td><td>" . htmlspecialchars($scriptName) . "</td></tr>";
echo "<tr><td>REQUEST_URI</td><td>" . htmlspecialchars($requestUri) . "</td></tr>";
echo "<tr><td>HTTP_HOST</td><td>" . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . "</td></tr>";
echo "</table>";

// 4. Test app_url
echo "<h3>4. app_url() Output</h3>";
echo "app_url('listings'): <strong>" . app_url('listings') . "</strong><br>";
echo "app_url(''): <strong>" . app_url('') . "</strong><br>";

echo "<h3>5. Diagnosis</h3>";
if (strpos($projectRoot, $documentRoot) !== 0) {
    echo "<p style='color: red;'><strong>Warning:</strong> Project root is NOT inside Document Root. This might cause path issues.</p>";
} else {
    echo "<p style='color: green;'>Project root is inside Document Root.</p>";
}

if (empty($detectedBaseUrl)) {
    echo "<p>Auto-detection is active. Based on the above, the system thinks your base URL is: <strong>" . (empty($baseUrl) ? '/' : $baseUrl) . "</strong></p>";
} else {
    echo "<p>Using forced Base URL from environment.</p>";
}
?>
