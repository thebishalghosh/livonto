<?php
// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require __DIR__ . '/../app/config.php';
    require __DIR__ . '/../app/functions.php';
    
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    if (empty($message)) {
        $errors[] = 'Message is required';
    }
    
    // If there are errors, store them and form data
    if (!empty($errors)) {
        $_SESSION['contact_error'] = implode('<br>', $errors);
        $_SESSION['contact_form_data'] = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message
        ];
        header('Location: ' . app_url('contact'));
        exit;
    }
    
    // Store in database
    try {
        $db = db();
        
        // Try to create table if it doesn't exist
        try {
            $db->execute(
                "INSERT INTO contacts (name, email, subject, message, status, created_at) 
                 VALUES (?, ?, ?, ?, 'new', CURRENT_TIMESTAMP)",
                [$name, $email, $subject, $message]
            );
        } catch (PDOException $e) {
            // Table might not exist, create it
            $pdo = $db->getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('new', 'read', 'replied') DEFAULT 'new',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Retry insert
            $db->execute(
                "INSERT INTO contacts (name, email, subject, message, status, created_at) 
                 VALUES (?, ?, ?, ?, 'new', CURRENT_TIMESTAMP)",
                [$name, $email, $subject, $message]
            );
        }
        
        // Success
        $_SESSION['contact_success'] = 'Thank you! Your message has been sent. We\'ll get back to you soon.';
        unset($_SESSION['contact_form_data']);
        
    } catch (Exception $e) {
        error_log("Error processing contact form: " . $e->getMessage());
        $_SESSION['contact_error'] = 'Sorry, there was an error sending your message. Please try again or contact us directly.';
        $_SESSION['contact_form_data'] = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message
        ];
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . app_url('contact'));
    exit;
}

// Now include header and display page
$pageTitle = "Contact Us";
require __DIR__ . '/../app/includes/header.php';
$baseUrl = app_url('');

// Get flash messages from session
$formError = $_SESSION['contact_error'] ?? '';
$formSuccess = $_SESSION['contact_success'] ?? '';
$formData = $_SESSION['contact_form_data'] ?? [];

// Clear flash messages after reading
unset($_SESSION['contact_error']);
unset($_SESSION['contact_success']);
unset($_SESSION['contact_form_data']);

// Pre-fill form with previous data if available
$name = $formData['name'] ?? '';
$email = $formData['email'] ?? '';
$subject = $formData['subject'] ?? '';
$message = $formData['message'] ?? '';
?>

