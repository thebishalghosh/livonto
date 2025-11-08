<?php
// Root index.php - Router for clean URLs
require __DIR__ . '/app/config.php';

// Get the requested URL
$requestUri = $_SERVER['REQUEST_URI'];
$baseUrl = app_url('');

// Check if URL is passed as query parameter (from .htaccess rewrite)
if (isset($_GET['url']) && !empty($_GET['url'])) {
    $path = $_GET['url'];
} else {
    // Remove base URL from request URI
    $path = str_replace($baseUrl, '', $requestUri);
    $path = parse_url($path, PHP_URL_PATH);
}

$path = trim($path, '/');

// Fix double slashes
$path = preg_replace('#/+#', '/', $path);
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
    'refer' => 'public/refer.php',
];

// Admin routes
$adminRoutes = [
    'admin' => 'admin/index.php',
    'admin/dashboard' => 'admin/index.php',
    'admin/login' => 'admin/login.php',
    'admin/listings' => 'admin/listing_manage.php',
    'admin/listings/add' => 'admin/listing_add.php',
    'admin/users' => 'admin/users_manage.php',
    'admin/amenities' => 'admin/amenities_manage.php',
    'admin/house-rules' => 'admin/house_rules_manage.php',
    'admin/referrals' => 'admin/referrals_manage.php',
];

// Check if it's an admin route
if (strpos($path, 'admin/') === 0 || $path === 'admin') {
    // Handle admin routes
    if (isset($adminRoutes[$path])) {
        $file = __DIR__ . '/' . $adminRoutes[$path];
        if (file_exists($file)) {
            require $file;
            exit;
        }
    }
    // Try to find admin file directly
    $adminPath = str_replace('admin/', '', $path);
    if ($adminPath === 'admin') {
        $adminPath = 'index';
    }
    $adminFile = __DIR__ . '/admin/' . $adminPath . '.php';
    if (file_exists($adminFile)) {
        require $adminFile;
        exit;
    }
}

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

