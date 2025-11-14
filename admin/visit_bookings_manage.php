<?php
/**
 * Admin Visit Bookings Management Page
 * View and manage all visit booking requests
 */

// Start session and load config/functions BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';

// Check admin authentication BEFORE processing POST
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// Handle actions BEFORE header output
$action = $_GET['action'] ?? '';
$bookingId = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action && $bookingId) {
    try {
        $db = db();
        
        // Verify booking exists before any operation
        $booking = $db->fetchOne("SELECT id FROM visit_bookings WHERE id = ?", [$bookingId]);
        if (!$booking) {
            $_SESSION['flash_message'] = 'Visit booking not found';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . app_url('admin/visit-bookings'));
            exit;
        }
        
        // Handle POST actions (update status, add notes, delete, etc.)
        if ($action === 'update_status' && isset($_POST['new_status'])) {
            $newStatus = $_POST['new_status'];
            if (in_array($newStatus, ['pending', 'confirmed', 'cancelled', 'completed'])) {
                // Get user email before updating status
                $bookingData = $db->fetchOne(
                    "SELECT u.email as user_email 
                     FROM visit_bookings vb
                     LEFT JOIN users u ON vb.user_id = u.id
                     WHERE vb.id = ?",
                    [$bookingId]
                );
                
                // Update booking status
                $db->execute("UPDATE visit_bookings SET status = ? WHERE id = ?", [$newStatus, $bookingId]);
                error_log("Admin updated visit booking status: ID {$bookingId} to {$newStatus} by Admin ID {$_SESSION['user_id']}");
                
                // Send email notification to user
                if (!empty($bookingData['user_email'])) {
                    require_once __DIR__ . '/../app/includes/send_status_mail.php';
                    sendStatusMail($bookingData['user_email'], $newStatus, $bookingId);
                }
                
                // Send admin notification for visit booking status change (if completed or cancelled)
                if (in_array($newStatus, ['completed', 'cancelled'])) {
                    try {
                        require_once __DIR__ . '/../app/email_helper.php';
                        $visitInfo = $db->fetchOne(
                            "SELECT vb.id, vb.preferred_date, vb.preferred_time, u.name as user_name, u.email as user_email, l.title as listing_title
                             FROM visit_bookings vb
                             LEFT JOIN users u ON vb.user_id = u.id
                             LEFT JOIN listings l ON vb.listing_id = l.id
                             WHERE vb.id = ?",
                            [$bookingId]
                        );
                        $baseUrl = app_url('');
                        sendAdminNotification(
                            "Visit Booking {$newStatus} - Visit #{$bookingId}",
                            "Visit Booking " . ucfirst($newStatus),
                            "A visit booking has been marked as {$newStatus}.",
                            [
                                'Visit ID' => '#' . $bookingId,
                                'User Name' => $visitInfo['user_name'] ?? 'Unknown',
                                'User Email' => $visitInfo['user_email'] ?? 'N/A',
                                'Property' => $visitInfo['listing_title'] ?? 'Unknown',
                                'Visit Date' => $visitInfo['preferred_date'] ? date('F d, Y', strtotime($visitInfo['preferred_date'])) : 'N/A',
                                'Visit Time' => $visitInfo['preferred_time'] ? date('h:i A', strtotime($visitInfo['preferred_time'])) : 'N/A',
                                'Status' => ucfirst($newStatus)
                            ],
                            $baseUrl . 'admin/visit-bookings',
                            'View Visit Booking'
                        );
                    } catch (Exception $e) {
                        error_log("Failed to send admin notification for visit booking status change: " . $e->getMessage());
                    }
                }
                
                $_SESSION['flash_message'] = 'Visit booking status updated successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/visit-bookings'));
                exit;
            }
        } elseif ($action === 'update_notes' && isset($_POST['admin_notes'])) {
            $adminNotes = trim($_POST['admin_notes']);
            $db->execute("UPDATE visit_bookings SET admin_notes = ? WHERE id = ?", [$adminNotes ?: null, $bookingId]);
            error_log("Admin updated visit booking notes: ID {$bookingId} by Admin ID {$_SESSION['user_id']}");
            $_SESSION['flash_message'] = 'Admin notes updated successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/visit-bookings'));
            exit;
        } elseif ($action === 'delete' && isset($_POST['confirm_delete'])) {
            $db->execute("DELETE FROM visit_bookings WHERE id = ?", [$bookingId]);
            error_log("Admin deleted visit booking: ID {$bookingId} by Admin ID {$_SESSION['user_id']}");
            $_SESSION['flash_message'] = 'Visit booking deleted successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/visit-bookings'));
            exit;
        }
    } catch (Exception $e) {
        error_log("Error in visit booking action: " . $e->getMessage());
        $_SESSION['flash_message'] = 'Error processing request: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
    }
}