<div class="contact-page">
<div class="row g-4">
  <!-- Header -->
  <div class="col-12">
    <h1 class="display-5 fw-bold mb-3 text-primary">Get in Touch</h1>
    <p class="lead text-muted">Have questions? We're here to help. Reach out and we'll respond as soon as possible.</p>
  </div>

  <!-- Success/Error Messages -->
  <?php if ($formSuccess): ?>
    <div class="col-12">
      <div class="alert alert-themed-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($formSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if ($formError): ?>
    <div class="col-12">
      <div class="alert alert-themed-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $formError ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Left Column: Contact Information -->
  <div class="col-lg-4">
    <!-- Contact Info Card -->
    <div class="card pg mb-4">
      <div class="card-body">
        <h5 class="fw-bold mb-4">
          <i class="bi bi-info-circle text-primary me-2"></i>Contact Information
        </h5>
        
        <div class="mb-4">
          <div class="kicker mb-2">PG Booking Enquiry</div>
          <?php
          $bookingEnquiryPhone = function_exists('getSetting') ? getSetting('booking_enquiry_phone', '6293010501 | 7047133182 | 9831068248') : '6293010501 | 7047133182 | 9831068248';
          $bookingPhones = array_map('trim', explode('|', $bookingEnquiryPhone));
          ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($bookingPhones as $phone): ?>
              <a href="tel:+91<?= preg_replace('/[^0-9]/', '', $phone) ?>" class="text-decoration-none d-flex align-items-center text-primary">
                <i class="bi bi-telephone-fill text-primary me-2"></i>
                <span class="text-primary"><?= htmlspecialchars($phone) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-2">
            <i class="bi bi-clock me-1"></i>Timings: <?= htmlspecialchars(function_exists('getSetting') ? getSetting('booking_timings', '10:00 AM to 8:00 PM') : '10:00 AM to 8:00 PM') ?>
          </div>
        </div>
        
        <hr>
        
        <div class="mb-4">
          <div class="kicker mb-2">Email</div>
          <a href="mailto:<?= htmlspecialchars(function_exists('getSetting') ? getSetting('contact_email', 'support@livonto.com') : 'support@livonto.com') ?>" class="text-decoration-none d-flex align-items-center text-primary">
            <i class="bi bi-envelope-fill text-primary me-2"></i>
            <span class="text-primary"><?= htmlspecialchars(function_exists('getSetting') ? getSetting('contact_email', 'support@livonto.com') : 'support@livonto.com') ?></span>
          </a>
        </div>
        
        <hr>
        
        <div>
          <div class="kicker mb-2">WhatsApp</div>
          <p class="small text-muted mb-3">Message us directly on WhatsApp</p>
          <div class="d-flex flex-column gap-2">
            <a href="https://wa.me/916293010501" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-whatsapp me-2"></i>Chat on WhatsApp
            </a>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Office Location Card -->
    <div class="card pg">
      <div class="card-body">
        <h5 class="fw-bold mb-3">
          <i class="bi bi-geo-alt-fill text-primary me-2"></i>Our Office
        </h5>
        <p class="mb-2 fw-semibold">Kolkata Office</p>
        <p class="small text-muted mb-3">St. Xavier's College area, Kolkata</p>
        <div class="ratio ratio-16x9 rounded overflow-hidden">
          <iframe
            src="https://www.google.com/maps?q=St.+Xavier%E2%80%99s+College,+Kolkata&output=embed"
            allowfullscreen
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            style="border:0"></iframe>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Right Column: Contact Form & Additional Info -->
  <div class="col-lg-8">
    <!-- Contact Form Card -->
    <div class="card pg mb-4">
      <div class="card-body">
        <h5 class="fw-bold mb-4">
          <i class="bi bi-send-fill text-primary me-2"></i>Send us a Message
        </h5>
        
        <form method="POST" action="" id="contactForm" novalidate>
          <input type="hidden" name="contact_submit" value="1">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="contactName" class="form-label fw-semibold">
                Full Name <span class="text-danger">*</span>
              </label>
              <input 
                type="text" 
                class="form-control" 
                id="contactName" 
                name="name" 
                value="<?= htmlspecialchars($name) ?>"
                placeholder="Enter your full name"
                required>
              <div class="invalid-feedback">Please provide your name.</div>
            </div>
            
            <div class="col-md-6">
              <label for="contactEmail" class="form-label fw-semibold">
                Email Address <span class="text-danger">*</span>
              </label>
              <input 
                type="email" 
                class="form-control" 
                id="contactEmail" 
                name="email" 
                value="<?= htmlspecialchars($email) ?>"
                placeholder="your.email@example.com"
                required>
              <div class="invalid-feedback">Please provide a valid email address.</div>
            </div>
            
            <div class="col-12">
              <label for="contactSubject" class="form-label fw-semibold">
                Subject <span class="text-danger">*</span>
              </label>
              <input 
                type="text" 
                class="form-control" 
                id="contactSubject" 
                name="subject" 
                value="<?= htmlspecialchars($subject) ?>"
                placeholder="What is this regarding?"
                required>
              <div class="invalid-feedback">Please provide a subject.</div>
            </div>
            
            <div class="col-12">
              <label for="contactMessage" class="form-label fw-semibold">
                Message <span class="text-danger">*</span>
              </label>
              <textarea 
                class="form-control" 
                id="contactMessage" 
                name="message" 
                rows="6" 
                placeholder="Tell us how we can help you..."
                required><?= htmlspecialchars($message) ?></textarea>
              <div class="invalid-feedback">Please provide your message.</div>
            </div>
            
            <div class="col-12">
              <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-send me-2"></i>Send Message
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Quick Links Card -->
    <div class="card pg mb-4">
      <div class="card-body">
        <h6 class="fw-bold mb-3">
          <i class="bi bi-link-45deg text-primary me-2"></i>Quick Links
        </h6>
        <div class="row g-2">
          <div class="col-md-6">
            <a href="<?= htmlspecialchars(app_url('listings')) ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
              <i class="bi bi-house-door me-2"></i>Browse Listings
            </a>
          </div>
          <div class="col-md-6">
            <a href="<?= htmlspecialchars(app_url('about')) ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
              <i class="bi bi-info-circle me-2"></i>About Livonto
            </a>
          </div>
          <div class="col-md-6">
            <a href="<?= htmlspecialchars(app_url('refer')) ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
              <i class="bi bi-gift me-2"></i>Refer & Earn
            </a>
          </div>
          <div class="col-md-6">
            <a href="tel:+916293010501" class="btn btn-outline-primary btn-sm w-100 mb-2">
              <i class="bi bi-telephone me-2"></i>Call Us Now
            </a>
          </div>
        </div>
      </div>
    </div>
    
    <!-- FAQ Accordion -->
    <div class="card pg">
      <div class="card-body">
        <h6 class="fw-bold mb-3">
          <i class="bi bi-question-circle text-primary me-2"></i>Frequently Asked Questions
        </h6>
        <div class="accordion accordion-flush" id="contactFaq">
          <div class="accordion-item border-0">
            <h2 class="accordion-header" id="faq1">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1Collapse" aria-expanded="false">
                How soon will I get a response?
              </button>
            </h2>
            <div id="faq1Collapse" class="accordion-collapse collapse" data-bs-parent="#contactFaq">
              <div class="accordion-body text-muted">
                We typically respond within business hours (10:00 AM to 8:00 PM IST). For urgent matters, please call us directly.
              </div>
            </div>
          </div>
          
          <div class="accordion-item border-0">
            <h2 class="accordion-header" id="faq2">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2Collapse" aria-expanded="false">
                Can I list my PG with you?
              </button>
            </h2>
            <div id="faq2Collapse" class="accordion-collapse collapse" data-bs-parent="#contactFaq">
              <div class="accordion-body text-muted">
                Yes! We welcome PG owners to list their properties. Please call us or send a message using the form above, and we'll guide you through the listing process.
              </div>
            </div>
          </div>
          
          <div class="accordion-item border-0">
            <h2 class="accordion-header" id="faq3">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3Collapse" aria-expanded="false">
                What information should I include in my message?
              </button>
            </h2>
            <div id="faq3Collapse" class="accordion-collapse collapse" data-bs-parent="#contactFaq">
              <div class="accordion-body text-muted">
                Please include your name, contact details, and a clear description of your inquiry. For PG listing inquiries, mention your property location, number of rooms, and preferred contact method.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Form validation
(function() {
  'use strict';
  const form = document.getElementById('contactForm');
  if (!form) return;
  
  form.addEventListener('submit', function(event) {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add('was-validated');
  }, false);
})();
</script>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>