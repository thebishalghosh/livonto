<?php
// admin/index.php
$pageTitle = "Dashboard";
require __DIR__ . '/../app/includes/admin_header.php';

// TODO: Replace with actual database queries
// For now, using placeholder data
$stats = [
    'total_users' => 1250,
    'total_hosts' => 85,
    'total_listings' => 320,
    'active_listings' => 245,
    'pending_listings' => 12,
    'total_bookings' => 1840,
    'pending_bookings' => 8,
    'total_revenue' => 2450000,
    'monthly_revenue' => 185000,
    'total_referrals' => 450
];

$recentUsers = [
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'user', 'created' => '2 hours ago'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => 'host', 'created' => '5 hours ago'],
    ['id' => 3, 'name' => 'Mike Johnson', 'email' => 'mike@example.com', 'role' => 'user', 'created' => '1 day ago'],
];

$pendingListings = [
    ['id' => 1, 'title' => 'Cozy PG near IIT', 'host' => 'Rajesh Kumar', 'city' => 'Mumbai', 'price' => 6500, 'created' => '3 hours ago'],
    ['id' => 2, 'title' => 'Modern PG with AC', 'host' => 'Priya Sharma', 'city' => 'Delhi', 'price' => 8500, 'created' => '1 day ago'],
    ['id' => 3, 'title' => 'Budget Friendly PG', 'host' => 'Amit Patel', 'city' => 'Pune', 'price' => 5500, 'created' => '2 days ago'],
];

$recentBookings = [
    ['id' => 1, 'listing' => 'Cozy PG near IIT', 'user' => 'Rahul Mehta', 'amount' => 6500, 'status' => 'confirmed', 'date' => '2 hours ago'],
    ['id' => 2, 'listing' => 'Modern PG with AC', 'user' => 'Sneha Reddy', 'amount' => 8500, 'status' => 'pending', 'date' => '5 hours ago'],
    ['id' => 3, 'listing' => 'Budget Friendly PG', 'user' => 'Vikram Singh', 'amount' => 5500, 'status' => 'confirmed', 'date' => '1 day ago'],
];
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
                <div class="admin-stat-card-change text-success">
                    <i class="bi bi-arrow-up"></i> +12% from last month
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
                <a href="<?= htmlspecialchars($baseUrl . '/admin/listing_manage.php?status=pending') ?>" class="btn btn-sm btn-outline-primary">
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
                                        <a href="<?= htmlspecialchars($baseUrl . '/admin/listing_manage.php?action=approve&id=' . $listing['id']) ?>" class="btn btn-success">
                                            <i class="bi bi-check"></i> Approve
                                        </a>
                                        <a href="<?= htmlspecialchars($baseUrl . '/admin/listing_manage.php?action=reject&id=' . $listing['id']) ?>" class="btn btn-danger">
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
    <!-- User Growth -->
    <div class="col-xl-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <h5 class="admin-card-title">
                    <i class="bi bi-people me-2"></i>User Growth
                </h5>
            </div>
            <div class="admin-card-body">
                <div id="userGrowthChart" style="height: 280px;"></div>
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
                <a href="<?= htmlspecialchars($baseUrl . '/admin/users_manage.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
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
                                <div class="admin-quick-stat-value">4.8</div>
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
        drawUserGrowthChart();
        drawListingsCityChart();
    }

    // Revenue Chart (Line Chart)
    function drawRevenueChart() {
        var data = google.visualization.arrayToDataTable([
            ['Date', 'Revenue'],
            ['Jan 1', 45000],
            ['Jan 8', 52000],
            ['Jan 15', 48000],
            ['Jan 22', 61000],
            ['Jan 29', 55000],
            ['Feb 5', 67000],
            ['Feb 12', 72000],
            ['Feb 19', 68000],
            ['Feb 26', 75000],
            ['Mar 5', 82000],
            ['Mar 12', 78000],
            ['Mar 19', 85000],
            ['Mar 26', 92000]
        ]);

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
        var data = google.visualization.arrayToDataTable([
            ['Status', 'Count'],
            ['Confirmed', 1240],
            ['Pending', 85],
            ['Cancelled', 45],
            ['Completed', 470]
        ]);

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

    // User Growth (Area Chart)
    function drawUserGrowthChart() {
        var data = google.visualization.arrayToDataTable([
            ['Month', 'Users', 'Hosts'],
            ['Jan', 850, 45],
            ['Feb', 920, 52],
            ['Mar', 1050, 58],
            ['Apr', 1120, 65],
            ['May', 1180, 72],
            ['Jun', 1250, 85]
        ]);

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
            colors: ['#8B6BD1', '#4facfe'],
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

        var chart = new google.visualization.AreaChart(document.getElementById('userGrowthChart'));
        chart.draw(data, options);
    }

    // Listings by City (Column Chart)
    function drawListingsCityChart() {
        var data = google.visualization.arrayToDataTable([
            ['City', 'Listings'],
            ['Mumbai', 85],
            ['Delhi', 72],
            ['Bangalore', 68],
            ['Pune', 45],
            ['Kolkata', 38],
            ['Hyderabad', 32]
        ]);

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

