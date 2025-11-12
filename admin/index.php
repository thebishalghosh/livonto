<?php
// admin/index.php
$pageTitle = "Dashboard";
require __DIR__ . '/../app/includes/admin_header.php';
// functions.php is already included in admin_header.php

try {
    $db = db();
    
    // Fetch Statistics
    $stats = [
        'total_users' => (int)$db->fetchValue("SELECT COUNT(*) FROM users WHERE role != 'admin'") ?: 0,
        'total_hosts' => (int)$db->fetchValue("SELECT COUNT(DISTINCT owner_name) FROM listings WHERE owner_name IS NOT NULL AND owner_name != ''") ?: 0,
        'total_listings' => (int)$db->fetchValue("SELECT COUNT(*) FROM listings") ?: 0,
        'active_listings' => (int)$db->fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'active'") ?: 0,
        'pending_listings' => (int)$db->fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'draft'") ?: 0,
        'total_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings") ?: 0,
        'pending_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE status = 'pending'") ?: 0,
        'total_revenue' => (float)$db->fetchValue("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'") ?: 0,
        'monthly_revenue' => (float)$db->fetchValue("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())") ?: 0,
        'total_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals") ?: 0
    ];
    
    // Calculate user growth percentage (users created this month vs last month)
    $usersThisMonth = (int)$db->fetchValue("SELECT COUNT(*) FROM users WHERE role != 'admin' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())") ?: 0;
    $usersLastMonth = (int)$db->fetchValue("SELECT COUNT(*) FROM users WHERE role != 'admin' AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))") ?: 0;
    $userGrowthPercent = $usersLastMonth > 0 ? round((($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100, 1) : ($usersThisMonth > 0 ? 100 : 0);
    
    // Fetch Recent Users (last 5)
    $recentUsersData = $db->fetchAll(
        "SELECT id, name, email, role, created_at 
         FROM users 
         WHERE role != 'admin'
         ORDER BY created_at DESC 
         LIMIT 5"
    );
    $recentUsers = [];
    foreach ($recentUsersData as $user) {
        $recentUsers[] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'created' => timeAgo($user['created_at'])
        ];
    }
    
    // Fetch Pending Listings (status = 'draft', last 5)
    $pendingListingsData = $db->fetchAll(
        "SELECT l.id, l.title, l.owner_name, l.created_at, ll.city,
                (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_price
         FROM listings l
         LEFT JOIN listing_locations ll ON l.id = ll.listing_id
         WHERE l.status = 'draft'
         ORDER BY l.created_at DESC 
         LIMIT 5"
    );
    $pendingListings = [];
    foreach ($pendingListingsData as $listing) {
        $pendingListings[] = [
            'id' => $listing['id'],
            'title' => $listing['title'],
            'host' => $listing['owner_name'] ?: 'Unknown',
            'city' => $listing['city'] ?: 'N/A',
            'price' => $listing['min_price'] ?: 0,
            'created' => timeAgo($listing['created_at'])
        ];
    }
    
    // Fetch Recent Bookings (last 5)
    $recentBookingsData = $db->fetchAll(
        "SELECT b.id, b.status, b.total_amount, b.created_at,
                l.title as listing_title,
                u.name as user_name
         FROM bookings b
         LEFT JOIN listings l ON b.listing_id = l.id
         LEFT JOIN users u ON b.user_id = u.id
         ORDER BY b.created_at DESC 
         LIMIT 5"
    );
    $recentBookings = [];
    foreach ($recentBookingsData as $booking) {
        $recentBookings[] = [
            'id' => $booking['id'],
            'listing' => $booking['listing_title'] ?: 'Unknown Listing',
            'user' => $booking['user_name'] ?: 'Unknown User',
            'amount' => $booking['total_amount'] ?: 0,
            'status' => $booking['status'],
            'date' => timeAgo($booking['created_at'])
        ];
    }
    
    // Fetch Average Rating
    $avgRating = (float)$db->fetchValue("SELECT COALESCE(AVG(rating), 0) FROM reviews") ?: 0;
    $stats['avg_rating'] = round($avgRating, 1);
    
    // Fetch Chart Data
    // Revenue Chart Data (last 30 days)
    $revenueData = $db->fetchAll(
        "SELECT DATE(created_at) as date, SUM(amount) as total
         FROM payments 
         WHERE status = 'success' 
         AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date ASC"
    );
    
    // Bookings by Status
    $bookingsByStatus = $db->fetchAll(
        "SELECT status, COUNT(*) as count 
         FROM bookings 
         GROUP BY status"
    );
    
    // Visit vs Booking Comparison (last 6 months)
    $visitVsBookingData = $db->fetchAll(
        "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            'visit' as type,
            COUNT(*) as count
         FROM visit_bookings
         WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
         UNION ALL
         SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            'booking' as type,
            COUNT(*) as count
         FROM bookings
         WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
         ORDER BY month ASC"
    );
    
    // Process the data to combine visits and bookings by month
    $visitVsBookingChart = [];
    foreach ($visitVsBookingData as $row) {
        $month = $row['month'];
        if (!isset($visitVsBookingChart[$month])) {
            $visitVsBookingChart[$month] = [
                'month' => $month,
                'visits' => 0,
                'bookings' => 0
            ];
        }
        if ($row['type'] === 'visit') {
            $visitVsBookingChart[$month]['visits'] = (int)$row['count'];
        } else {
            $visitVsBookingChart[$month]['bookings'] = (int)$row['count'];
        }
    }
    // Convert to indexed array
    $visitVsBookingChart = array_values($visitVsBookingChart);
    
    // Listings by City
    $listingsByCity = $db->fetchAll(
        "SELECT ll.city, COUNT(*) as count
         FROM listing_locations ll
         INNER JOIN listings l ON ll.listing_id = l.id
         WHERE ll.city IS NOT NULL AND ll.city != ''
         GROUP BY ll.city
         ORDER BY count DESC
         LIMIT 6"
    );
    
} catch (Exception $e) {
    // Fallback to empty data
    $stats = [
        'total_users' => 0,
        'total_hosts' => 0,
        'total_listings' => 0,
        'active_listings' => 0,
        'pending_listings' => 0,
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'total_revenue' => 0,
        'monthly_revenue' => 0,
        'total_referrals' => 0,
        'avg_rating' => 0
    ];
    $userGrowthPercent = 0;
    $recentUsers = [];
    $pendingListings = [];
    $recentBookings = [];
    $revenueData = [];
    $bookingsByStatus = [];
    $visitVsBookingChart = [];
    $listingsByCity = [];
}
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Dashboard</h1>
            <p class="admin-page-subtitle text-muted">Welcome back, <?= htmlspecialchars($currentUser['name']) ?>!</p>
        </div>
        <div>
            <button class="btn btn-primary">
                <i class="bi bi-download me-2"></i>Export Report
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Users -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-people"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Users</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total_users']) ?></div>
                <div class="admin-stat-card-change <?= $userGrowthPercent >= 0 ? 'text-success' : 'text-danger' ?>">
                    <i class="bi bi-arrow-<?= $userGrowthPercent >= 0 ? 'up' : 'down' ?>"></i> 
                    <?= $userGrowthPercent >= 0 ? '+' : '' ?><?= number_format($userGrowthPercent, 1) ?>% from last month
                </div>
            </div>
        </div>
    </div>

    <!-- Total Listings -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-building"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Listings</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total_listings']) ?></div>
                <div class="admin-stat-card-change">
                    <span class="text-primary"><?= $stats['active_listings'] ?> active</span>
                    <span class="text-warning ms-2"><?= $stats['pending_listings'] ?> pending</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Bookings -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Bookings</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total_bookings']) ?></div>
                <div class="admin-stat-card-change text-warning">
                    <i class="bi bi-clock"></i> <?= $stats['pending_bookings'] ?> pending approval
                </div>
            </div>
        </div>
    </div>

    <!-- Total Revenue -->
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Revenue</div>
                <div class="admin-stat-card-value">₹<?= number_format($stats['total_revenue']) ?></div>
                <div class="admin-stat-card-change text-success">
                    <i class="bi bi-arrow-up"></i> ₹<?= number_format($stats['monthly_revenue']) ?> this month
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row g-4 mb-4">
    <!-- Pending Approvals -->
    <div class="col-xl-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-clock-history me-2"></i>Pending Approvals
                </h5>
                <a href="<?= htmlspecialchars(app_url('admin/listings?status=pending')) ?>" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>
            <div class="admin-card-body">
                <?php if (empty($pendingListings)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                        <p>No pending listings</p>
                    </div>
                <?php else: ?>
                    <div class="admin-list">
                        <?php foreach ($pendingListings as $listing): ?>
                            <div class="admin-list-item">
                                <div class="admin-list-item-content">
                                    <h6 class="admin-list-item-title"><?= htmlspecialchars($listing['title']) ?></h6>
                                    <div class="admin-list-item-meta">
                                        <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($listing['host']) ?></span>
                                        <span class="ms-3"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($listing['city']) ?></span>
                                        <span class="ms-3"><i class="bi bi-currency-rupee me-1"></i><?= number_format($listing['price']) ?>/month</span>
                                    </div>
                                </div>
                                <div class="admin-list-item-actions">
                                    <small class="text-muted d-block mb-2"><?= htmlspecialchars($listing['created']) ?></small>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= htmlspecialchars(app_url('admin/listings?action=approve&id=' . $listing['id'])) ?>" class="btn btn-success">
                                            <i class="bi bi-check"></i> Approve
                                        </a>
                                        <a href="<?= htmlspecialchars(app_url('admin/listings?action=reject&id=' . $listing['id'])) ?>" class="btn btn-danger">
                                            <i class="bi bi-x"></i> Reject
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="col-xl-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-calendar-event me-2"></i>Recent Bookings
                </h5>
                <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="admin-card-body">
                <?php if (empty($recentBookings)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                        <p>No recent bookings</p>
                    </div>
                <?php else: ?>
                    <div class="admin-list">
                        <?php foreach ($recentBookings as $booking): ?>
                            <div class="admin-list-item">
                                <div class="admin-list-item-content">
                                    <h6 class="admin-list-item-title"><?= htmlspecialchars($booking['listing']) ?></h6>
                                    <div class="admin-list-item-meta">
                                        <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($booking['user']) ?></span>
                                        <span class="ms-3"><i class="bi bi-currency-rupee me-1"></i><?= number_format($booking['amount']) ?></span>
                                        <span class="ms-3">
                                            <?php
                                            $badgeClass = match($booking['status']) {
                                                'confirmed' => 'success',
                                                'pending' => 'warning',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($booking['status']) ?></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="admin-list-item-actions">
                                    <small class="text-muted"><?= htmlspecialchars($booking['date']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Revenue Chart -->
    <div class="col-xl-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-graph-up me-2"></i>Revenue Overview
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary active" data-period="7d">7 Days</button>
                    <button class="btn btn-outline-primary" data-period="30d">30 Days</button>
                    <button class="btn btn-outline-primary" data-period="90d">90 Days</button>
                </div>
            </div>
            <div class="admin-card-body">
                <div id="revenueChart" style="height: 300px;"></div>
            </div>
        </div>
    </div>

    <!-- Bookings by Status -->
    <div class="col-xl-4">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-pie-chart me-2"></i>Bookings by Status
                </h5>
            </div>
            <div class="admin-card-body">
                <div id="bookingsPieChart" style="height: 300px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Charts Row -->
<div class="row g-4 mb-4">
    <!-- Visit vs Booking Comparison -->
    <div class="col-xl-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-bar-chart me-2"></i>Visit vs Booking Conversion
                </h5>
            </div>
            <div class="admin-card-body">
                <div id="visitVsBookingChart" style="height: 280px;"></div>
                <div class="mt-3 text-center">
                    <?php
                    // Calculate overall conversion ratio
                    $totalVisits = 0;
                    $totalBookings = 0;
                    if (!empty($visitVsBookingChart)) {
                        $totalVisits = array_sum(array_column($visitVsBookingChart, 'visits'));
                        $totalBookings = array_sum(array_column($visitVsBookingChart, 'bookings'));
                    }
                    $conversionRate = $totalVisits > 0 ? round(($totalBookings / $totalVisits) * 100, 1) : 0;
                    ?>
                    <div class="d-flex justify-content-center gap-4 flex-wrap">
                        <div>
                            <div class="text-muted small">Total Visits</div>
                            <div class="h5 mb-0"><?= number_format($totalVisits) ?></div>
                        </div>
                        <div>
                            <div class="text-muted small">Total Bookings</div>
                            <div class="h5 mb-0"><?= number_format($totalBookings) ?></div>
                        </div>
                        <div>
                            <div class="text-muted small">Conversion Rate</div>
                            <div class="h5 mb-0 text-primary"><?= $conversionRate ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Listings by City -->
    <div class="col-xl-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-building me-2"></i>Listings by City
                </h5>
            </div>
            <div class="admin-card-body">
                <div id="listingsCityChart" style="height: 280px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Stats Row -->
<div class="row g-4">
    <!-- Recent Users -->
    <div class="col-xl-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-person-plus me-2"></i>Recent Users
                </h5>
                <a href="<?= htmlspecialchars(app_url('admin/users')) ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="admin-card-body">
                <div class="admin-list">
                    <?php foreach ($recentUsers as $user): ?>
                        <div class="admin-list-item">
                            <div class="admin-list-item-avatar">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <div class="admin-list-item-content">
                                <h6 class="admin-list-item-title"><?= htmlspecialchars($user['name']) ?></h6>
                                <div class="admin-list-item-meta">
                                    <span><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email']) ?></span>
                                    <span class="ms-3">
                                        <span class="badge bg-<?= $user['role'] === 'host' ? 'primary' : 'secondary' ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div class="admin-list-item-actions">
                                <small class="text-muted"><?= htmlspecialchars($user['created']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="col-xl-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-graph-up me-2"></i>Quick Statistics
                </h5>
            </div>
            <div class="admin-card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="admin-quick-stat">
                            <div class="admin-quick-stat-icon text-primary">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="admin-quick-stat-content">
                                <div class="admin-quick-stat-value"><?= number_format($stats['total_hosts']) ?></div>
                                <div class="admin-quick-stat-label">Hosts</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-quick-stat">
                            <div class="admin-quick-stat-icon text-success">
                                <i class="bi bi-gift"></i>
                            </div>
                            <div class="admin-quick-stat-content">
                                <div class="admin-quick-stat-value"><?= number_format($stats['total_referrals']) ?></div>
                                <div class="admin-quick-stat-label">Referrals</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-quick-stat">
                            <div class="admin-quick-stat-icon text-warning">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="admin-quick-stat-content">
                                <div class="admin-quick-stat-value"><?= $stats['pending_listings'] ?></div>
                                <div class="admin-quick-stat-label">Pending Listings</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-quick-stat">
                            <div class="admin-quick-stat-icon text-info">
                                <i class="bi bi-star"></i>
                            </div>
                            <div class="admin-quick-stat-content">
                                <div class="admin-quick-stat-value"><?= number_format($stats['avg_rating'], 1) ?></div>
                                <div class="admin-quick-stat-label">Avg Rating</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Google Charts Script -->
<script type="text/javascript">
    // Load Google Charts
    google.charts.load('current', {'packages':['corechart', 'line', 'bar']});
    google.charts.setOnLoadCallback(drawCharts);

    function drawCharts() {
        drawRevenueChart();
        drawBookingsPieChart();
        drawVisitVsBookingChart();
        drawListingsCityChart();
    }

    // Revenue Chart (Line Chart)
    function drawRevenueChart() {
        var revenueData = <?= json_encode($revenueData) ?>;
        var chartData = [['Date', 'Revenue']];
        
        if (revenueData.length > 0) {
            revenueData.forEach(function(item) {
                var date = new Date(item.date);
                chartData.push([date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }), parseFloat(item.total)]);
            });
        } else {
            // Show placeholder if no data
            chartData.push(['No Data', 0]);
        }
        
        var data = google.visualization.arrayToDataTable(chartData);

        var options = {
            title: '',
            curveType: 'function',
            legend: { position: 'none' },
            hAxis: {
                title: 'Date',
                textStyle: { color: '#757095' },
                titleTextStyle: { color: '#757095' }
            },
            vAxis: {
                title: 'Revenue (₹)',
                format: 'currency',
                textStyle: { color: '#757095' },
                titleTextStyle: { color: '#757095' }
            },
            colors: ['#8B6BD1'],
            backgroundColor: 'transparent',
            chartArea: {
                left: 60,
                top: 20,
                right: 20,
                bottom: 50,
                width: '100%',
                height: '100%'
            },
            lineWidth: 3,
            pointSize: 6,
            animation: {
                startup: true,
                duration: 1000,
                easing: 'out'
            }
        };

        var chart = new google.visualization.LineChart(document.getElementById('revenueChart'));
        chart.draw(data, options);

        // Period toggle
        document.querySelectorAll('[data-period]').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('[data-period]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                // Here you would update the chart with new data based on period
                // For now, just redraw with same data
                chart.draw(data, options);
            });
        });
    }

    // Bookings by Status (Pie Chart)
    function drawBookingsPieChart() {
        var bookingsData = <?= json_encode($bookingsByStatus) ?>;
        var chartData = [['Status', 'Count']];
        
        if (bookingsData.length > 0) {
            bookingsData.forEach(function(item) {
                chartData.push([item.status.charAt(0).toUpperCase() + item.status.slice(1), parseInt(item.count)]);
            });
        } else {
            // Show placeholder if no data
            chartData.push(['No Data', 1]);
        }
        
        var data = google.visualization.arrayToDataTable(chartData);

        var options = {
            title: '',
            pieHole: 0.4,
            colors: ['#43e97b', '#fbbf24', '#ef4444', '#8B6BD1'],
            backgroundColor: 'transparent',
            chartArea: {
                left: 20,
                top: 20,
                right: 20,
                bottom: 20,
                width: '100%',
                height: '100%'
            },
            legend: {
                position: 'bottom',
                textStyle: { color: '#757095', fontSize: 12 }
            },
            pieSliceText: 'value',
            pieSliceTextStyle: {
                color: 'white',
                fontSize: 12,
                bold: true
            },
            animation: {
                startup: true,
                duration: 1000,
                easing: 'out'
            }
        };

        var chart = new google.visualization.PieChart(document.getElementById('bookingsPieChart'));
        chart.draw(data, options);
    }

    // Visit vs Booking Comparison (Column Chart)
    function drawVisitVsBookingChart() {
        var comparisonData = <?= json_encode($visitVsBookingChart) ?>;
        var chartData = [['Month', 'Visits', 'Bookings']];
        
        if (comparisonData.length > 0) {
            comparisonData.forEach(function(item) {
                var date = new Date(item.month + '-01');
                chartData.push([
                    date.toLocaleDateString('en-US', { month: 'short' }), 
                    parseInt(item.visits || 0), 
                    parseInt(item.bookings || 0)
                ]);
            });
        } else {
            // Show placeholder if no data
            chartData.push(['No Data', 0, 0]);
        }
        
        var data = google.visualization.arrayToDataTable(chartData);

        var options = {
            title: '',
            hAxis: {
                title: 'Month',
                textStyle: { color: '#757095' },
                titleTextStyle: { color: '#757095' }
            },
            vAxis: {
                title: 'Count',
                textStyle: { color: '#757095' },
                titleTextStyle: { color: '#757095' }
            },
            colors: ['#4facfe', '#8B6BD1'],
            backgroundColor: 'transparent',
            chartArea: {
                left: 60,
                top: 20,
                right: 20,
                bottom: 50,
                width: '100%',
                height: '100%'
            },
            legend: {
                position: 'top',
                textStyle: { color: '#757095', fontSize: 12 }
            },
            animation: {
                startup: true,
                duration: 1000,
                easing: 'out'
            },
            isStacked: false
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('visitVsBookingChart'));
        chart.draw(data, options);
    }

    // Listings by City (Column Chart)
    function drawListingsCityChart() {
        var cityData = <?= json_encode($listingsByCity) ?>;
        var chartData = [['City', 'Listings']];
        
        if (cityData.length > 0) {
            cityData.forEach(function(item) {
                chartData.push([item.city || 'Unknown', parseInt(item.count)]);
            });
        } else {
            // Show placeholder if no data
            chartData.push(['No Data', 0]);
        }
        
        var data = google.visualization.arrayToDataTable(chartData);

        var options = {
            title: '',
            hAxis: {
                title: 'City',
                textStyle: { color: '#757095' },
                titleTextStyle: { color: '#757095' }
            },
            vAxis: {
                title: 'Number of Listings',
                textStyle: { color: '#757095' },
                titleTextStyle: { color: '#757095' }
            },
            colors: ['#8B6BD1'],
            backgroundColor: 'transparent',
            chartArea: {
                left: 60,
                top: 20,
                right: 20,
                bottom: 50,
                width: '100%',
                height: '100%'
            },
            legend: { position: 'none' },
            animation: {
                startup: true,
                duration: 1000,
                easing: 'out'
            }
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('listingsCityChart'));
        chart.draw(data, options);
    }

    // Redraw charts on window resize
    window.addEventListener('resize', function() {
        drawCharts();
    });
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

