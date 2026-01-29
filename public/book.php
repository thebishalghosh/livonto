<?php
/**
 * Booking Page
 * View file - displays booking form with KYC check
 */

// Include backend handler (handles all logic, redirects, and sets variables)
require __DIR__ . '/../app/handlers/book_handler.php';

// Include header after handler (handler may redirect, so header comes after)
require __DIR__ . '/../app/includes/header.php';
?>

<style>
.booking-page {
    background: var(--bg);
    min-height: calc(100vh - 200px);
    padding: 2rem 0 4rem;
}

.booking-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
    color: white;
    border-radius: var(--card-radius);
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-2);
}

.booking-header h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: white;
}

.booking-card {
    background: var(--card-bg);
    border-radius: var(--card-radius);
    padding: 2.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-1);
    margin-bottom: 2rem;
}

.listing-preview-section {
    background: var(--card-bg);
    border-radius: var(--card-radius);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-1);
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.listing-preview-section .listing-image {
    width: 120px;
    height: 120px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
}

.listing-preview-section .listing-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.step-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: var(--card-bg);
    border-radius: var(--card-radius);
    border: 1px solid var(--border);
}

.step-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
    max-width: 300px;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.step-item.active .step-number {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
    color: white;
}

.step-item.completed .step-number {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    color: white;
}

.step-item.inactive .step-number {
    background: var(--border);
    color: var(--muted);
}

.step-content {
    flex: 1;
}

.step-title {
    font-weight: 600;
    font-size: 0.95rem;
    margin: 0;
    color: var(--bs-body-color);
}

.step-item.active .step-title {
    color: var(--primary-700);
}

.step-item.inactive .step-title {
    color: var(--muted);
}

.step-connector {
    width: 60px;
    height: 2px;
    background: var(--border);
    flex-shrink: 0;
}

.step-item.completed + .step-connector {
    background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
}

.form-section {
    margin-bottom: 2rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-700);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--accent);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.form-label {
    font-weight: 500;
    color: var(--bs-body-color);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1.5px solid var(--border);
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
    background-color: var(--card-bg);
    color: var(--bs-body-color);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(139, 107, 209, 0.1);
    outline: none;
    background-color: var(--card-bg);
    color: var(--bs-body-color);
}

.form-check-input {
    background-color: var(--card-bg);
    border: 2px solid var(--primary);
    width: 1.25em;
    height: 1.25em;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
}

/* Dark mode checkbox styling */
:root[data-theme="dark"] .form-check-input {
    background-color: var(--card-bg);
    border: 2px solid var(--primary);
    border-color: var(--primary) !important;
}

:root[data-theme="dark"] .form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary) !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%230f1020' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
}

.file-upload-area {
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    transition: all 0.2s ease;
    cursor: pointer;
    background: var(--card-bg);
    color: var(--bs-body-color);
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: rgba(139, 107, 209, 0.05);
}

:root[data-theme="dark"] .file-upload-area:hover {
    background: rgba(191, 176, 255, 0.1);
}

.file-upload-area.dragover {
    border-color: var(--primary);
    background: rgba(139, 107, 209, 0.1);
}

:root[data-theme="dark"] .file-upload-area.dragover {
    background: rgba(191, 176, 255, 0.15);
}

.file-preview {
    margin-top: 1rem;
    display: none;
}

.file-preview img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.price-summary {
    background: linear-gradient(135deg, rgba(139, 107, 209, 0.05) 0%, rgba(111, 85, 178, 0.05) 100%);
    border: 1px solid rgba(139, 107, 209, 0.2);
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 1rem;
}

:root[data-theme="dark"] .price-summary {
    background: linear-gradient(135deg, rgba(191, 176, 255, 0.1) 0%, rgba(154, 128, 230, 0.1) 100%);
    border-color: rgba(191, 176, 255, 0.3);
}

.price-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
    color: var(--bs-body-color);
}

.price-row:last-child {
    border-bottom: none;
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--primary-700);
    margin-top: 0.5rem;
    padding-top: 1rem;
}

.btn-submit {
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
    border: none;
    padding: 0.875rem 2rem;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.2s ease;
    width: 100%;
    color: white;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 107, 209, 0.3);
    color: white;
}

