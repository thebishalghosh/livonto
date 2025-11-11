<?php
/**
 * Admin Bookings Management Page
 * View and manage all PG bookings
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

$action = $_GET['action'] ?? '';
$bookingId = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action && $bookingId) {
    try {
        $db = db();
        
        $booking = $db->fetchOne("SELECT id FROM bookings WHERE id = ?", [$bookingId]);
        if (!$booking) {
            $_SESSION['flash_message'] = 'Booking not found';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }
        
        if ($action === 'update_status' && isset($_POST['new_status'])) {
            $newStatus = $_POST['new_status'];
            if (in_array($newStatus, ['pending', 'confirmed', 'cancelled', 'completed'])) {
                // Get current booking status and room_config_id
                $currentBooking = $db->fetchOne(
                    "SELECT status, room_config_id FROM bookings WHERE id = ?",
                    [$bookingId]
                );
                
                if ($currentBooking) {
                    $oldStatus = $currentBooking['status'];
                    $roomConfigId = $currentBooking['room_config_id'];
                    
                    // Update booking status
                    $db->execute("UPDATE bookings SET status = ? WHERE id = ?", [$newStatus, $bookingId]);
                    
                    // Update available_rooms based on status change
                    if ($roomConfigId) {
                        // If changing from pending/confirmed to cancelled, increase available_rooms
                        if (in_array($oldStatus, ['pending', 'confirmed']) && $newStatus === 'cancelled') {
                            $db->execute(
                                "UPDATE room_configurations 
                                 SET available_rooms = LEAST(total_rooms, available_rooms + 1) 
                                 WHERE id = ?",
                                [$roomConfigId]
                            );
                        }
                        // If changing from cancelled/pending to confirmed, decrease available_rooms
                        elseif (in_array($oldStatus, ['pending', 'cancelled']) && $newStatus === 'confirmed') {
                            $db->execute(
                                "UPDATE room_configurations 
                                 SET available_rooms = GREATEST(0, available_rooms - 1) 
                                 WHERE id = ?",
                                [$roomConfigId]
                            );
                        }
                        // If changing from confirmed to completed, room becomes available (increase)
                        elseif ($oldStatus === 'confirmed' && $newStatus === 'completed') {
                            $db->execute(
                                "UPDATE room_configurations 
                                 SET available_rooms = LEAST(total_rooms, available_rooms + 1) 
                                 WHERE id = ?",
                                [$roomConfigId]
                            );
                        }
                    }
                }
                
                $_SESSION['flash_message'] = 'Booking status updated successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/bookings'));
                exit;
            }
        } elseif ($action === 'update_notes' && isset($_POST['admin_notes'])) {
            $adminNotes = trim($_POST['admin_notes']);
            $db->execute("UPDATE bookings SET special_requests = ? WHERE id = ?", [$adminNotes ?: null, $bookingId]);
            $_SESSION['flash_message'] = 'Admin notes updated successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/bookings'));
            exit;
        } elseif ($action === 'delete' && isset($_POST['confirm_delete'])) {
            // Get booking details before deletion
            $bookingToDelete = $db->fetchOne(
                "SELECT status, room_config_id FROM bookings WHERE id = ?",
                [$bookingId]
            );
            
            // Delete booking
            $db->execute("DELETE FROM bookings WHERE id = ?", [$bookingId]);
            
            // If booking was confirmed, increase available_rooms
            if ($bookingToDelete && $bookingToDelete['status'] === 'confirmed' && $bookingToDelete['room_config_id']) {
                $db->execute(
                    "UPDATE room_configurations 
                     SET available_rooms = LEAST(total_rooms, available_rooms + 1) 
                     WHERE id = ?",
                    [$bookingToDelete['room_config_id']]
                );
            }
            
            $_SESSION['flash_message'] = 'Booking deleted successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/bookings'));
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error processing request: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
    }
}

$pageTitle = "Bookings Management";
require __DIR__ . '/../app/includes/admin_header.php';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = db();
    
    // Check if duration_months column exists
    $hasDurationMonths = false;
    try {
        $db->fetchValue("SELECT duration_months FROM bookings LIMIT 1");
        $hasDurationMonths = true;
    } catch (Exception $e) {
        // Column doesn't exist, will use default value
        $hasDurationMonths = false;
    }
    
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR l.title LIKE ? OR b.special_requests LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($status) && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
        $where[] = "b.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $totalBookings = $db->fetchValue(
        "SELECT COUNT(*) FROM bookings b
         LEFT JOIN users u ON b.user_id = u.id
         LEFT JOIN listings l ON b.listing_id = l.id
         {$whereClause}",
        $params
    ) ?: 0;
    
    $totalPages = ceil($totalBookings / $perPage);
    
    $allowedSorts = ['b.id', 'b.booking_start_date', 'b.status', 'b.created_at', 'u.name', 'l.title', 'b.total_amount'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'b.created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Build SELECT clause with conditional duration_months
    $durationField = $hasDurationMonths ? 'b.duration_months,' : '1 as duration_months,';
    
    $bookings = $db->fetchAll(
        "SELECT b.id, b.listing_id, b.user_id, b.room_config_id, b.booking_start_date, {$durationField}
                b.total_amount, b.status, b.special_requests, b.created_at, b.updated_at,
                u.name as user_name, u.email as user_email, u.phone as user_phone,
                l.title as listing_title,
                loc.city as listing_city, loc.pin_code as listing_pincode,
                rc.room_type, rc.rent_per_month,
                p.id as payment_id, p.status as payment_status, p.provider, p.provider_payment_id,
                i.id as invoice_id, i.invoice_number
         FROM bookings b
         LEFT JOIN users u ON b.user_id = u.id
         LEFT JOIN listings l ON b.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         LEFT JOIN room_configurations rc ON b.room_config_id = rc.id
         LEFT JOIN payments p ON b.id = p.booking_id
         LEFT JOIN invoices i ON b.id = i.booking_id AND p.id = i.payment_id
         {$whereClause}
         ORDER BY {$sort} {$order}
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    $statsRow = $db->fetchOne(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month,
            SUM(CASE WHEN status = 'confirmed' THEN total_amount ELSE 0 END) as total_revenue
         FROM bookings"
    );
    $stats = [
        'total' => intval($statsRow['total'] ?? 0),
        'pending' => intval($statsRow['pending'] ?? 0),
        'confirmed' => intval($statsRow['confirmed'] ?? 0),
        'completed' => intval($statsRow['completed'] ?? 0),
        'cancelled' => intval($statsRow['cancelled'] ?? 0),
        'today' => intval($statsRow['today'] ?? 0),
        'this_month' => intval($statsRow['this_month'] ?? 0),
        'total_revenue' => floatval($statsRow['total_revenue'] ?? 0)
    ];
    
} catch (Exception $e) {
    error_log("Error in bookings_manage.php: " . $e->getMessage());
    $bookings = [];
    $stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0, 'today' => 0, 'this_month' => 0, 'total_revenue' => 0];
    $totalBookings = 0;
    $totalPages = 0;
    $_SESSION['flash_message'] = 'Error loading bookings';
    $_SESSION['flash_type'] = 'danger';
}

// Auto-complete expired bookings (run on page load)
// Booking is completed when the booking period ends (based on duration_months)
if (empty($_GET['action']) && empty($_GET['id'])) {
    try {
        $db = db();
        // Check if duration_months column exists before using it
        $hasDurationMonths = false;
        try {
            $db->fetchValue("SELECT duration_months FROM bookings LIMIT 1");
            $hasDurationMonths = true;
        } catch (Exception $e) {
            $hasDurationMonths = false;
        }
        
        if ($hasDurationMonths) {
            // Column exists, use it
            $db->execute(
                "UPDATE bookings 
                 SET status = 'completed', updated_at = NOW()
                 WHERE status = 'confirmed' 
                 AND DATE_ADD(booking_start_date, INTERVAL COALESCE(duration_months, 1) MONTH) <= CURDATE()"
            );
        } else {
            // Column doesn't exist, use default 1 month
            $db->execute(
                "UPDATE bookings 
                 SET status = 'completed', updated_at = NOW()
                 WHERE status = 'confirmed' 
                 AND DATE_ADD(booking_start_date, INTERVAL 1 MONTH) <= CURDATE()"
            );
        }
    } catch (Exception $e) {
        // Silently fail - don't interrupt page load
    }
}

$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Bookings Management</h1>
            <p class="admin-page-subtitle text-muted">View and manage all PG bookings</p>
        </div>
    </div>
</div>

<?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Bookings</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Pending</div>
                <div class="admin-stat-card-value"><?= number_format($stats['pending']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Confirmed</div>
                <div class="admin-stat-card-value"><?= number_format($stats['confirmed']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Completed</div>
                <div class="admin-stat-card-value"><?= number_format($stats['completed']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Today</div>
                <div class="admin-stat-card-value"><?= number_format($stats['today']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                <i class="bi bi-calendar-month"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">This Month</div>
                <div class="admin-stat-card-value"><?= number_format($stats['this_month']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Revenue</div>
                <div class="admin-stat-card-value">₹<?= number_format($stats['total_revenue'], 0) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="<?= htmlspecialchars(app_url('admin/bookings')) ?>" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" 
                       class="form-control form-control-sm" 
                       name="search" 
                       placeholder="Search by user name, email, listing..." 
                       value="<?= htmlspecialchars($search) ?>"
                       style="height: 38px;">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-control form-control-sm filter-select" name="status" style="height: 38px;">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort By</label>
                <select class="form-control form-control-sm filter-select" name="sort" style="height: 38px;">
                    <option value="b.created_at" <?= $sort === 'b.created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="b.booking_start_date" <?= $sort === 'b.booking_start_date' ? 'selected' : '' ?>>Start Date</option>
                    <option value="u.name" <?= $sort === 'u.name' ? 'selected' : '' ?>>User Name</option>
                    <option value="l.title" <?= $sort === 'l.title' ? 'selected' : '' ?>>Listing</option>
                    <option value="b.status" <?= $sort === 'b.status' ? 'selected' : '' ?>>Status</option>
                    <option value="b.total_amount" <?= $sort === 'b.total_amount' ? 'selected' : '' ?>>Amount</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <select class="form-control form-control-sm filter-select" name="order" style="height: 38px;">
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100" style="height: 38px;">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bookings Table -->
<div class="admin-card">
    <div class="admin-card-body p-0">
        <?php if (empty($bookings)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                <p class="text-muted mt-3">No bookings found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-hover admin-table mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Listing</th>
                            <th>Start Date</th>
                            <th>Room Type</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $durationMonths = isset($booking['duration_months']) ? (int)$booking['duration_months'] : 1;
                            if ($durationMonths < 1) $durationMonths = 1;
                            $startDate = new DateTime($booking['booking_start_date']);
                            $endDate = clone $startDate;
                            $endDate->modify("+{$durationMonths} months");
                            $endDate->modify('-1 day');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($booking['id']) ?></td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($booking['user_name'] ?? 'N/A') ?></strong>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($booking['user_email'] ?? '') ?>
                                        </div>
                                        <?php if (!empty($booking['user_phone'])): ?>
                                            <div class="small text-muted">
                                                <i class="bi bi-telephone"></i> <?= htmlspecialchars($booking['user_phone']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($booking['listing_title'] ?? 'N/A') ?></strong>
                                        <?php if (!empty($booking['listing_city'])): ?>
                                            <div class="small text-muted">
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($booking['listing_city']) ?>
                                                <?php if (!empty($booking['listing_pincode'])): ?>
                                                    - <?= htmlspecialchars($booking['listing_pincode']) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= date('F 1, Y', strtotime($booking['booking_start_date'])) ?></strong>
                                        <div class="small text-muted">
                                            to <?= $endDate->format('F d, Y') ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($booking['room_type'] ?? 'N/A') ?></td>
                                <td><strong>₹<?= number_format($booking['total_amount'], 2) ?></strong></td>
                                <td>
                                    <?php if ($booking['payment_status'] === 'success'): ?>
                                        <span class="badge bg-success">Paid</span>
                                        <?php if ($booking['provider']): ?>
                                            <div class="small text-muted"><?= strtoupper($booking['provider']) ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($booking['payment_status'] === 'initiated'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Paid</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'confirmed' => 'success',
                                        'completed' => 'info',
                                        'cancelled' => 'danger'
                                    ];
                                    $statusColor = $statusColors[$booking['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        <?= date('d M Y', strtotime($booking['created_at'])) ?>
                                        <div class="text-muted">
                                            <?= date('h:i A', strtotime($booking['created_at'])) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewModal<?= $booking['id'] ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if (!empty($booking['invoice_id'])): ?>
                                            <a href="<?= app_url('invoice?id=' . $booking['invoice_id']) ?>" 
                                               class="btn btn-outline-success" 
                                               target="_blank"
                                               title="View Invoice">
                                                <i class="bi bi-receipt"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-secondary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#statusModal<?= $booking['id'] ?>">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-lg-none">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $durationMonths = isset($booking['duration_months']) ? (int)$booking['duration_months'] : 1;
                    if ($durationMonths < 1) $durationMonths = 1;
                    $startDate = new DateTime($booking['booking_start_date']);
                    $endDate = clone $startDate;
                    $endDate->modify("+{$durationMonths} months");
                    $endDate->modify('-1 day');
                    ?>
                    <div class="card mb-3 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($booking['user_name'] ?? 'N/A') ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($booking['user_email'] ?? '') ?></small>
                                </div>
                                <?php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'confirmed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $statusColor = $statusColors[$booking['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $statusColor ?>">
                                    <?= ucfirst($booking['status']) ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <strong><?= htmlspecialchars($booking['listing_title'] ?? 'N/A') ?></strong>
                                <?php if (!empty($booking['listing_city'])): ?>
                                    <div class="small text-muted">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($booking['listing_city']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-2">
                                <div class="small">
                                    <i class="bi bi-calendar"></i> <?= date('F 1, Y', strtotime($booking['booking_start_date'])) ?> to <?= $endDate->format('F d, Y') ?>
                                </div>
                                <div class="small">
                                    <i class="bi bi-door-open"></i> <?= htmlspecialchars($booking['room_type'] ?? 'N/A') ?>
                                </div>
                                <div class="small">
                                    <strong>₹<?= number_format($booking['total_amount'], 2) ?></strong>
                                    <?php if ($booking['payment_status'] === 'success'): ?>
                                        <span class="badge bg-success ms-2">Paid</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary flex-fill" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModal<?= $booking['id'] ?>">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <?php if (!empty($booking['invoice_id'])): ?>
                                    <a href="<?= app_url('invoice?id=' . $booking['invoice_id']) ?>" 
                                       class="btn btn-sm btn-outline-success flex-fill" 
                                       target="_blank">
                                        <i class="bi bi-receipt"></i> Invoice
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#statusModal<?= $booking['id'] ?>">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="admin-card-body border-top">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- View Detail Modals -->
<?php foreach ($bookings as $booking): ?>
    <?php
    $durationMonths = isset($booking['duration_months']) ? (int)$booking['duration_months'] : 1;
    if ($durationMonths < 1) $durationMonths = 1;
    $startDate = new DateTime($booking['booking_start_date']);
    $endDate = clone $startDate;
    $endDate->modify("+{$durationMonths} months");
    $endDate->modify('-1 day');
    ?>
    <div class="modal fade" id="viewModal<?= $booking['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Booking Details #<?= $booking['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">User Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($booking['user_name'] ?? 'N/A') ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($booking['user_email'] ?? 'N/A') ?></p>
                            <?php if (!empty($booking['user_phone'])): ?>
                                <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($booking['user_phone']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Listing Information</h6>
                            <p class="mb-1"><strong>Title:</strong> <?= htmlspecialchars($booking['listing_title'] ?? 'N/A') ?></p>
                            <?php if (!empty($booking['listing_city'])): ?>
                                <p class="mb-1">
                                    <strong>Location:</strong> 
                                    <?= htmlspecialchars($booking['listing_city']) ?>
                                    <?php if (!empty($booking['listing_pincode'])): ?>
                                        - <?= htmlspecialchars($booking['listing_pincode']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Booking Details</h6>
                            <p class="mb-1"><strong>Start Date:</strong> <?= date('F 1, Y', strtotime($booking['booking_start_date'])) ?></p>
                            <p class="mb-1"><strong>End Date:</strong> <?= $endDate->format('F d, Y') ?></p>
                            <p class="mb-1"><strong>Duration:</strong> <?= $durationMonths ?> Month<?= $durationMonths > 1 ? 's' : '' ?></p>
                            <p class="mb-1"><strong>Room Type:</strong> <?= htmlspecialchars($booking['room_type'] ?? 'N/A') ?></p>
                            <p class="mb-1"><strong>Amount (Security Deposit):</strong> ₹<?= number_format($booking['total_amount'], 2) ?></p>
                            <p class="mb-1">
                                <strong>Status:</strong> 
                                <?php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'confirmed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $statusColor = $statusColors[$booking['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($booking['status']) ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Payment Information</h6>
                            <?php if ($booking['payment_id']): ?>
                                <p class="mb-1"><strong>Payment Status:</strong> 
                                    <span class="badge bg-<?= $booking['payment_status'] === 'success' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($booking['payment_status'] ?? 'N/A') ?>
                                    </span>
                                </p>
                                <?php if ($booking['provider']): ?>
                                    <p class="mb-1"><strong>Provider:</strong> <?= strtoupper($booking['provider']) ?></p>
                                <?php endif; ?>
                                <?php if ($booking['provider_payment_id']): ?>
                                    <p class="mb-1"><strong>Transaction ID:</strong> <?= htmlspecialchars($booking['provider_payment_id']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($booking['invoice_id'])): ?>
                                    <p class="mb-1">
                                        <strong>Invoice:</strong> 
                                        <a href="<?= app_url('invoice?id=' . $booking['invoice_id']) ?>" 
                                           target="_blank" 
                                           class="text-decoration-none">
                                            <?= htmlspecialchars($booking['invoice_number']) ?>
                                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="mb-1 text-muted">No payment record found</p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($booking['special_requests'])): ?>
                            <div class="col-12">
                                <h6 class="text-muted mb-2">Special Requests</h6>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($booking['special_requests'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <h6 class="text-muted mb-2">Timestamps</h6>
                            <p class="mb-1"><strong>Created:</strong> <?= date('d M Y h:i A', strtotime($booking['created_at'])) ?></p>
                            <?php if ($booking['updated_at'] !== $booking['created_at']): ?>
                                <p class="mb-1"><strong>Updated:</strong> <?= date('d M Y h:i A', strtotime($booking['updated_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="statusModal<?= $booking['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Booking #<?= $booking['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="?action=update_status&id=<?= $booking['id'] ?>" class="mb-4">
                        <h6 class="mb-3">Update Status</h6>
                        <div class="mb-3">
                            <label for="new_status<?= $booking['id'] ?>" class="form-label">Status</label>
                            <select class="form-control filter-select" id="new_status<?= $booking['id'] ?>" name="new_status" required>
                                <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="completed" <?= $booking['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                    </form>
                    
                    <hr>
                    
                    <form method="POST" action="?action=update_notes&id=<?= $booking['id'] ?>" class="mb-4">
                        <h6 class="mb-3">Special Requests / Notes</h6>
                        <div class="mb-3">
                            <label for="admin_notes<?= $booking['id'] ?>" class="form-label">Notes</label>
                            <textarea class="form-control" id="admin_notes<?= $booking['id'] ?>" name="admin_notes" rows="3" 
                                      placeholder="Add notes about this booking..."><?= htmlspecialchars($booking['special_requests'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm">Save Notes</button>
                    </form>
                    
                    <hr>
                    
                    <form method="POST" action="?action=delete&id=<?= $booking['id'] ?>" 
                          onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">
                        <h6 class="mb-3 text-danger">Danger Zone</h6>
                        <input type="hidden" name="confirm_delete" value="1">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash"></i> Delete Booking
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

