<?php
// Root index.php - Router for clean URLs
require __DIR__ . '/app/config.php';

// Get the requested URL
$requestUri = $_SERVER['REQUEST_URI'];
$baseUrl = app_url('');

// Remove base URL from request URI
$path = str_replace($baseUrl, '', $requestUri);
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');

// Remove query string for routing
$path = explode('?', $path)[0];

// Default route
if (empty($path) || $path === 'index.php') {
    $path = 'index';
}

// Map of clean URLs to actual PHP files
$routes = [
    'index' => 'public/index.php',
    'listings' => 'public/listings.php',
    'about' => 'public/about.php',
    'contact' => 'public/contact.php',
    'profile' => 'public/profile.php',
    'login' => 'public/login.php',
    'logout' => 'public/logout.php',
    'register' => 'public/register.php',
    'password-reset' => 'public/password_reset.php',
];

// Check if route exists
if (isset($routes[$path])) {
    $file = __DIR__ . '/' . $routes[$path];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// If route not found, try to find file in public directory
$publicFile = __DIR__ . '/public/' . $path . '.php';
if (file_exists($publicFile)) {
    require $publicFile;
    exit;
}

// 404 - Page not found
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="text-center">
            <h1 class="display-1">404</h1>
            <p class="lead">Page not found</p>
            <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-primary">Go Home</a>
        </div>
    </div>
</body>
</html>

