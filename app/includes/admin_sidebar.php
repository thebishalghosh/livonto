<?php
// app/includes/admin_sidebar.php
$baseUrl = app_url('');

// Get current page from URL to determine active state
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim(str_replace($baseUrl, '', $path), '/');
$currentRoute = $path ?: 'admin';

// Define navigation items
$navItems = [
    [
        'id' => 'dashboard',
        'title' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'url' => app_url('admin'),
        'route' => 'admin'
    ],
    [
        'id' => 'listings',
        'title' => 'Listings',
        'icon' => 'bi-building',
        'url' => app_url('admin/listings'),
        'route' => 'admin/listings'
    ],
    [
        'id' => 'users',
        'title' => 'Users',
        'icon' => 'bi-people',
        'url' => app_url('admin/users'),
        'route' => 'admin/users'
    ],
    [
        'id' => 'amenities',
        'title' => 'Amenities',
        'icon' => 'bi-star',
        'url' => app_url('admin/amenities'),
        'route' => 'admin/amenities'
    ],
    [
        'id' => 'house-rules',
        'title' => 'House Rules',
        'icon' => 'bi-shield-check',
        'url' => app_url('admin/house-rules'),
        'route' => 'admin/house-rules'
    ],
    [
        'id' => 'referrals',
        'title' => 'Referrals',
        'icon' => 'bi-gift',
        'url' => app_url('admin/referrals'),
        'route' => 'admin/referrals'
    ],
    [
        'id' => 'enquiries',
        'title' => 'Enquiries',
        'icon' => 'bi-envelope',
        'url' => app_url('admin/enquiries'),
        'route' => 'admin/enquiries'
    ],
    [
        'id' => 'visit-bookings',
        'title' => 'Visit Bookings',
        'icon' => 'bi-calendar-check',
        'url' => app_url('admin/visit-bookings'),
        'route' => 'admin/visit-bookings'
    ],
    [
        'id' => 'divider1',
        'type' => 'divider'
    ],
    [
        'id' => 'bookings',
        'title' => 'Bookings',
        'icon' => 'bi-calendar-event',
        'url' => app_url('admin/bookings'),
        'route' => 'admin/bookings'
    ],
    [
        'id' => 'payments',
        'title' => 'Payments',
        'icon' => 'bi-credit-card',
        'url' => app_url('admin/payments'),
        'route' => 'admin/payments'
    ],
    [
        'id' => 'reviews',
        'title' => 'Reviews',
        'icon' => 'bi-star',
        'url' => app_url('admin/reviews'),
        'route' => 'admin/reviews'
    ],
    [
        'id' => 'divider2',
        'type' => 'divider'
    ],
    [
        'id' => 'settings',
        'title' => 'Settings',
        'icon' => 'bi-gear',
        'url' => app_url('admin/settings'),
        'route' => 'admin/settings'
    ],
    [
        'id' => 'website',
        'title' => 'View Website',
        'icon' => 'bi-globe',
        'url' => $baseUrl . '/public/',
        'page' => '',
        'external' => true
    ]
];
?>
<!-- Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-content">
        <!-- Navigation Menu -->
        <nav class="admin-sidebar-nav">
            <ul class="admin-nav-list">
                <?php foreach ($navItems as $item): ?>
                    <?php if (isset($item['type']) && $item['type'] === 'divider'): ?>
                        <li class="admin-nav-divider"></li>
                    <?php else: ?>
                        <?php
                        $isActive = ($currentRoute === ($item['route'] ?? ''));
                        $classes = ['admin-nav-item'];
                        if ($isActive) $classes[] = 'active';
                        ?>
                        <li class="<?= implode(' ', $classes) ?>">
                            <a href="<?= htmlspecialchars($item['url']) ?>" 
                               class="admin-nav-link"
                               <?= isset($item['external']) && $item['external'] ? 'target="_blank"' : '' ?>>
                                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                                <span><?= htmlspecialchars($item['title']) ?></span>
                                <?php if ($isActive): ?>
                                    <span class="admin-nav-indicator"></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Sidebar Footer -->
        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-footer-content">
                <div class="admin-sidebar-footer-text">
                    <small class="text-muted">Livonto Admin Panel</small>
                    <small class="text-muted d-block">v1.0.0</small>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div class="admin-sidebar-overlay"></div>

<!-- Main Content Area -->
<main class="admin-main" id="adminMain">
    <div class="admin-main-content">

