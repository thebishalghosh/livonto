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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - Livonto Admin</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($baseUrl) ?>/public/assets/images/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars($baseUrl) ?>/public/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($baseUrl) ?>/public/assets/images/favicon.ico">
    
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
                        <span class="admin-badge">3</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown" aria-labelledby="notificationsDropdown">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#"><small class="text-muted">New listing pending approval</small></a></li>
                        <li><a class="dropdown-item" href="#"><small class="text-muted">New user registration</small></a></li>
                        <li><a class="dropdown-item" href="#"><small class="text-muted">Payment received</small></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">View all</a></li>
                    </ul>
                </div>

                <!-- User Dropdown -->
                <div class="admin-navbar-item dropdown">
                    <button class="admin-navbar-user" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="admin-user-avatar">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div class="admin-user-info">
                            <span class="admin-user-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                            <small class="admin-user-role">Administrator</small>
                        </div>
                        <i class="bi bi-chevron-down ms-2"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end admin-dropdown" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?= htmlspecialchars($baseUrl . '/public/profile.php') ?>">
                            <i class="bi bi-person me-2"></i>My Profile
                        </a></li>
                        <li><a class="dropdown-item" href="#">
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

