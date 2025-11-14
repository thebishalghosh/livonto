<?php
/**
 * Admin Settings Page
 * Manage site-wide settings and configuration
 */

// Start session and load config/functions BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../app/config.php';

// Check admin authentication BEFORE processing POST
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// Handle POST requests (save settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if functions.php is already loaded
        if (!function_exists('getFlashMessage')) {
            require_once __DIR__ . '/../app/functions.php';
        }
        
        $db = db();
        
        // Get all POST data
        $settings = $_POST['settings'] ?? [];
        
        // Validate and save each setting
        foreach ($settings as $key => $value) {
            // Sanitize key
            $key = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
            
            // Sanitize value
            $value = is_array($value) ? json_encode($value) : trim($value);
            
            // Special handling for footer_copyright: replace {YEAR} with current year
            if ($key === 'footer_copyright' && !empty($value)) {
                $value = str_replace('{YEAR}', date('Y'), $value);
            }
            
            // Check if setting exists
            $existing = $db->fetchValue("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
            
            if ($existing) {
                // Update existing setting
                $db->execute("UPDATE site_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?", [$value, $key]);
            } else {
                // Insert new setting
                $db->execute("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
            }
        }
        
        error_log("Admin updated settings by Admin ID {$_SESSION['user_id']}");
        $_SESSION['flash_message'] = 'Settings saved successfully';
        $_SESSION['flash_type'] = 'success';
        header('Location: ' . app_url('admin/settings'));
        exit;
    } catch (Exception $e) {
        error_log("Error saving settings: " . $e->getMessage());
        $_SESSION['flash_message'] = 'Error saving settings: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
    }
}

// Now include header and display page
$pageTitle = "Settings";
require __DIR__ . '/../app/includes/admin_header.php';

