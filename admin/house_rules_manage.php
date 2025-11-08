<?php
/**
 * Admin House Rules Management Page
 * Create, edit, and manage house rules
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
                    jsonError('House rule name is required.', [], 400);
                }

                // Check if house rule already exists (case-insensitive, trimmed)
                $existing = $db->fetchOne(
                    "SELECT id, name FROM house_rules WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))", 
                    [$name]
                );
                if ($existing) {
                    jsonError('This house rule already exists (found: "' . htmlspecialchars($existing['name']) . '").', [], 400);
                }

                $db->execute("INSERT INTO house_rules (name) VALUES (?)", [$name]);
                $ruleId = $db->lastInsertId();

                // Log admin action
                try {
                    $db->execute(
                        "INSERT INTO admin_actions (admin_id, action, target_type, target_id)
                         VALUES (?, ?, 'house_rule', ?)",
                        [$currentUser['id'], 'Created house rule: ' . $name, 'house_rule', $ruleId]
                    );
                } catch (Exception $e) {
                    error_log("Failed to log admin action: " . $e->getMessage());
                }

                $db->commit();
                jsonSuccess('House rule added successfully.');
                break;

            case 'edit':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');

                if ($id <= 0) {
                    jsonError('Invalid house rule ID.', [], 400);
                }
                if (empty($name)) {
                    jsonError('House rule name is required.', [], 400);
                }

                // Check if another house rule with same name exists (case-insensitive)
                $existing = $db->fetchOne("SELECT id FROM house_rules WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ?", [$name, $id]);
                if ($existing) {
                    jsonError('Another house rule with this name already exists.', [], 400);
                }

                $db->execute("UPDATE house_rules SET name = ? WHERE id = ?", [$name, $id]);

                // Log admin action
                try {
                    $db->execute(
                        "INSERT INTO admin_actions (admin_id, action, target_type, target_id)
                         VALUES (?, ?, 'house_rule', ?)",
                        [$currentUser['id'], 'Updated house rule ID ' . $id . ' to: ' . $name, 'house_rule', $id]
                    );
                } catch (Exception $e) {
                    error_log("Failed to log admin action: " . $e->getMessage());
                }

                $db->commit();
                jsonSuccess('House rule updated successfully.');
                break;

            case 'delete':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    jsonError('Invalid house rule ID.', [], 400);
                }

                // Check if house rule is being used in any listings
                $usageCount = $db->fetchValue(
                    "SELECT COUNT(*) FROM listing_rules WHERE rule_id = ?",
                    [$id]
                );

                if ($usageCount > 0) {
                    jsonError("Cannot delete house rule. It is being used in {$usageCount} listing(s).", [], 400);
                }

                $ruleName = $db->fetchValue("SELECT name FROM house_rules WHERE id = ?", [$id]);
                $db->execute("DELETE FROM house_rules WHERE id = ?", [$id]);

                // Log admin action
                try {
                    $db->execute(
                        "INSERT INTO admin_actions (admin_id, action, target_type, target_id)
                         VALUES (?, ?, 'house_rule', ?)",
                        [$currentUser['id'], 'Deleted house rule: ' . $ruleName, 'house_rule', $id]
                    );
                } catch (Exception $e) {
                    error_log("Failed to log admin action: " . $e->getMessage());
                }

                $db->commit();
                jsonSuccess('House rule deleted successfully.');
                break;

            default:
                jsonError('Invalid action.', [], 400);
        }
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        error_log("Database error in house_rules_manage.php: " . $e->getMessage());
        jsonError('Database error: ' . (getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Please try again'), [], 500);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        error_log("Error in house_rules_manage.php: " . $e->getMessage());
        jsonError('An error occurred: ' . $e->getMessage(), [], 500);
    }
    exit; // Important: exit after JSON response
}

// Now include header for regular page display
$pageTitle = "Manage House Rules";
require __DIR__ . '/../app/includes/admin_header.php';

// Fetch house rules with usage count
try {
    $db = db();
    $houseRules = $db->fetchAll(
        "SELECT hr.id, hr.name, 
         COUNT(lr.listing_id) as usage_count
         FROM house_rules hr
         LEFT JOIN listing_rules lr ON hr.id = lr.rule_id
         GROUP BY hr.id
         ORDER BY hr.name"
    );

    $totalHouseRules = count($houseRules);
    $rulesInUse = $db->fetchValue("SELECT COUNT(DISTINCT rule_id) FROM listing_rules");
} catch (Exception $e) {
    error_log("Error fetching house rules: " . $e->getMessage());
    $houseRules = [];
    $totalHouseRules = 0;
    $rulesInUse = 0;
    setFlashMessage("Error loading house rules: " . $e->getMessage(), 'error');
}

$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Manage House Rules</h1>
            <p class="admin-page-subtitle text-muted">Create and manage property house rules</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                <i class="bi bi-plus-circle me-2"></i>Add New House Rule
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
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total House Rules</div>
                <div class="admin-stat-card-value"><?= number_format($totalHouseRules) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-building"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">House Rules in Use</div>
                <div class="admin-stat-card-value"><?= number_format($rulesInUse) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- House Rules Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
            <i class="bi bi-table me-2"></i>All House Rules (<?= number_format($totalHouseRules) ?>)
        </h5>
    </div>
    <div class="admin-card-body p-0">
        <div class="table-responsive">
            <table class="table admin-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>House Rule Name</th>
                        <th>Used In Listings</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($houseRules)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                                No house rules found. Click "Add New House Rule" to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($houseRules as $rule): ?>
                            <tr>
                                <td><?= htmlspecialchars($rule['id']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($rule['name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $rule['usage_count'] > 0 ? 'success' : 'secondary' ?>">
                                        <?= $rule['usage_count'] ?> listing(s)
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" 
                                            class="btn btn-outline-primary btn-sm" 
                                            onclick="editRule(<?= $rule['id'] ?>, '<?= htmlspecialchars($rule['name'], ENT_QUOTES) ?>')"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($rule['usage_count'] == 0): ?>
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm" 
                                                onclick="deleteRule(<?= $rule['id'] ?>, '<?= htmlspecialchars($rule['name'], ENT_QUOTES) ?>')"
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

<!-- Add House Rule Modal -->
<div class="modal fade" id="addRuleModal" tabindex="-1" aria-labelledby="addRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addRuleForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRuleModalLabel">Add New House Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="ruleName" class="form-label">House Rule Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ruleName" name="name" required 
                               placeholder="e.g., No Smoking, No Pets, No Loud Music">
                        <div class="invalid-feedback"></div>
                    </div>
                    <input type="hidden" name="action" value="add">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" id="addSpinner" role="status"></span>
                        Add House Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit House Rule Modal -->
<div class="modal fade" id="editRuleModal" tabindex="-1" aria-labelledby="editRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editRuleForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRuleModalLabel">Edit House Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editRuleName" class="form-label">House Rule Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editRuleName" name="name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editRuleId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" id="editSpinner" role="status"></span>
                        Update House Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete House Rule Modal -->
<div class="modal fade" id="deleteRuleModal" tabindex="-1" aria-labelledby="deleteRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="deleteRuleForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteRuleModalLabel">Delete House Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the house rule <strong id="deleteRuleName"></strong>?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteRuleId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <span class="spinner-border spinner-border-sm d-none" id="deleteSpinner" role="status"></span>
                        Delete House Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Function to open edit modal
function editRule(id, name) {
    document.getElementById('editRuleId').value = id;
    document.getElementById('editRuleName').value = name;
    const modal = new bootstrap.Modal(document.getElementById('editRuleModal'));
    modal.show();
}

// Function to open delete modal
function deleteRule(id, name) {
    document.getElementById('deleteRuleId').value = id;
    document.getElementById('deleteRuleName').textContent = name;
    const modal = new bootstrap.Modal(document.getElementById('deleteRuleModal'));
    modal.show();
}

// Handle form submissions via AJAX
async function submitRuleForm(form, spinnerId) {
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
document.getElementById('addRuleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitRuleForm(this, 'addSpinner');
});

document.getElementById('editRuleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitRuleForm(this, 'editSpinner');
});

document.getElementById('deleteRuleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitRuleForm(this, 'deleteSpinner');
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

