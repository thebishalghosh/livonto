<?php
// app/includes/admin_sidebar.php
$baseUrl = app_url('');
$currentPage = basename($_SERVER['PHP_SELF']);

// Define navigation items
$navItems = [
    [
        'id' => 'dashboard',
        'title' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'url' => $baseUrl . '/admin/index.php',
        'page' => 'index.php'
    ],
    [
        'id' => 'listings',
        'title' => 'Listings',
        'icon' => 'bi-building',
        'url' => $baseUrl . '/admin/listing_manage.php',
        'page' => 'listing_manage.php'
    ],
    [
        'id' => 'users',
        'title' => 'Users',
        'icon' => 'bi-people',
        'url' => $baseUrl . '/admin/users_manage.php',
        'page' => 'users_manage.php'
    ],
    [
        'id' => 'referrals',
        'title' => 'Referrals',
        'icon' => 'bi-gift',
        'url' => $baseUrl . '/admin/referrals_manage.php',
        'page' => 'referrals_manage.php'
    ],
    [
        'id' => 'divider1',
        'type' => 'divider'
    ],
    [
        'id' => 'bookings',
        'title' => 'Bookings',
        'icon' => 'bi-calendar-check',
        'url' => '#',
        'page' => 'bookings.php'
    ],
    [
        'id' => 'payments',
        'title' => 'Payments',
        'icon' => 'bi-credit-card',
        'url' => '#',
        'page' => 'payments.php'
    ],
    [
        'id' => 'reviews',
        'title' => 'Reviews',
        'icon' => 'bi-star',
        'url' => '#',
        'page' => 'reviews.php'
    ],
    [
        'id' => 'divider2',
        'type' => 'divider'
    ],
    [
        'id' => 'settings',
        'title' => 'Settings',
        'icon' => 'bi-gear',
        'url' => '#',
        'page' => 'settings.php'
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
                        $isActive = ($currentPage === $item['page']);
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

