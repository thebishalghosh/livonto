<?php
/**
 * Admin Enquiries Management Page
 * View and manage all contact form submissions
 */

// Handle actions BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require __DIR__ . '/../app/config.php';
    // functions.php is already included in admin_header.php
    
    $action = $_GET['action'] ?? '';
    $enquiryId = intval($_GET['id'] ?? 0);
    
    if ($action && $enquiryId) {
        // Handle POST actions (update status, delete, etc.)
        if ($action === 'update_status' && isset($_POST['new_status'])) {
            $newStatus = $_POST['new_status'];
            if (in_array($newStatus, ['new', 'read', 'replied'])) {
                try {
                    $db = db();
                    $db->execute("UPDATE contacts SET status = ? WHERE id = ?", [$newStatus, $enquiryId]);
                    error_log("Admin updated enquiry status: ID {$enquiryId} to {$newStatus} by Admin ID {$_SESSION['user_id']}");
                    $_SESSION['flash_message'] = 'Enquiry status updated successfully';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ' . app_url('admin/enquiries'));
                    exit;
                } catch (Exception $e) {
                    error_log("Error updating enquiry status: " . $e->getMessage());
                    $_SESSION['flash_message'] = 'Error updating enquiry status';
                    $_SESSION['flash_type'] = 'danger';
                }
            }
        } elseif ($action === 'delete' && isset($_POST['confirm_delete'])) {
            try {
                $db = db();
                $db->execute("DELETE FROM contacts WHERE id = ?", [$enquiryId]);
                error_log("Admin deleted enquiry: ID {$enquiryId} by Admin ID {$_SESSION['user_id']}");
                $_SESSION['flash_message'] = 'Enquiry deleted successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/enquiries'));
                exit;
            } catch (Exception $e) {
                error_log("Error deleting enquiry: " . $e->getMessage());
                $_SESSION['flash_message'] = 'Error deleting enquiry';
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
}

// Now include header and display page
$pageTitle = "Enquiries Management";
require __DIR__ . '/../app/includes/admin_header.php';
// functions.php is already included in admin_header.php

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
        $where[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($status) && in_array($status, ['new', 'read', 'replied'])) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get total count
    $totalEnquiries = $db->fetchValue(
        "SELECT COUNT(*) FROM contacts {$whereClause}",
        $params
    ) ?: 0;
    
    $totalPages = ceil($totalEnquiries / $perPage);
    
    // Fetch enquiries
    $enquiries = $db->fetchAll(
        "SELECT id, name, email, subject, message, status, created_at, updated_at
         FROM contacts
         {$whereClause}
         ORDER BY {$sort} {$order}
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    // Get statistics
    $stats = [
        'total' => $db->fetchValue("SELECT COUNT(*) FROM contacts") ?: 0,
        'new' => $db->fetchValue("SELECT COUNT(*) FROM contacts WHERE status = 'new'") ?: 0,
        'read' => $db->fetchValue("SELECT COUNT(*) FROM contacts WHERE status = 'read'") ?: 0,
        'replied' => $db->fetchValue("SELECT COUNT(*) FROM contacts WHERE status = 'replied'") ?: 0,
        'today' => $db->fetchValue("SELECT COUNT(*) FROM contacts WHERE DATE(created_at) = CURDATE()") ?: 0,
        'this_month' => $db->fetchValue("SELECT COUNT(*) FROM contacts WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())") ?: 0
    ];
    
} catch (Exception $e) {
    error_log("Error in enquiries_manage.php: " . $e->getMessage());
    $enquiries = [];
    $stats = ['total' => 0, 'new' => 0, 'read' => 0, 'replied' => 0, 'today' => 0, 'this_month' => 0];
    $totalEnquiries = 0;
    $totalPages = 0;
    $_SESSION['flash_message'] = 'Error loading enquiries';
    $_SESSION['flash_type'] = 'danger';
}