:root[data-theme="dark"] .btn-submit:hover {
    box-shadow: 0 8px 20px rgba(191, 176, 255, 0.4);
}

.kyc-status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.kyc-status-badge.verified {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    color: white;
}

.kyc-status-badge.pending {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: white;
}

.kyc-status-badge.rejected {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

/* Dark mode specific overrides */
:root[data-theme="dark"] .listing-preview-section p,
:root[data-theme="dark"] .listing-preview-section h5 {
    color: var(--bs-body-color);
}

:root[data-theme="dark"] .form-control::placeholder,
:root[data-theme="dark"] .form-select option {
    color: var(--muted);
}

:root[data-theme="dark"] .alert {
    background-color: var(--card-bg);
    border-color: var(--border);
    color: var(--bs-body-color);
}

.form-check-label {
    color: var(--bs-body-color);
    cursor: pointer;
}

:root[data-theme="dark"] .form-check-label {
    color: #efeaff !important;
}

:root[data-theme="dark"] .form-check-label a {
    color: var(--primary) !important;
}

:root[data-theme="dark"] .form-check-label a:hover {
    color: var(--primary-700) !important;
}
</style>

<div class="booking-page">
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($listing): ?>
        <div class="booking-header">
            <h1><i class="bi bi-check-circle me-2"></i>Book Now</h1>
            <p class="text-white mb-0">Complete your booking in a few simple steps</p>
        </div>
        
        <!-- Listing Preview -->
        <div class="listing-preview-section">
            <?php if (!empty($listing['images']) && is_array($listing['images'])): ?>
                <div class="listing-image">
                    <img src="<?= htmlspecialchars($listing['images'][0]) ?>" 
                         alt="<?= htmlspecialchars($listing['title']) ?>"
                         onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:white;font-size:2rem;\'><i class=\'bi bi-building\'></i></div>';">
                </div>
            <?php else: ?>
                <div class="listing-image" style="display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                    <i class="bi bi-building"></i>
                </div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <h5 class="mb-1" style="color: var(--primary-700);"><?= htmlspecialchars($listing['title']) ?></h5>
                <p class="text-muted mb-0">
                    <i class="bi bi-geo-alt-fill me-1"></i>
                    <?= htmlspecialchars($listing['city'] ?? '') ?>
                    <?= !empty($listing['pin_code']) ? ' • ' . htmlspecialchars($listing['pin_code']) : '' ?>
                </p>
            </div>
        </div>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-item <?= $step === 'kyc' ? 'active' : ($kycStatus && $kycStatus['status'] === 'verified' ? 'completed' : 'inactive') ?>">
                <div class="step-number">
                    <?php if ($kycStatus && $kycStatus['status'] === 'verified'): ?>
                        <i class="bi bi-check"></i>
                    <?php else: ?>
                        1
                    <?php endif; ?>
                </div>
                <div class="step-content">
                    <div class="step-title">KYC Verification</div>
                </div>
            </div>
            <div class="step-connector"></div>
            <div class="step-item <?= $step === 'booking' ? 'active' : 'inactive' ?>">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-title">Booking Details</div>
                </div>
            </div>
        </div>
        
        <!-- KYC Form -->
        <?php if ($step === 'kyc'): ?>
        <div class="booking-card">
            <h3 class="section-title">
                <i class="bi bi-shield-check"></i>
                KYC Verification Required
            </h3>
            
            <?php if ($kycStatus): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>KYC Already Submitted</strong> - You can proceed with booking.
                </div>
            <?php endif; ?>
            
            <form id="kycForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_kyc">
                <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                
                <div class="form-section">
                    <label class="form-label">Document Type *</label>
                    <select class="form-select" name="document_type" required>
                        <option value="">Select Document Type</option>
                        <option value="aadhar">Aadhar Card</option>
                        <option value="pan">PAN Card</option>
                        <option value="passport">Passport</option>
                        <option value="driving_license">Driving License</option>
                        <option value="voter_id">Voter ID</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-section">
                    <label class="form-label">Document Number *</label>
                    <input type="text" class="form-control" name="document_number" required 
                           placeholder="Enter document number">
                </div>
                
                <div class="form-section">
                    <label class="form-label">Document Front Image *</label>
                    <div class="file-upload-area" onclick="document.getElementById('document_front').click();">
                        <i class="bi bi-cloud-upload" style="font-size: 2rem; color: var(--primary);"></i>
                        <p class="mt-2 mb-0">Click to upload or drag and drop</p>
                        <small class="text-muted">PNG, JPG, PDF up to 5MB</small>
                    </div>
                    <input type="file" id="document_front" name="document_front" accept="image/*,.pdf" required style="display: none;" onchange="previewFile(this, 'frontPreview')">
                    <div class="file-preview" id="frontPreview"></div>
                </div>
                
                <div class="form-section">
                    <label class="form-label">Document Back Image (if applicable)</label>
                    <div class="file-upload-area" onclick="document.getElementById('document_back').click();">
                        <i class="bi bi-cloud-upload" style="font-size: 2rem; color: var(--primary);"></i>
                        <p class="mt-2 mb-0">Click to upload or drag and drop</p>
                        <small class="text-muted">PNG, JPG, PDF up to 5MB</small>
                    </div>
                    <input type="file" id="document_back" name="document_back" accept="image/*,.pdf" style="display: none;" onchange="previewFile(this, 'backPreview')">
                    <div class="file-preview" id="backPreview"></div>
                </div>
                
                <button type="submit" class="btn btn-submit">
                    <i class="bi bi-upload me-2"></i>Submit KYC
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Booking Form -->
        <?php if ($step === 'booking'): ?>
        <div class="booking-card">
            <h3 class="section-title">
                <i class="bi bi-calendar-check"></i>
                Booking Details
            </h3>
            
            <div class="alert alert-info mb-4" style="background: rgba(139, 107, 209, 0.1); border-color: var(--primary);">
                <i class="bi bi-check-circle me-2"></i>
                <strong>KYC Verified!</strong> You can now proceed with booking.
            </div>
            
            <form id="bookingForm" method="POST">
                <input type="hidden" name="action" value="submit_booking">
                <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                <input type="hidden" name="kyc_id" value="<?= $kycStatus['id'] ?? '' ?>">
                
                <?php if (!empty($roomConfigs)): ?>
                <div class="form-section">
                    <label class="form-label">Select Month for Booking *</label>
                    <input type="date" class="form-control" name="booking_start_date" 
                           id="bookingStartDate" min="<?= date('Y-m-01') ?>" required>
                    <small class="text-muted">Booking will start on the 1st of the selected month.</small>
                </div>
                
                <div class="form-section">
                    <label class="form-label">Duration (Number of Months) *</label>
                    <select class="form-select" name="duration_months" id="durationMonths" required>
                        <option value="">Select Duration</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?> Month<?= $i > 1 ? 's' : '' ?></option>
                        <?php endfor; ?>
                    </select>
                    <small class="text-muted">Select how many months you want to book the PG for.</small>
                </div>
                
                <div class="form-section">
                    <label class="form-label">Select Room Type *</label>
                    <select class="form-select" name="room_config_id" id="roomConfigSelect" required>
                        <option value="">Select month and duration first to see availability</option>
                        <?php foreach ($roomConfigs as $room): ?>
                            <option value="<?= $room['id'] ?>" 
                                    data-price="<?= $room['rent_per_month'] ?>"
                                    data-room-id="<?= $room['id'] ?>">
                                <?= htmlspecialchars(ucfirst($room['room_type'])) ?> - 
                                ₹<?= number_format($room['rent_per_month']) ?>/month
                                <?php 
                                $availableBeds = (int)($room['available_beds'] ?? $room['available_rooms'] ?? 0);
                                if ($availableBeds > 0): ?>
                                    (<?= $availableBeds ?> bed<?= $availableBeds !== 1 ? 's' : '' ?> available)
                                <?php else: ?>
                                    (Fully booked)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted" id="availabilityNote"></small>
                </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <label class="form-label">Special Requests (Optional)</label>
                    <textarea class="form-control" name="special_requests" rows="3" 
                              placeholder="Any special requests or requirements..."></textarea>
                </div>
                
                <div class="form-section">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="agreed_to_tnc" id="agreedToTnc" required>
                        <label class="form-check-label" for="agreedToTnc">
                            I agree to the <a href="<?= app_url('terms') ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none">Terms and Conditions</a>
                        </label>
                    </div>
                </div>
                
                <!-- Price Summary -->
                <div class="price-summary" id="priceSummary" style="display: none;">
                    <div class="price-row">
                        <span>Monthly Rent:</span>
                        <span id="monthlyRent">₹0</span>
                    </div>
                    <div class="price-row">
                        <span>Duration:</span>
                        <span id="duration">0 months</span>
                    </div>
                    <div class="price-row">
                        <span>Security Deposit:</span>
                        <span id="securityDeposit">₹0</span>
                    </div>
                    <div class="price-row" id="gstRow" style="display: none;">
                        <span>GST (<span id="gstPercentage">0</span>%):</span>
                        <span id="gstAmount">₹0</span>
                    </div>
                    <div class="price-row">
                        <span>Total Amount to Pay:</span>
                        <span id="totalAmount">₹0</span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-submit mt-4">
                    <i class="bi bi-credit-card me-2"></i>Proceed to Payment
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="booking-card text-center py-5">
                    <i class="bi bi-exclamation-circle text-muted" style="font-size: 3rem;"></i>
                    <h4 class="mt-3 mb-2">Unable to Load Listing</h4>
                    <p class="text-muted mb-4">The listing you're looking for is not available or has been removed.</p>
                    <a href="<?= app_url('index') ?>" class="btn btn-submit">
                        <i class="bi bi-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function previewFile(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Get GST settings from PHP
const gstEnabled = <?= (function_exists('getSetting') && getSetting('gst_enabled', '0') == '1') ? 'true' : 'false' ?>;
const gstPercentage = <?= (function_exists('getSetting') ? floatval(getSetting('gst_percentage', '18')) : 18) ?>;

// Determine security deposit configuration from listing
<?php
$securityDepositMonths = 0;
$securityDepositFixed = 0.00;
if ($listing) {
    // Prefer explicit months column if available (added via migration)
    if (!empty($listing['security_deposit_months'])) {
        $securityDepositMonths = intval($listing['security_deposit_months']);
    }

    if (!empty($listing['security_deposit_amount'])) {
        $depositStr = trim($listing['security_deposit_amount']);
        if (strtolower($depositStr) !== 'no deposit') {
            // If stored value is a number between 1 and 6, treat as months
            if (is_numeric($depositStr) && intval($depositStr) >= 1 && intval($depositStr) <= 6) {
                if ($securityDepositMonths <= 0) {
                    $securityDepositMonths = intval($depositStr);
                }
            } else {
                // Otherwise parse as fixed currency amount (e.g., '₹5000')
                $depositStrClean = preg_replace('/[₹,\s]/', '', $depositStr);
                $securityDepositFixed = floatval($depositStrClean);
            }
        }
    }
}
// Export to JS: prefer fixed amount if provided, otherwise months * rent will be used
?>
const defaultSecurityDepositMonths = <?= $securityDepositMonths ?>;
const defaultSecurityDepositFixed = <?= number_format($securityDepositFixed, 2, '.', '') ?>;

// Calculate price based on room selection and start date (1 month booking)
document.addEventListener('DOMContentLoaded', function() {
    const roomSelect = document.getElementById('roomConfigSelect');
    const bookingStartDate = document.getElementById('bookingStartDate');
    const priceSummary = document.getElementById('priceSummary');
    const gstRow = document.getElementById('gstRow');
    
    function calculatePrice() {
        if (!roomSelect || !bookingStartDate) return;
        
        const selectedOption = roomSelect.options[roomSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            priceSummary.style.display = 'none';
            return;
        }
        
        const monthlyRent = parseFloat(selectedOption.dataset.price) || 0;
        const selectedDate = new Date(bookingStartDate.value);
        const durationMonths = document.getElementById('durationMonths');
        const duration = durationMonths ? parseInt(durationMonths.value) || 1 : 1;
        
        if (selectedDate && !isNaN(selectedDate.getTime())) {
            // Booking starts on 1st of the selected month
            const actualStartDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
            // End date is calculated based on duration
            const endDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth() + duration, 0);
            
            // Format dates for display
            const startDateStr = actualStartDate.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
            const endDateStr = endDate.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
            
            // Calculate total rent for the duration (for information only, not charged upfront)
            const totalRent = monthlyRent * duration;
            
            // Calculate security deposit: prefer fixed amount, then months * monthlyRent, else fallback to 1 month rent
            let securityDeposit = 0;
            const fixedDepositVal = parseFloat(defaultSecurityDepositFixed) || 0;
            const monthsDepositVal = parseInt(defaultSecurityDepositMonths) || 0;

            // Logic change: If months value is present, prioritize it over fixed value if fixed value looks like a small integer (1-6)
            // This handles the case where "2" was parsed as 2.00 fixed amount
            if (monthsDepositVal > 0) {
                securityDeposit = monthlyRent * monthsDepositVal;
            } else if (fixedDepositVal > 0) {
                securityDeposit = fixedDepositVal;
            } else {
                securityDeposit = monthlyRent;
            }
            
            // Calculate GST if enabled (GST is calculated on security deposit, not rent)
            let gstAmount = 0;
            let totalPayable = securityDeposit;
            
            if (gstEnabled && gstPercentage > 0) {
                gstAmount = (securityDeposit * gstPercentage) / 100;
                totalPayable = securityDeposit + gstAmount;
                
                // Show GST row
                if (gstRow) {
                    document.getElementById('gstPercentage').textContent = gstPercentage;
                    document.getElementById('gstAmount').textContent = '₹' + gstAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    gstRow.style.display = 'flex';
                }
            } else {
                // Hide GST row if disabled
                if (gstRow) {
                    gstRow.style.display = 'none';
                }
            }
            
            // Display rent information (for reference only)
            document.getElementById('monthlyRent').textContent = '₹' + monthlyRent.toLocaleString('en-IN') + '/month × ' + duration;
            document.getElementById('duration').textContent = `${duration} month${duration > 1 ? 's' : ''} (${startDateStr} to ${endDateStr})`;
            
            // Display security deposit
            document.getElementById('securityDeposit').textContent = '₹' + securityDeposit.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Display total payable amount (Security Deposit + GST)
            document.getElementById('totalAmount').textContent = '₹' + totalPayable.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            priceSummary.style.display = 'block';
        } else {
            priceSummary.style.display = 'none';
        }
    }
    
    if (roomSelect) roomSelect.addEventListener('change', calculatePrice);
    const durationMonths = document.getElementById('durationMonths');
    if (bookingStartDate) {
        bookingStartDate.addEventListener('change', function() {
            calculatePrice();
            if (durationMonths && durationMonths.value) {
                updateAvailabilityForMonth();
            }
        });
    }
    if (durationMonths) {
        durationMonths.addEventListener('change', function() {
            calculatePrice();
            if (bookingStartDate && bookingStartDate.value) {
                updateAvailabilityForMonth();
            }
        });
    }
    
    // Update room availability based on selected month and duration
    async function updateAvailabilityForMonth() {
        const bookingStartDate = document.getElementById('bookingStartDate');
        const durationMonths = document.getElementById('durationMonths');
        const roomSelect = document.getElementById('roomConfigSelect');
        const availabilityNote = document.getElementById('availabilityNote');
        const listingId = document.querySelector('input[name="listing_id"]').value;
        
        if (!bookingStartDate || !bookingStartDate.value || !durationMonths || !durationMonths.value || !roomSelect) return;
        
        // Show loading state
        roomSelect.disabled = true;
        availabilityNote.textContent = 'Checking availability...';
        availabilityNote.className = 'text-muted';
        
        try {
            const formData = new FormData();
            formData.append('action', 'check_availability');
            formData.append('listing_id', listingId);
            formData.append('booking_start_date', bookingStartDate.value);
            formData.append('duration_months', durationMonths.value);
            
            const response = await fetch('<?= app_url("book-api") ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.status === 'success' && data.data && data.data.rooms) {
                const rooms = data.data.rooms;
                const selectedValue = roomSelect.value;
                
                // Clear existing options except the first one
                roomSelect.innerHTML = '<option value="">Select Room Type</option>';
                
                // Add updated options with month-specific availability
                rooms.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.setAttribute('data-price', room.rent_per_month);
                    option.setAttribute('data-room-id', room.id);
                    
                    const roomTypeFormatted = room.room_type.split(' ').map(word => 
                        word.charAt(0).toUpperCase() + word.slice(1)
                    ).join(' ');
                    let text = `${roomTypeFormatted} - ₹${parseFloat(room.rent_per_month).toLocaleString('en-IN')}/month`;
                    
                    if (room.is_available) {
                        const availableBeds = room.available_beds || room.available_count || 0;
                        text += ` (${availableBeds} bed${availableBeds !== 1 ? 's' : ''} available)`;
                        option.disabled = false;
                    } else {
                        text += ' (Fully booked)';
                        option.disabled = true;
                    }
                    
                    option.textContent = text;
                    roomSelect.appendChild(option);
                });
                
                // Restore selection if it still exists and is available
                if (selectedValue) {
                    const selectedOption = Array.from(roomSelect.options).find(opt => opt.value === selectedValue && !opt.disabled);
                    if (selectedOption) {
                        roomSelect.value = selectedValue;
                    } else {
                        roomSelect.value = '';
                    }
                }
                
                // Update note
                const monthName = new Date(bookingStartDate.value).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
                const duration = durationMonths.value;
                const availableRooms = rooms.filter(r => r.is_available).length;
                if (availableRooms > 0) {
                    availabilityNote.textContent = `Showing availability for ${duration} month${duration > 1 ? 's' : ''} starting from ${monthName}`;
                    availabilityNote.className = 'text-success';
                } else {
                    availabilityNote.textContent = `No rooms available for ${duration} month${duration > 1 ? 's' : ''} starting from ${monthName}. Please select a different month or duration.`;
                    availabilityNote.className = 'text-danger';
                }
                
                calculatePrice();
            } else {
                throw new Error(data.message || 'Failed to check availability');
            }
        } catch (error) {
            availabilityNote.textContent = 'Error checking availability. Please try again.';
            availabilityNote.className = 'text-danger';
        } finally {
            roomSelect.disabled = false;
        }
    }
    
    // KYC Form Submission
    const kycForm = document.getElementById('kycForm');
    if (kycForm) {
        kycForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(kycForm);
            const submitBtn = kycForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            
            try {
                const apiUrl = '<?= app_url("book-api") ?>';
                
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                const responseText = await response.text();
                
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    throw new Error('Invalid JSON response from server');
                }
                
                if (data.status === 'success') {
                    alert('KYC submitted successfully! You can now proceed with booking.');
                    if (data.data && data.data.redirect) {
                        window.location.href = data.data.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    let errorMsg = data.message || 'Error submitting KYC. Please try again.';
                    if (data.errors) {
                        const errorList = Object.values(data.errors).join('\n');
                        if (errorList) {
                            errorMsg += '\n\n' + errorList;
                        }
                    }
                    alert(errorMsg);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                alert('Network error. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    // Booking Form Submission
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(bookingForm);
            const submitBtn = bookingForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            try {
                const apiUrl = '<?= app_url("book-api") ?>';
                
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                const responseText = await response.text();
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    throw new Error('Invalid JSON response from server');
                }
                
                if (!response.ok) {
                    if (data && data.status === 'error') {
                        let errorMsg = data.message || 'Error creating booking.';
                        if (data.errors) {
                            const errorList = Object.values(data.errors).join(', ');
                            if (errorList) {
                                errorMsg += '\n\n' + errorList;
                            }
                        }
                        alert(errorMsg);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                        return;
                    }
                    throw new Error('HTTP error! status: ' + response.status);
                }
                
                if (data.status === 'success') {
                    if (data.data && data.data.redirect) {
                        window.location.href = data.data.redirect;
                    } else {
                        alert('Booking created successfully!');
                        window.location.href = '<?= app_url("profile") ?>';
                    }
                } else {
                    let errorMsg = data.message || 'Error creating booking.';
                    if (data.errors) {
                        const errorList = Object.values(data.errors).join(', ');
                        if (errorList) {
                            errorMsg += '\n\n' + errorList;
                        }
                    }
                    alert(errorMsg);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                alert('Network error. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
