<?php
/**
 * Visit Booking Page
 * View file - displays the visit booking form
 */

// Include backend handler (handles all logic, redirects, and sets variables)
require __DIR__ . '/../app/handlers/visit_book_handler.php';

// Include header after handler (handler may redirect, so header comes after)
require __DIR__ . '/../app/includes/header.php';
?>

<style>
.visit-booking-page {
    background: var(--bg);
    min-height: calc(100vh - 200px);
    padding: 2rem 0 4rem;
}

.visit-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
    color: white;
    border-radius: var(--card-radius);
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-2);
}

.visit-header h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: white;
}

.visit-header .subtitle {
    font-size: 1rem;
    opacity: 0.95;
    margin: 0;
}

.listing-preview-card {
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

.listing-preview-card .listing-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.listing-preview-card .listing-info h5 {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
    color: var(--primary-700);
}

.listing-preview-card .listing-info p {
    margin: 0;
    color: var(--muted);
    font-size: 0.9rem;
}

.visit-form-card {
    background: var(--card-bg);
    border-radius: var(--card-radius);
    padding: 2.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-1);
}

.form-section {
    margin-bottom: 2.5rem;
}

.form-section:last-of-type {
    margin-bottom: 0;
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

.section-title i {
    font-size: 1.2rem;
}

.form-label {
    font-weight: 500;
    color: #2d2a3e;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1.5px solid var(--border);
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(139, 107, 209, 0.1);
    outline: none;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.btn-submit {
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
    border: none;
    padding: 0.875rem 2rem;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.2s ease;
    flex: 1;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(139, 107, 209, 0.3);
}

.btn-cancel {
    background: transparent;
    border: 1.5px solid var(--border);
    color: var(--muted);
    padding: 0.875rem 2rem;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.btn-cancel:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(139, 107, 209, 0.05);
}

.visit-info-sidebar {
    position: sticky;
    top: 2rem;
}

.info-card {
    background: var(--card-bg);
    border-radius: var(--card-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-1);
    transition: all 0.2s ease;
}

.info-card:hover {
    box-shadow: var(--shadow-2);
    transform: translateY(-2px);
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--accent);
}

.info-card-header i {
    font-size: 1.5rem;
    color: var(--primary);
}

.info-card-header h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-700);
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.info-list li:last-child {
    border-bottom: none;
}

.info-list li i {
    color: var(--primary);
    font-size: 1.1rem;
    margin-top: 0.2rem;
    flex-shrink: 0;
}

.info-list li span {
    color: var(--muted);
    font-size: 0.95rem;
    line-height: 1.5;
}

.process-step {
    display: flex;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
}

.process-step:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.step-number {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}

.step-content strong {
    display: block;
    color: var(--primary-700);
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

.step-content p {
    margin: 0;
    color: var(--muted);
    font-size: 0.85rem;
    line-height: 1.5;
}

.help-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
}

.help-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.help-item i {
    font-size: 1.5rem;
    color: var(--primary);
    margin-top: 0.2rem;
    flex-shrink: 0;
}

