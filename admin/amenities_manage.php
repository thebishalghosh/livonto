<?php
/**
 * Admin Amenities Management Page
 * Create, edit, and manage amenities
 */

// Start session and load config/functions BEFORE handling POST
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Ensure admin is logged in
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// Handle actions (Add, Edit, Delete) - MUST be before header output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set JSON header for AJAX responses
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $currentUser = getCurrentUser();

    try {
        $db = db();
        $db->beginTransaction();

        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    jsonError('Amenity name is required.', [], 400);
                }

                // Check if amenity already exists (case-insensitive, trimmed)
                $existing = $db->fetchOne(
                    "SELECT id, name FROM amenities WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))", 
                    [$name]
                );
                if ($existing) {
                    jsonError('This amenity already exists (found: "' . htmlspecialchars($existing['name']) . '").', [], 400);
                }

                $db->execute("INSERT INTO amenities (name) VALUES (?)", [$name]);
                $amenityId = $db->lastInsertId();

                // Log admin action
                try {
                    $db->execute(
                        "INSERT INTO admin_actions (admin_id, action, target_type, target_id)
                         VALUES (?, ?, 'amenity', ?)",
                        [$currentUser['id'], 'Created amenity: ' . $name, 'amenity', $amenityId]
                    );
                } catch (Exception $e) {
                    error_log("Failed to log admin action: " . $e->getMessage());
                }

                $db->commit();
                jsonSuccess('Amenity added successfully.');
                break;

            case 'edit':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');

                if ($id <= 0) {
                    jsonError('Invalid amenity ID.', [], 400);
                }
                if (empty($name)) {
                    jsonError('Amenity name is required.', [], 400);
                }

                // Check if another amenity with same name exists (case-insensitive)
                $existing = $db->fetchOne("SELECT id FROM amenities WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ?", [$name, $id]);
                if ($existing) {
                    jsonError('Another amenity with this name already exists.', [], 400);
                }

                $db->execute("UPDATE amenities SET name = ? WHERE id = ?", [$name, $id]);

                // Log admin action
                try {
                    $db->execute(
                        "INSERT INTO admin_actions (admin_id, action, target_type, target_id)
                         VALUES (?, ?, 'amenity', ?)",
                        [$currentUser['id'], 'Updated amenity ID ' . $id . ' to: ' . $name, 'amenity', $id]
                    );
                } catch (Exception $e) {
                    error_log("Failed to log admin action: " . $e->getMessage());
                }

                $db->commit();
                jsonSuccess('Amenity updated successfully.');
                break;

            case 'delete':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    jsonError('Invalid amenity ID.', [], 400);
                }

                // Check if amenity is being used in any listings
                $usageCount = $db->fetchValue(
                    "SELECT COUNT(*) FROM listing_amenities WHERE amenity_id = ?",
                    [$id]
                );

                if ($usageCount > 0) {
                    jsonError("Cannot delete amenity. It is being used in {$usageCount} listing(s).", [], 400);
                }

                $amenityName = $db->fetchValue("SELECT name FROM amenities WHERE id = ?", [$id]);
                $db->execute("DELETE FROM amenities WHERE id = ?", [$id]);

                // Log admin action
                try {
                    $db->execute(
                        "INSERT INTO admin_actions (admin_id, action, target_type, target_id)
                         VALUES (?, ?, 'amenity', ?)",
                        [$currentUser['id'], 'Deleted amenity: ' . $amenityName, 'amenity', $id]
                    );
                } catch (Exception $e) {
                    error_log("Failed to log admin action: " . $e->getMessage());
                }

                $db->commit();
                jsonSuccess('Amenity deleted successfully.');
                break;

            default:
                jsonError('Invalid action.', [], 400);
        }
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        error_log("Database error in amenities_manage.php: " . $e->getMessage());
        jsonError('Database error: ' . (getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Please try again'), [], 500);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        error_log("Error in amenities_manage.php: " . $e->getMessage());
        jsonError('An error occurred: ' . $e->getMessage(), [], 500);
    }
    exit; // Important: exit after JSON response
}

// Now include header for regular page display
$pageTitle = "Manage Amenities";
require __DIR__ . '/../app/includes/admin_header.php';

// Fetch amenities with usage count
try {
    $db = db();
    $amenities = $db->fetchAll(
        "SELECT a.id, a.name, 
         COUNT(la.listing_id) as usage_count
         FROM amenities a
         LEFT JOIN listing_amenities la ON a.id = la.amenity_id
         GROUP BY a.id
         ORDER BY a.name"
    );

    $totalAmenities = count($amenities);
    $amenitiesInUse = $db->fetchValue("SELECT COUNT(DISTINCT amenity_id) FROM listing_amenities");
} catch (Exception $e) {
    error_log("Error fetching amenities: " . $e->getMessage());
    $amenities = [];
    $totalAmenities = 0;
    $amenitiesInUse = 0;
    setFlashMessage("Error loading amenities: " . $e->getMessage(), 'error');
}

