<?php
// db_test.php
// Upload this to your server root to debug Database Connection

echo "<h1>Database Connection Debugger</h1>";

// 1. Load Config
echo "<h3>1. Loading Configuration</h3>";
try {
    require_once __DIR__ . '/app/config.php';
    echo "<p style='color:green'>Config loaded successfully.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error loading config: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Check Environment Variables
echo "<h3>2. Checking Environment Variables</h3>";
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'livonto_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS'); // Don't print this!

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><td>DB_HOST</td><td>" . htmlspecialchars($host) . "</td></tr>";
echo "<tr><td>DB_NAME</td><td>" . htmlspecialchars($dbname) . "</td></tr>";
echo "<tr><td>DB_USER</td><td>" . htmlspecialchars($user) . "</td></tr>";
echo "<tr><td>DB_PASS</td><td>" . (empty($pass) ? '<em>(Empty)</em>' : '******') . "</td></tr>";
echo "</table>";

// 3. Attempt Connection
echo "<h3>3. Testing Connection</h3>";

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $start = microtime(true);
    $pdo = new PDO($dsn, $user, $pass, $options);
    $end = microtime(true);

    echo "<p style='color:green; font-weight:bold;'>✅ Connection Successful!</p>";
    echo "<p>Time taken: " . round(($end - $start) * 1000, 2) . " ms</p>";

    // 4. Test Query
    echo "<h3>4. Testing Query</h3>";
    $stmt = $pdo->query("SELECT VERSION()");
    $version = $stmt->fetchColumn();
    echo "<p>MySQL Version: <strong>" . htmlspecialchars($version) . "</strong></p>";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: " . count($tables) . "</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ Connection Failed!</p>";
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border:1px solid #f5c6cb; border-radius:5px;'>";
    echo "<strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
    echo "<strong>Common Causes:</strong>";
    echo "<ul>";
    echo "<li>Incorrect password</li>";
    echo "<li>Database user does not have permission</li>";
    echo "<li>Database name does not exist</li>";
    echo "<li>MySQL server is not running</li>";
    echo "</ul>";
    echo "</div>";
}
?>