// Now include header and display page
$pageTitle = "Visit Bookings Management";
require __DIR__ . '/../app/includes/admin_header.php';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = db();
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR l.title LIKE ? OR vb.message LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($status) && in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
        $where[] = "vb.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get total count
    $totalBookings = $db->fetchValue(
        "SELECT COUNT(*) FROM visit_bookings vb
         LEFT JOIN users u ON vb.user_id = u.id
         LEFT JOIN listings l ON vb.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         {$whereClause}",
        $params
    ) ?: 0;
    
    $totalPages = ceil($totalBookings / $perPage);
    
    // Validate sort and order
    $allowedSorts = ['vb.id', 'vb.preferred_date', 'vb.preferred_time', 'vb.status', 'vb.created_at', 'u.name', 'l.title'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'vb.created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Fetch visit bookings with user and listing info
    $bookings = $db->fetchAll(
        "SELECT vb.id, vb.listing_id, vb.user_id, vb.preferred_date, vb.preferred_time, 
                vb.message, vb.status, vb.admin_notes, vb.created_at, vb.updated_at,
                u.name as user_name, u.email as user_email, u.phone as user_phone,
                l.title as listing_title, loc.city as listing_city, loc.pin_code as listing_pin_code
         FROM visit_bookings vb
         LEFT JOIN users u ON vb.user_id = u.id
         LEFT JOIN listings l ON vb.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         {$whereClause}
         ORDER BY {$sort} {$order}
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    // Get statistics (optimized with single query)
    $statsRow = $db->fetchOne(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month
         FROM visit_bookings"
    );
    $stats = [
        'total' => intval($statsRow['total'] ?? 0),
        'pending' => intval($statsRow['pending'] ?? 0),
        'confirmed' => intval($statsRow['confirmed'] ?? 0),
        'completed' => intval($statsRow['completed'] ?? 0),
        'cancelled' => intval($statsRow['cancelled'] ?? 0),
        'today' => intval($statsRow['today'] ?? 0),
        'this_month' => intval($statsRow['this_month'] ?? 0)
    ];
    
} catch (Exception $e) {
    error_log("Error in visit_bookings_manage.php: " . $e->getMessage());
    $bookings = [];
    $stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0, 'today' => 0, 'this_month' => 0];
    $totalBookings = 0;
    $totalPages = 0;
    $_SESSION['flash_message'] = 'Error loading visit bookings';
    $_SESSION['flash_type'] = 'danger';
}

// Get flash message
$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Visit Bookings Management</h1>
            <p class="admin-page-subtitle text-muted">View and manage all property visit requests</p>
        </div>
    </div>
</div>

<!-- Flash Message -->
<?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<!-- Row 1: Main Statistics -->
<div class="row g-3 mb-3">
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Visits</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
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
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
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
</div>

<!-- Row 2: Additional Statistics -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
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
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
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
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
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
</div>

<!-- Filters and Search -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="<?= htmlspecialchars(app_url('admin/visit-bookings')) ?>" class="row g-3">
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
                    <option value="vb.created_at" <?= $sort === 'vb.created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="vb.preferred_date" <?= $sort === 'vb.preferred_date' ? 'selected' : '' ?>>Visit Date</option>
                    <option value="u.name" <?= $sort === 'u.name' ? 'selected' : '' ?>>User Name</option>
                    <option value="l.title" <?= $sort === 'l.title' ? 'selected' : '' ?>>Listing</option>
                    <option value="vb.status" <?= $sort === 'vb.status' ? 'selected' : '' ?>>Status</option>
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

