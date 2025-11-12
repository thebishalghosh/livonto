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

// Remove query string for routing (but keep it in $_GET via QSA)
$path = explode('?', $path)[0];

// Default route
if (empty($path) || $path === 'index.php') {
    $path = 'index';
}

// Check for listing detail route (listings/{id})
if (preg_match('#^listings/(\d+)$#', $path, $matches)) {
    $listingId = intval($matches[1]);
    $_GET['id'] = $listingId;
    require __DIR__ . '/public/listing_detail.php';
    exit;
}

// Map of clean URLs to actual PHP files
$routes = [
    'index' => 'public/index.php',
    'listings' => 'public/listings.php',
    'about' => 'public/about.php',
    'contact' => 'public/contact.php',
    'profile' => 'public/profile.php',
    'login' => 'public/login.php',
    'login-action' => 'app/login_action.php',
    'logout' => 'public/logout.php',
    'register' => 'public/register.php',
    'forgot-password' => 'public/forgot-password.php',
    'reset-password' => 'public/reset-password.php',
    'password-reset' => 'public/password_reset.php', // Keep for backward compatibility
    'refer' => 'public/refer.php',
    'visit-book' => 'public/visit_book.php',
    'visit-book-api' => 'app/visit_book_api.php',
    'book' => 'public/book.php',
    'book-api' => 'app/book_api.php',
    'payment' => 'public/payment.php',
    'razorpay-api' => 'app/razorpay_api.php',
    'razorpay-callback' => 'app/razorpay_callback.php',
    'google-auth-callback' => 'app/google_auth_callback.php',
    'invoice' => 'public/invoice.php',
    'invoice-api' => 'app/invoice_api.php',
    'change-password-api' => 'app/change_password_api.php',
];

// Owner routes
$ownerRoutes = [
    'owner/login' => 'owner/login.php',
    'owner/logout' => 'owner/logout.php',
    'owner/dashboard' => 'owner/dashboard.php',
    'owner/listings/edit' => 'owner/listings/edit.php',
    'owner/forgot-password' => 'owner/forgot-password.php',
    'owner/reset-password' => 'owner/reset-password.php',
    'owner/change-password-api' => 'app/owner_change_password_api.php',
];

// Admin routes
$adminRoutes = [
    'admin' => 'admin/index.php',
    'admin/dashboard' => 'admin/index.php',
    'admin/login' => 'admin/login.php',
    'admin/listings' => 'admin/listing_manage.php',
    'admin/listings/add' => 'admin/listing_add.php',
    'admin/listings/edit' => 'admin/listing_edit.php',
    'admin/listings/view' => 'admin/listing_view.php',
    'admin/listings/delete' => 'admin/listing_delete.php',
    'admin/users' => 'admin/users_manage.php',
    'admin/users/view' => 'admin/user_view.php',
    'admin/amenities' => 'admin/amenities_manage.php',
    'admin/house-rules' => 'admin/house_rules_manage.php',
    'admin/referrals' => 'admin/referrals_manage.php',
    'admin/enquiries' => 'admin/enquiries_manage.php',
    'admin/visit-bookings' => 'admin/visit_bookings_manage.php',
    'admin/bookings' => 'admin/bookings_manage.php',
    'admin/payments' => 'admin/payments_manage.php',
    'admin/settings' => 'admin/settings.php',
];

// Check if it's an owner route
if (strpos($path, 'owner/') === 0 || $path === 'owner') {
    // Handle root owner route - redirect to login or dashboard
    if ($path === 'owner' || $path === 'owner/') {
        // Check if owner is logged in
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['owner_logged_in']) && $_SESSION['owner_logged_in'] === true) {
            header('Location: ' . app_url('owner/dashboard'));
            exit;
        } else {
            header('Location: ' . app_url('owner/login'));
            exit;
        }
    }
    
    // Handle owner routes
    if (isset($ownerRoutes[$path])) {
        $file = __DIR__ . '/' . $ownerRoutes[$path];
        if (file_exists($file)) {
            require $file;
            exit;
        }
    }
    // Try to find owner file directly (handles both simple and nested paths)
    $ownerPath = str_replace('owner/', '', $path);
    if (empty($ownerPath)) {
        $ownerPath = 'login';
    }
    // Try file path (e.g., owner/login.php or owner/listings/edit.php)
    $file = __DIR__ . '/owner/' . $ownerPath . '.php';
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

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

