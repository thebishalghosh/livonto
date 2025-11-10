<?php
// app/includes/admin_header.php
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . '/../config.php';
$baseUrl = app_url('');

// Check if user is logged in and is admin
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}
$pageTitle = $pageTitle ?? 'Admin Dashboard';
$currentUser = [
    'id' => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['user_name'] ?? 'Admin',
    'email' => $_SESSION['user_email'] ?? '',
    'role' => $_SESSION['user_role'] ?? 'admin'
];

// Fetch admin profile image - always fetch fresh from database to ensure correct path
$adminProfileImage = null;
$hasAdminProfileImage = false;

if (!empty($currentUser['id'])) {
    try {
        // Check if functions.php is already loaded
        if (!function_exists('db')) {
            require_once __DIR__ . '/../functions.php';
        }
        $db = db();
        $profileImage = $db->fetchValue("SELECT profile_image FROM users WHERE id = ?", [$currentUser['id']]);
        if (!empty($profileImage) && trim($profileImage) !== '') {
            // Handle both http:// and https:// URLs (Google uses https://) or local paths
            if (strpos($profileImage, 'http://') === 0 || strpos($profileImage, 'https://') === 0) {
                // External URL (Google profile image)
                $adminProfileImage = $profileImage;
            } else {
                // Local file path - use app_url to get full URL
                // app_url now handles storage/ paths correctly
                $adminProfileImage = app_url($profileImage);
                
                // Safety check: ensure the generated URL doesn't contain /admin/storage/
                if (strpos($adminProfileImage, '/admin/storage/') !== false) {
                    // If somehow /admin/ got added, remove it
                    $adminProfileImage = str_replace('/admin/storage/', '/storage/', $adminProfileImage);
                }
            }
            $hasAdminProfileImage = true;
            // Update session for next time (but we'll always fetch fresh to ensure correctness)
            $_SESSION['user_profile_image'] = $adminProfileImage;
        } else {
            // No profile image - clear session
            unset($_SESSION['user_profile_image']);
        }
    } catch (Exception $e) {
        // Silently fail - will show icon instead
    }
}

// Fetch dynamic notifications
// Check if functions.php is already loaded
if (!function_exists('getFlashMessage')) {
    require_once __DIR__ . '/../functions.php';
}
$notifications = [];
$notificationCount = 0;