<!-- Visit Bookings Table -->
<div class="admin-card">
    <div class="admin-card-body p-0">
        <?php if (empty($bookings)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                <p class="text-muted mt-3">No visit bookings found</p>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-hover admin-table mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Listing</th>
                            <th>Visit Date & Time</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
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
                                                <?php if (!empty($booking['listing_pin_code'])): ?>
                                                    - <?= htmlspecialchars($booking['listing_pin_code']) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= date('d M Y', strtotime($booking['preferred_date'])) ?></strong>
                                        <div class="small text-muted">
                                            <i class="bi bi-clock"></i> <?= date('h:i A', strtotime($booking['preferred_time'])) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $statusColor = $statusColors[$booking['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($booking['message'])): ?>
                                        <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                              title="<?= htmlspecialchars($booking['message']) ?>">
                                            <?= htmlspecialchars($booking['message']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
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
            
            <!-- Mobile Card View -->
            <div class="d-lg-none">
                <?php foreach ($bookings as $booking): ?>
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
                                    'confirmed' => 'info',
                                    'completed' => 'success',
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
                                    <i class="bi bi-calendar"></i> <?= date('d M Y', strtotime($booking['preferred_date'])) ?>
                                    <i class="bi bi-clock ms-2"></i> <?= date('h:i A', strtotime($booking['preferred_time'])) ?>
                                </div>
                            </div>
                            <?php if (!empty($booking['message'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted"><?= htmlspecialchars($booking['message']) ?></small>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary flex-fill" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModal<?= $booking['id'] ?>">
                                    <i class="bi bi-eye"></i> View
                                </button>
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
            
            <!-- Pagination -->
            <?= renderAdminPagination($page, $totalPages, $totalBookings, $perPage, $offset, null, 'Visit Bookings pagination', true) ?>
        <?php endif; ?>
    </div>
</div>

<!-- View Detail Modals -->
<?php foreach ($bookings as $booking): ?>
    <!-- View Modal -->
    <div class="modal fade" id="viewModal<?= $booking['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Visit Booking Details #<?= $booking['id'] ?></h5>
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
                                    <?php if (!empty($booking['listing_pin_code'])): ?>
                                        - <?= htmlspecialchars($booking['listing_pin_code']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Visit Details</h6>
                            <p class="mb-1"><strong>Date:</strong> <?= date('d M Y', strtotime($booking['preferred_date'])) ?></p>
                            <p class="mb-1"><strong>Time:</strong> <?= date('h:i A', strtotime($booking['preferred_time'])) ?></p>
                            <p class="mb-1">
                                <strong>Status:</strong> 
                                <?php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'confirmed' => 'info',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $statusColor = $statusColors[$booking['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($booking['status']) ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Timestamps</h6>
                            <p class="mb-1"><strong>Created:</strong> <?= date('d M Y h:i A', strtotime($booking['created_at'])) ?></p>
                            <?php if ($booking['updated_at'] !== $booking['created_at']): ?>
                                <p class="mb-1"><strong>Updated:</strong> <?= date('d M Y h:i A', strtotime($booking['updated_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($booking['message'])): ?>
                            <div class="col-12">
                                <h6 class="text-muted mb-2">Message</h6>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($booking['message'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['admin_notes'])): ?>
                            <div class="col-12">
                                <h6 class="text-muted mb-2">Admin Notes</h6>
                                <p class="mb-0 bg-light p-2 rounded"><?= nl2br(htmlspecialchars($booking['admin_notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status/Notes Modal -->
    <div class="modal fade" id="statusModal<?= $booking['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Booking #<?= $booking['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Update Status Form -->
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
                    
                    <!-- Update Notes Form -->
                    <form method="POST" action="?action=update_notes&id=<?= $booking['id'] ?>" class="mb-4">
                        <h6 class="mb-3">Admin Notes</h6>
                        <div class="mb-3">
                            <label for="admin_notes<?= $booking['id'] ?>" class="form-label">Notes</label>
                            <textarea class="form-control" id="admin_notes<?= $booking['id'] ?>" name="admin_notes" rows="3" 
                                      placeholder="Add internal notes about this visit booking..."><?= htmlspecialchars($booking['admin_notes'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm">Save Notes</button>
                    </form>
                    
                    <hr>
                    
                    <!-- Delete Form -->
                    <form method="POST" action="?action=delete&id=<?= $booking['id'] ?>" 
                          onsubmit="return confirm('Are you sure you want to delete this visit booking? This action cannot be undone.');">
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