$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Manage Amenities</h1>
            <p class="admin-page-subtitle text-muted">Create and manage property amenities</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAmenityModal">
                <i class="bi bi-plus-circle me-2"></i>Add New Amenity
            </button>
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
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-star"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Amenities</div>
                <div class="admin-stat-card-value"><?= number_format($totalAmenities) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-building"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Amenities in Use</div>
                <div class="admin-stat-card-value"><?= number_format($amenitiesInUse) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Amenities Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
            <i class="bi bi-table me-2"></i>All Amenities (<?= number_format($totalAmenities) ?>)
        </h5>
    </div>
    <div class="admin-card-body p-0">
        <div class="table-responsive">
            <table class="table admin-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Amenity Name</th>
                        <th>Used In Listings</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($amenities)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                                No amenities found. Click "Add New Amenity" to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($amenities as $amenity): ?>
                            <tr>
                                <td><?= htmlspecialchars($amenity['id']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($amenity['name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $amenity['usage_count'] > 0 ? 'success' : 'secondary' ?>">
                                        <?= $amenity['usage_count'] ?> listing(s)
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" 
                                            class="btn btn-outline-primary btn-sm" 
                                            onclick="editAmenity(<?= $amenity['id'] ?>, '<?= htmlspecialchars($amenity['name'], ENT_QUOTES) ?>')"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($amenity['usage_count'] == 0): ?>
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm" 
                                                onclick="deleteAmenity(<?= $amenity['id'] ?>, '<?= htmlspecialchars($amenity['name'], ENT_QUOTES) ?>')"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-sm" 
                                                disabled
                                                title="Cannot delete - in use">
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Amenity Modal -->
<div class="modal fade" id="addAmenityModal" tabindex="-1" aria-labelledby="addAmenityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addAmenityForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAmenityModalLabel">Add New Amenity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="amenityName" class="form-label">Amenity Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="amenityName" name="name" required 
                               placeholder="e.g., WiFi, AC, Parking, Laundry">
                        <div class="invalid-feedback"></div>
                    </div>
                    <input type="hidden" name="action" value="add">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" id="addSpinner" role="status"></span>
                        Add Amenity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Amenity Modal -->
<div class="modal fade" id="editAmenityModal" tabindex="-1" aria-labelledby="editAmenityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editAmenityForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAmenityModalLabel">Edit Amenity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editAmenityName" class="form-label">Amenity Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editAmenityName" name="name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editAmenityId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" id="editSpinner" role="status"></span>
                        Update Amenity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Amenity Modal -->
<div class="modal fade" id="deleteAmenityModal" tabindex="-1" aria-labelledby="deleteAmenityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="deleteAmenityForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAmenityModalLabel">Delete Amenity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the amenity <strong id="deleteAmenityName"></strong>?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteAmenityId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <span class="spinner-border spinner-border-sm d-none" id="deleteSpinner" role="status"></span>
                        Delete Amenity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Function to open edit modal
function editAmenity(id, name) {
    document.getElementById('editAmenityId').value = id;
    document.getElementById('editAmenityName').value = name;
    const modal = new bootstrap.Modal(document.getElementById('editAmenityModal'));
    modal.show();
}

// Function to open delete modal
function deleteAmenity(id, name) {
    document.getElementById('deleteAmenityId').value = id;
    document.getElementById('deleteAmenityName').textContent = name;
    const modal = new bootstrap.Modal(document.getElementById('deleteAmenityModal'));
    modal.show();
}

// Handle form submissions via AJAX
async function submitAmenityForm(form, spinnerId) {
    const formData = new FormData(form);
    const spinner = document.getElementById(spinnerId);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    if (spinner) spinner.classList.remove('d-none');
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // Show success message and reload
            showFlashMessage(data.message, 'success');
            setTimeout(() => window.location.reload(), 500);
        } else {
            // Show errors
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    const input = form.querySelector(`[name="${field}"]`);
                    const feedback = input?.nextElementSibling;
                    if (input) input.classList.add('is-invalid');
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = data.errors[field];
                    }
                });
            }
            showFlashMessage(data.message || 'An error occurred.', 'danger');
            submitBtn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
        }
    } catch (error) {
        console.error('Error submitting form:', error);
        showFlashMessage('Server error. Please try again.', 'danger');
        submitBtn.disabled = false;
        if (spinner) spinner.classList.add('d-none');
    }
}

// Add form event listeners
document.getElementById('addAmenityForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitAmenityForm(this, 'addSpinner');
});

document.getElementById('editAmenityForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitAmenityForm(this, 'editSpinner');
});

document.getElementById('deleteAmenityForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitAmenityForm(this, 'deleteSpinner');
});

function showFlashMessage(message, type) {
    const container = document.querySelector('.admin-main-content') || document.body;
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    container.insertAdjacentHTML('afterbegin', alertHtml);
}
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