try {
    $db = db();
    
    // Get pending listings (draft status)
    $pendingListings = $db->fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'draft'") ?: 0;
    if ($pendingListings > 0) {
        $notifications[] = [
            'type' => 'listing',
            'message' => $pendingListings . ' listing' . ($pendingListings > 1 ? 's' : '') . ' pending approval',
            'url' => app_url('admin/listings?status=draft'),
            'icon' => 'bi-building',
            'count' => $pendingListings
        ];
        $notificationCount += $pendingListings;
    }
    
    // Get new users today
    $newUsersToday = $db->fetchValue("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()") ?: 0;
    if ($newUsersToday > 0) {
        $notifications[] = [
            'type' => 'user',
            'message' => $newUsersToday . ' new user' . ($newUsersToday > 1 ? 's' : '') . ' registered today',
            'url' => app_url('admin/users'),
            'icon' => 'bi-person-plus',
            'count' => $newUsersToday
        ];
        $notificationCount += $newUsersToday;
    }
    
    // Get pending visit bookings
    $pendingVisits = $db->fetchValue("SELECT COUNT(*) FROM visit_bookings WHERE status = 'pending'") ?: 0;
    if ($pendingVisits > 0) {
        $notifications[] = [
            'type' => 'visit',
            'message' => $pendingVisits . ' visit booking' . ($pendingVisits > 1 ? 's' : '') . ' pending',
            'url' => app_url('admin/visit-bookings?status=pending'),
            'icon' => 'bi-calendar-check',
            'count' => $pendingVisits
        ];
        $notificationCount += $pendingVisits;
    }
    
    // Get new enquiries (unread)
    $newEnquiries = $db->fetchValue("SELECT COUNT(*) FROM contacts WHERE status = 'new'") ?: 0;
    if ($newEnquiries > 0) {
        $notifications[] = [
            'type' => 'enquiry',
            'message' => $newEnquiries . ' new enquiry' . ($newEnquiries > 1 ? 'ies' : 'y'),
            'url' => app_url('admin/enquiries?status=new'),
            'icon' => 'bi-envelope',
            'count' => $newEnquiries
        ];
        $notificationCount += $newEnquiries;
    }
    
    // Get pending bookings
    $pendingBookings = $db->fetchValue("SELECT COUNT(*) FROM bookings WHERE status = 'pending'") ?: 0;
    if ($pendingBookings > 0) {
        $notifications[] = [
            'type' => 'booking',
            'message' => $pendingBookings . ' booking' . ($pendingBookings > 1 ? 's' : '') . ' pending',
            'url' => app_url('admin/bookings?status=pending'),
            'icon' => 'bi-calendar-event',
            'count' => $pendingBookings
        ];
        $notificationCount += $pendingBookings;
    }
    
    // Sort notifications by count (highest first)
    usort($notifications, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    // Limit to 5 most recent/important notifications
    $notifications = array_slice($notifications, 0, 5);
    
} catch (Exception $e) {
    // Silently fail - notifications will be empty
    $notifications = [];
    $notificationCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - Livonto Admin</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($baseUrl . '/public/assets/images/favicon.ico') ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars($baseUrl . '/public/assets/images/favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($baseUrl . '/public/assets/images/favicon.ico') ?>">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Admin Styles -->
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/admin/assets/css/admin.css') ?>">
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
</head>
<body class="admin-body">
    <!-- Top Navigation Bar -->
    <nav class="admin-navbar">
        <div class="admin-navbar-content">
            <!-- Left: Menu Toggle & Logo -->
            <div class="admin-navbar-left">
                <button class="admin-sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <a href="<?= htmlspecialchars(app_url('admin')) ?>" class="admin-navbar-brand">
                    <img src="<?= htmlspecialchars($baseUrl . '/public/assets/images/logo-removebg.png') ?>" 
                         alt="Livonto" 
                         class="admin-brand-logo">
                </a>
            </div>

            <!-- Right: User Menu & Actions -->
            <div class="admin-navbar-right">
                <!-- Notifications -->
                <div class="admin-navbar-item dropdown">
                    <button class="admin-navbar-icon-btn" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="admin-badge"><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown" aria-labelledby="notificationsDropdown" style="min-width: 300px; max-height: 400px; overflow-y: auto;">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <?php if (empty($notifications)): ?>
                            <li><a class="dropdown-item text-center text-muted" href="#">
                                <small>No new notifications</small>
                            </a></li>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <li>
                                    <a class="dropdown-item" href="<?= htmlspecialchars($notification['url']) ?>">
                                        <div class="d-flex align-items-start">
                                            <i class="bi <?= htmlspecialchars($notification['icon']) ?> me-2 mt-1" style="color: var(--primary);"></i>
                                            <div class="flex-grow-1">
                                                <div class="small"><?= htmlspecialchars($notification['message']) ?></div>
                                            </div>
                                            <?php if ($notification['count'] > 1): ?>
                                                <span class="badge bg-primary ms-2"><?= $notification['count'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="<?= htmlspecialchars(app_url('admin')) ?>">
                            <small>View Dashboard</small>
                        </a></li>
                    </ul>
                </div>

                <!-- User Dropdown -->
                <div class="admin-navbar-item dropdown">
                    <button class="admin-navbar-user" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="admin-user-avatar" style="position: relative; width: 40px !important; height: 40px !important; min-width: 40px; min-height: 40px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?php if ($hasAdminProfileImage && !empty($adminProfileImage)): ?>
                                <img src="<?= htmlspecialchars($adminProfileImage) ?>" 
                                     alt="<?= htmlspecialchars($currentUser['name']) ?>" 
                                     id="adminHeaderProfileImage"
                                     style="width: 40px !important; height: 40px !important; object-fit: cover; display: block !important; border-radius: 50%; position: absolute; top: 0; left: 0;"
                                     onerror="this.style.display='none'; var icon = document.getElementById('adminHeaderProfileIcon'); if(icon) icon.style.display='flex';">
                                <i class="bi bi-person-circle" id="adminHeaderProfileIcon" style="display: none; font-size: 24px; color: white; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"></i>
                            <?php else: ?>
                                <i class="bi bi-person-circle" style="font-size: 24px; color: white; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"></i>
                            <?php endif; ?>
                        </div>
                        <div class="admin-user-info">
                            <span class="admin-user-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                            <small class="admin-user-role">Administrator</small>
                        </div>
                        <i class="bi bi-chevron-down ms-2"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('admin/profile')) ?>">
                            <i class="bi bi-person me-2"></i>My Profile
                        </a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(app_url('admin/settings')) ?>">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= htmlspecialchars(app_url('logout')) ?>">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="admin-container">
        <?php require __DIR__ . '/admin_sidebar.php'; ?>