.help-item strong {
    display: block;
    color: var(--primary-700);
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

.help-item p {
    margin: 0;
    color: var(--muted);
    font-size: 0.9rem;
}

.tips-card {
    background: linear-gradient(135deg, rgba(139, 107, 209, 0.05) 0%, rgba(111, 85, 178, 0.05) 100%);
    border: 1px solid rgba(139, 107, 209, 0.2);
}

.tip-text {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0;
    margin: 0;
    color: var(--muted);
    font-size: 0.9rem;
    line-height: 1.6;
}

.tip-text:not(:last-child) {
    border-bottom: 1px solid var(--border);
}

.tip-text i {
    color: var(--primary);
    font-size: 1rem;
    margin-top: 0.2rem;
    flex-shrink: 0;
}

@media (max-width: 992px) {
    .visit-info-sidebar {
        position: static;
        margin-top: 2rem;
    }
}

@media (max-width: 768px) {
    .visit-header {
        padding: 1.5rem;
    }
    
    .visit-header h1 {
        font-size: 1.5rem;
    }
    
    .visit-form-card {
        padding: 1.5rem;
    }
    
    .listing-preview-card {
        flex-direction: column;
        text-align: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .info-card {
        padding: 1.25rem;
    }
}
</style>

<div class="visit-booking-page">
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Success!</strong> Your visit request has been submitted. We'll contact you soon.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="visit-header">
            <h1><i class="bi bi-calendar-check me-2"></i>Book a Visit</h1>
            <p class="subtitle text-white">Fill in your details to schedule a visit to this property</p>
        </div>
        
        <?php if ($listing): ?>
        <div class="listing-preview-card">
            <div class="listing-icon">
                <i class="bi bi-building"></i>
            </div>
            <div class="listing-info flex-grow-1">
                <h5><?= htmlspecialchars($listing['title']) ?></h5>
                <p>
                    <i class="bi bi-geo-alt-fill me-1"></i>
                    <?= htmlspecialchars($listing['city'] ?? '') ?>
                    <?= !empty($listing['pin_code']) ? ' • ' . htmlspecialchars($listing['pin_code']) : '' ?>
                </p>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Left Column - Form -->
            <div class="col-lg-7">
                <div class="visit-form-card">
                    <!-- Alert container for AJAX messages -->
                    <div id="visitBookingAlert"></div>
                    
                    <form id="visitBookingForm" method="POST" novalidate>
                        <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                        
                        <!-- User Information Display -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="bi bi-person-circle"></i>
                                Your Information
                            </h3>
                            <div class="alert alert-info mb-0" style="background: rgba(139, 107, 209, 0.1); border-color: var(--primary);">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1"><strong>Name:</strong></small>
                                        <div><?= htmlspecialchars($userData['name'] ?? 'Not set') ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1"><strong>Email:</strong></small>
                                        <div><?= htmlspecialchars($userData['email'] ?? 'Not set') ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1"><strong>Phone:</strong></small>
                                        <div><?= htmlspecialchars($userData['phone'] ?? 'Not set') ?></div>
                                    </div>
                                    <?php if (!empty($userData['gender'])): ?>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1"><strong>Gender:</strong></small>
                                        <div><?= ucfirst($userData['gender']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($userData['dob'])): ?>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block mb-1"><strong>Date of Birth:</strong></small>
                                        <div><?= date('d M Y', strtotime($userData['dob'])) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($userData['address']) || !empty($userData['city'])): ?>
                                    <div class="col-12">
                                        <small class="text-muted d-block mb-1"><strong>Address:</strong></small>
                                        <div><?= htmlspecialchars(trim(($userData['address'] ?? '') . ', ' . ($userData['city'] ?? '') . ', ' . ($userData['state'] ?? '') . ' - ' . ($userData['pincode'] ?? ''), ', - ')) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        This information is taken from your profile. 
                                        <a href="<?= app_url('profile') ?>" class="text-decoration-none fw-semibold">Update your profile</a> if needed.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Visit Details Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="bi bi-clock-history"></i>
                                Visit Preferences
                            </h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Date *</label>
                                    <input type="date" class="form-control" name="date" 
                                           min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Time *</label>
                                    <input type="time" class="form-control" name="time" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Additional Message (Optional)</label>
                                    <textarea class="form-control" name="message" rows="3" 
                                              placeholder="Any special requests or questions..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-submit text-white" id="submitBtn">
                                <i class="bi bi-send-fill me-2"></i><span class="btn-text">Submit Visit Request</span>
                                <span class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                            </button>
                            <a href="<?= app_url('index') ?>" class="btn btn-cancel">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Right Column - Information -->
            <div class="col-lg-5">
                <div class="visit-info-sidebar">
                    <!-- What to Expect -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="bi bi-info-circle"></i>
                            <h4>What to Expect</h4>
                        </div>
                        <div class="info-card-body">
                            <ul class="info-list">
                                <li>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Property tour and room inspection</span>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Meet with property manager</span>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Review amenities and facilities</span>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Discuss pricing and availability</span>
                                </li>
                                <li>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Get answers to your questions</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Visit Process -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="bi bi-list-check"></i>
                            <h4>Visit Process</h4>
                        </div>
                        <div class="info-card-body">
                            <div class="process-step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Submit Request</strong>
                                    <p>Fill out the form with your details and preferred visit time</p>
                                </div>
                            </div>
                            <div class="process-step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Confirmation</strong>
                                    <p>We'll confirm your visit via email or phone within 24 hours</p>
                                </div>
                            </div>
                            <div class="process-step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Visit Day</strong>
                                    <p>Arrive at the property at your scheduled time</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Help & Support -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="bi bi-headset"></i>
                            <h4>Need Help?</h4>
                        </div>
                        <div class="info-card-body">
                            <div class="help-item">
                                <i class="bi bi-telephone-fill"></i>
                                <div>
                                    <strong>Call Us</strong>
                                    <p>6293010501 | 7047133182</p>
                                </div>
                            </div>
                            <div class="help-item">
                                <i class="bi bi-envelope-fill"></i>
                                <div>
                                    <strong>Email</strong>
                                    <p>support@livonto.com</p>
                                </div>
                            </div>
                            <div class="help-item">
                                <i class="bi bi-clock-fill"></i>
                                <div>
                                    <strong>Timings</strong>
                                    <p>10:00 AM to 8:00 PM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tips Card -->
                    <div class="info-card tips-card">
                        <div class="info-card-header">
                            <i class="bi bi-lightbulb"></i>
                            <h4>Pro Tips</h4>
                        </div>
                        <div class="info-card-body">
                            <p class="tip-text">
                                <i class="bi bi-star-fill"></i>
                                Come prepared with questions about amenities, rules, and pricing
                            </p>
                            <p class="tip-text">
                                <i class="bi bi-star-fill"></i>
                                Bring a valid ID for verification during the visit
                            </p>
                            <p class="tip-text">
                                <i class="bi bi-star-fill"></i>
                                Arrive 5-10 minutes early to ensure you don't miss your slot
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="visit-form-card text-center py-5">
                    <i class="bi bi-exclamation-circle text-muted" style="font-size: 3rem;"></i>
                    <h4 class="mt-3 mb-2">Unable to Load Listing</h4>
                    <p class="text-muted mb-4">The listing you're looking for is not available or has been removed.</p>
                    <a href="<?= app_url('index') ?>" class="btn btn-submit text-white">
                        <i class="bi bi-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

<script>
(function() {
    const form = document.getElementById('visitBookingForm');
    const submitBtn = document.getElementById('submitBtn');
    const alertContainer = document.getElementById('visitBookingAlert');
    
    if (!form || !submitBtn || !alertContainer) return;
    
    const btnText = submitBtn.querySelector('.btn-text');
    const spinner = submitBtn.querySelector('.spinner-border');
    
    // Show alert function
    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        
        alertContainer.innerHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi ${icon} me-2"></i>
                <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Scroll to alert
        alertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    // Show field errors
    function showFieldErrors(errors) {
        Object.keys(errors).forEach(field => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
                input.classList.add('is-invalid');
                let feedback = input.parentElement.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    input.parentElement.appendChild(feedback);
                }
                feedback.textContent = errors[field];
            }
        });
    }
    
    // Clear field errors
    function clearFieldErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.remove();
        });
    }
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Clear previous errors
        clearFieldErrors();
        alertContainer.innerHTML = '';
        
        // Validate form
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        // Disable submit button and show loading
        submitBtn.disabled = true;
        if (btnText) btnText.textContent = 'Submitting...';
        if (spinner) spinner.classList.remove('d-none');
        
        // Get form data
        const formData = new FormData(form);
        
        try {
            const response = await fetch('<?= app_url("visit-book-api") ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // Success
                showAlert('success', data.message || 'Your visit request has been submitted successfully.');
                
                // Reset form
                form.reset();
                form.classList.remove('was-validated');
                
                // Disable form to prevent resubmission
                form.querySelectorAll('input, textarea, button').forEach(el => {
                    el.disabled = true;
                });
                
                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = '<?= app_url("index") ?>';
                }, 2000);
                
            } else {
                // Error
                if (data.errors && typeof data.errors === 'object') {
                    showFieldErrors(data.errors);
                }
                showAlert('error', data.message || 'Error submitting request. Please try again.');
                
                // Re-enable submit button
                submitBtn.disabled = false;
                if (btnText) btnText.textContent = 'Submit Visit Request';
                if (spinner) spinner.classList.add('d-none');
            }
            
        } catch (error) {
            console.error('AJAX error:', error);
            showAlert('error', 'Network error. Please check your connection and try again.');
            
            // Re-enable submit button
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = 'Submit Visit Request';
            if (spinner) spinner.classList.add('d-none');
        }
    });
})();
</script>