// Fetch current settings
try {
    $db = db();
    $settingsRows = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings");
    $currentSettings = [];
    foreach ($settingsRows as $row) {
        $value = $row['setting_value'];
        // Try to decode JSON, otherwise use as string
        $decoded = json_decode($value, true);
        $currentSettings[$row['setting_key']] = ($decoded !== null && json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    $currentSettings = [];
}

// Get flash message
$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Settings</h1>
            <p class="admin-page-subtitle text-muted">Manage site-wide settings and configuration</p>
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

<!-- Settings Form -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
            <i class="bi bi-gear me-2"></i>Site Settings
        </h5>
    </div>
    <div class="admin-card-body">
        <form method="POST" action="<?= htmlspecialchars(app_url('admin/settings')) ?>" id="settingsForm">
            <!-- General Settings -->
            <div class="mb-4">
                <h6 class="mb-3" style="color: var(--primary-700); border-bottom: 2px solid var(--accent); padding-bottom: 8px;">
                    <i class="bi bi-info-circle me-2"></i>General Settings
                </h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="site_name" class="form-label">Site Name</label>
                        <input type="text" class="form-control" id="site_name" name="settings[site_name]" 
                               value="<?= htmlspecialchars($currentSettings['site_name'] ?? 'Livonto') ?>" 
                               placeholder="Livonto">
                        <small class="text-muted">The name of your website</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="site_tagline" class="form-label">Site Tagline</label>
                        <input type="text" class="form-control" id="site_tagline" name="settings[site_tagline]" 
                               value="<?= htmlspecialchars($currentSettings['site_tagline'] ?? 'Find, compare and book PGs instantly') ?>" 
                               placeholder="Find, compare and book PGs instantly">
                        <small class="text-muted">A short tagline for your website</small>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="mb-4">
                <h6 class="mb-3" style="color: var(--primary-700); border-bottom: 2px solid var(--accent); padding-bottom: 8px;">
                    <i class="bi bi-telephone me-2"></i>Contact Information
                </h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="contact_email" name="settings[contact_email]" 
                               value="<?= htmlspecialchars($currentSettings['contact_email'] ?? 'support@pgfinder.com') ?>" 
                               placeholder="support@pgfinder.com">
                        <small class="text-muted">Email address for contact form submissions</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="admin_email" class="form-label">Admin Notification Email</label>
                        <input type="email" class="form-control" id="admin_email" name="settings[admin_email]" 
                               value="<?= htmlspecialchars($currentSettings['admin_email'] ?? 'admin@livonto.com') ?>" 
                               placeholder="admin@livonto.com">
                        <small class="text-muted">Email address to receive admin notifications (new bookings, users, etc.)</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="contact_phone" class="form-label">Contact Phone</label>
                        <input type="tel" class="form-control" id="contact_phone" name="settings[contact_phone]" 
                               value="<?= htmlspecialchars($currentSettings['contact_phone'] ?? '+91 9876543210') ?>" 
                               placeholder="+91 9876543210">
                        <small class="text-muted">Primary contact phone number</small>
                    </div>
                    
                    <div class="col-md-12">
                        <label for="contact_address" class="form-label">Contact Address</label>
                        <textarea class="form-control" id="contact_address" name="settings[contact_address]" rows="3" 
                                  placeholder="Enter your business address"><?= htmlspecialchars($currentSettings['contact_address'] ?? '') ?></textarea>
                        <small class="text-muted">Physical address or office location</small>
                    </div>
                </div>
            </div>

            <!-- Booking & Enquiry Settings -->
            <div class="mb-4">
                <h6 class="mb-3" style="color: var(--primary-700); border-bottom: 2px solid var(--accent); padding-bottom: 8px;">
                    <i class="bi bi-calendar-check me-2"></i>Booking & Enquiry Settings
                </h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="booking_enquiry_phone" class="form-label">Booking Enquiry Phone</label>
                        <input type="tel" class="form-control" id="booking_enquiry_phone" name="settings[booking_enquiry_phone]" 
                               value="<?= htmlspecialchars($currentSettings['booking_enquiry_phone'] ?? '6293010501 | 7047133182 | 9831068248') ?>" 
                               placeholder="6293010501 | 7047133182 | 9831068248">
                        <small class="text-muted">Phone numbers for booking enquiries (separate with |)</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="booking_timings" class="form-label">Booking Timings</label>
                        <input type="text" class="form-control" id="booking_timings" name="settings[booking_timings]" 
                               value="<?= htmlspecialchars($currentSettings['booking_timings'] ?? '10:00 AM to 8:00 PM') ?>" 
                               placeholder="10:00 AM to 8:00 PM">
                        <small class="text-muted">Business hours for booking enquiries</small>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Settings -->
            <div class="mb-4">
                <h6 class="mb-3" style="color: var(--primary-700); border-bottom: 2px solid var(--accent); padding-bottom: 8px;">
                    <i class="bi bi-whatsapp me-2"></i>WhatsApp Settings
                </h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                        <input type="tel" class="form-control" id="whatsapp_number" name="settings[whatsapp_number]" 
                               value="<?= htmlspecialchars($currentSettings['whatsapp_number'] ?? '916293010501') ?>" 
                               placeholder="916293010501">
                        <small class="text-muted">WhatsApp number with country code (e.g., 916293010501 for +91 6293010501)</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="whatsapp_message" class="form-label">Default WhatsApp Message</label>
                        <input type="text" class="form-control" id="whatsapp_message" name="settings[whatsapp_message]" 
                               value="<?= htmlspecialchars($currentSettings['whatsapp_message'] ?? 'Hello! I would like to know more about your PG listings.') ?>" 
                               placeholder="Hello! I would like to know more about your PG listings.">
                        <small class="text-muted">Pre-filled message when users click WhatsApp button</small>
                    </div>
                </div>
            </div>

            <!-- GST Settings -->
            <div class="mb-4">
                <h6 class="mb-3" style="color: var(--primary-700); border-bottom: 2px solid var(--accent); padding-bottom: 8px;">
                    <i class="bi bi-receipt me-2"></i>GST (Tax) Settings
                </h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="gst_enabled" class="form-label">Enable GST</label>
                        <select class="form-control" id="gst_enabled" name="settings[gst_enabled]">
                            <option value="1" <?= (isset($currentSettings['gst_enabled']) && $currentSettings['gst_enabled'] == '1') ? 'selected' : '' ?>>Enabled</option>
                            <option value="0" <?= (!isset($currentSettings['gst_enabled']) || $currentSettings['gst_enabled'] == '0') ? 'selected' : '' ?>>Disabled</option>
                        </select>
                        <small class="text-muted">Enable or disable GST calculation for bookings</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="gst_percentage" class="form-label">GST Percentage (%)</label>
                        <input type="number" class="form-control" id="gst_percentage" name="settings[gst_percentage]" 
                               value="<?= htmlspecialchars($currentSettings['gst_percentage'] ?? '18') ?>" 
                               placeholder="18" step="0.01" min="0" max="100">
                        <small class="text-muted">GST percentage to be applied (e.g., 18 for 18%)</small>
                    </div>
                </div>
            </div>

            <!-- Social Media Links -->
            <div class="mb-4">
                <h6 class="mb-3" style="color: var(--primary-700); border-bottom: 2px solid var(--accent); padding-bottom: 8px;">
                    <i class="bi bi-share me-2"></i>Social Media Links
                </h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="facebook_url" class="form-label">Facebook URL</label>
                        <input type="url" class="form-control" id="facebook_url" name="settings[facebook_url]" 
                               value="<?= htmlspecialchars($currentSettings['facebook_url'] ?? '') ?>" 
                               placeholder="https://facebook.com/yourpage">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="instagram_url" class="form-label">Instagram URL</label>
                        <input type="url" class="form-control" id="instagram_url" name="settings[instagram_url]" 
                               value="<?= htmlspecialchars($currentSettings['instagram_url'] ?? '') ?>" 
                               placeholder="https://instagram.com/yourpage">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="twitter_url" class="form-label">Twitter/X URL</label>
                        <input type="url" class="form-control" id="twitter_url" name="settings[twitter_url]" 
                               value="<?= htmlspecialchars($currentSettings['twitter_url'] ?? '') ?>" 
                               placeholder="https://twitter.com/yourpage">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                        <input type="url" class="form-control" id="linkedin_url" name="settings[linkedin_url]" 
                               value="<?= htmlspecialchars($currentSettings['linkedin_url'] ?? '') ?>" 
                               placeholder="https://linkedin.com/company/yourpage">
                    </div>
                </div>
            </div>

            <!-- SEO Settings -->
            <div class="mb-4">
                <h6 class="mb-3" style="color: var(--primary-700); border-bottom: 2px solid var(--accent); padding-bottom: 8px;">
                    <i class="bi bi-search me-2"></i>SEO Settings
                </h6>
                
                <div class="row g-3">
                    <div class="col-md-12">
                        <label for="meta_description" class="form-label">Meta Description</label>
                        <textarea class="form-control" id="meta_description" name="settings[meta_description]" rows="3" 
                                  placeholder="Enter meta description for SEO"><?= htmlspecialchars($currentSettings['meta_description'] ?? '') ?></textarea>
                        <small class="text-muted">Brief description for search engines (150-160 characters recommended)</small>
                    </div>
                    
                    <div class="col-md-12">
                        <label for="meta_keywords" class="form-label">Meta Keywords</label>
                        <input type="text" class="form-control" id="meta_keywords" name="settings[meta_keywords]" 
                               value="<?= htmlspecialchars($currentSettings['meta_keywords'] ?? '') ?>" 
                               placeholder="PG, paying guest, accommodation, hostel">
                        <small class="text-muted">Comma-separated keywords for SEO</small>
                    </div>
                </div>
            </div>

            <!-- Footer Settings -->
            <div class="mb-4">
                <h6 class="mb-3" style="color: var(--primary-700); border-bottom: 2px solid var(--accent); padding-bottom: 8px;">
                    <i class="bi bi-file-text me-2"></i>Footer Settings
                </h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="copyright_text" class="form-label">Copyright Text</label>
                        <input type="text" class="form-control" id="copyright_text" name="settings[copyright_text]" 
                               value="<?= htmlspecialchars($currentSettings['copyright_text'] ?? '© 2025 Livonto — All Rights Reserved.') ?>" 
                               placeholder="© 2025 Livonto — All Rights Reserved.">
                        <small class="text-muted">Used for general copyright notices</small>
                    </div>
                    <div class="col-md-6">
                        <label for="footer_copyright" class="form-label">Footer Copyright Text (Optional)</label>
                        <input type="text" class="form-control" id="footer_copyright" name="settings[footer_copyright]" 
                               value="<?= htmlspecialchars($currentSettings['footer_copyright'] ?? '') ?>" 
                               placeholder="© {YEAR} Livonto — All Rights Reserved.">
                        <small class="text-muted">If empty, will use Copyright Text above. Use {YEAR} for dynamic year.</small>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will reload the page.')) {
        window.location.reload();
    }
}

// Form submission handling
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    
    // Form will submit normally, button will be re-enabled on page reload
});
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