// Get flash message
$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Enquiries Management</h1>
            <p class="admin-page-subtitle text-muted">View and manage all contact form submissions</p>
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
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-envelope"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Enquiries</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-envelope-fill"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">New</div>
                <div class="admin-stat-card-value"><?= number_format($stats['new']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-envelope-open"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Read</div>
                <div class="admin-stat-card-value"><?= number_format($stats['read']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Replied</div>
                <div class="admin-stat-card-value"><?= number_format($stats['replied']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
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
    <div class="col-xl-2 col-md-4 col-6">
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
        <form method="GET" action="<?= htmlspecialchars(app_url('admin/enquiries')) ?>" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" 
                       class="form-control form-control-sm" 
                       name="search" 
                       placeholder="Search by name, email, subject..." 
                       value="<?= htmlspecialchars($search) ?>"
                       style="height: 38px;">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-control form-control-sm filter-select" name="status" style="height: 38px;">
                    <option value="">All Status</option>
                    <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Read</option>
                    <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort By</label>
                <select class="form-control form-control-sm filter-select" name="sort" style="height: 38px;">
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
                    <option value="email" <?= $sort === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
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

<!-- Enquiries Table -->
<div class="admin-card">
    <div class="admin-card-body p-0">
        <?php if (empty($enquiries)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-3">No enquiries found</p>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-hover admin-table mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enquiries as $enquiry): ?>
                            <tr>
                                <td><?= htmlspecialchars($enquiry['id']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($enquiry['name']) ?></strong>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($enquiry['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($enquiry['email']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($enquiry['subject']) ?></td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                          title="<?= htmlspecialchars($enquiry['message']) ?>">
                                        <?= htmlspecialchars(mb_substr($enquiry['message'], 0, 50)) ?><?= mb_strlen($enquiry['message']) > 50 ? '...' : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'new' => 'warning',
                                        'read' => 'info',
                                        'replied' => 'success'
                                    ];
                                    $statusColor = $statusColors[$enquiry['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>">
                                        <?= ucfirst($enquiry['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('M d, Y', strtotime($enquiry['created_at'])) ?><br>
                                        <span class="text-muted"><?= date('h:i A', strtotime($enquiry['created_at'])) ?></span>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#enquiryModal<?= $enquiry['id'] ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <form method="POST" action="?action=update_status&id=<?= $enquiry['id'] ?>" class="d-inline">
                                                        <input type="hidden" name="new_status" value="read">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="bi bi-envelope-open me-2"></i>Mark as Read
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="?action=update_status&id=<?= $enquiry['id'] ?>" class="d-inline">
                                                        <input type="hidden" name="new_status" value="replied">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="bi bi-check-circle me-2"></i>Mark as Replied
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger" 
                                                            onclick="confirmDelete(<?= $enquiry['id'] ?>)">
                                                        <i class="bi bi-trash me-2"></i>Delete
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="d-lg-none">
                <?php foreach ($enquiries as $enquiry): ?>
                    <?php
                    $statusColors = [
                        'new' => 'warning',
                        'read' => 'info',
                        'replied' => 'success'
                    ];
                    $statusColor = $statusColors[$enquiry['status']] ?? 'secondary';
                    ?>
                    <div class="card mb-3 border-start border-4 border-<?= $statusColor ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1 fw-bold"><?= htmlspecialchars($enquiry['name']) ?></h6>
                                    <small class="text-muted">ID: <?= htmlspecialchars($enquiry['id']) ?></small>
                                </div>
                                <span class="badge bg-<?= $statusColor ?>">
                                    <?= ucfirst($enquiry['status']) ?>
                                </span>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted d-block">Email:</small>
                                <a href="mailto:<?= htmlspecialchars($enquiry['email']) ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($enquiry['email']) ?>
                                </a>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted d-block">Subject:</small>
                                <strong><?= htmlspecialchars($enquiry['subject']) ?></strong>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted d-block">Message:</small>
                                <p class="mb-0 small"><?= htmlspecialchars(mb_substr($enquiry['message'], 0, 100)) ?><?= mb_strlen($enquiry['message']) > 100 ? '...' : '' ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('M d, Y h:i A', strtotime($enquiry['created_at'])) ?>
                                </small>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary flex-fill" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#enquiryModal<?= $enquiry['id'] ?>">
                                    <i class="bi bi-eye me-1"></i>View
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                            type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <form method="POST" action="?action=update_status&id=<?= $enquiry['id'] ?>" class="d-inline">
                                                <input type="hidden" name="new_status" value="read">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-envelope-open me-2"></i>Mark as Read
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="?action=update_status&id=<?= $enquiry['id'] ?>" class="d-inline">
                                                <input type="hidden" name="new_status" value="replied">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-check-circle me-2"></i>Mark as Replied
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" 
                                                    onclick="confirmDelete(<?= $enquiry['id'] ?>)">
                                                <i class="bi bi-trash me-2"></i>Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Enquiry Detail Modals -->
            <?php foreach ($enquiries as $enquiry): ?>
                <?php
                $statusColors = [
                    'new' => 'warning',
                    'read' => 'info',
                    'replied' => 'success'
                ];
                $statusColor = $statusColors[$enquiry['status']] ?? 'secondary';
                ?>
                <div class="modal fade" id="enquiryModal<?= $enquiry['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Enquiry Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted">Name</label>
                                        <p class="mb-0"><strong><?= htmlspecialchars($enquiry['name']) ?></strong></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted">Email</label>
                                        <p class="mb-0">
                                            <a href="mailto:<?= htmlspecialchars($enquiry['email']) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($enquiry['email']) ?>
                                            </a>
                                        </p>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-muted">Subject</label>
                                        <p class="mb-0"><strong><?= htmlspecialchars($enquiry['subject']) ?></strong></p>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-muted">Message</label>
                                        <div class="border rounded p-3 bg-light">
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($enquiry['message'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted">Status</label>
                                        <p class="mb-0">
                                            <span class="badge bg-<?= $statusColor ?>">
                                                <?= ucfirst($enquiry['status']) ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted">Submitted</label>
                                        <p class="mb-0">
                                            <?= date('M d, Y h:i A', strtotime($enquiry['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="mailto:<?= htmlspecialchars($enquiry['email']) ?>?subject=Re: <?= urlencode($enquiry['subject']) ?>" 
                                   class="btn btn-primary">
                                    <i class="bi bi-reply me-2"></i>Reply via Email
                                </a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?= renderAdminPagination($page, $totalPages, $totalEnquiries, $perPage, $offset, null, 'Enquiries pagination', true) ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this enquiry? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="confirm_delete" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(enquiryId) {
    const form = document.getElementById('deleteForm');
    form.action = '?action=delete&id=' + enquiryId;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

