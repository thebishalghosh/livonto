<?php
/**
 * Payment Page
 * Handles Razorpay payment for bookings
 */

// Start session and load config/functions BEFORE any output
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';

// Require login
if (!isLoggedIn()) {
    header('Location: ' . app_url('login') . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = getCurrentUserId();
$bookingId = intval($_GET['booking_id'] ?? 0);

if ($bookingId <= 0) {
    header('Location: ' . app_url('profile'));
    exit;
}

try {
    $db = db();
    
    $booking = $db->fetchOne(
        "SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
                l.title as listing_title, loc.complete_address as listing_address,
                loc.city as listing_city, loc.pin_code as listing_pincode,
                rc.room_type, rc.rent_per_month,
                p.id as payment_id, p.amount as payment_amount, p.status as payment_status,
                p.provider, p.provider_payment_id
         FROM bookings b
         INNER JOIN users u ON b.user_id = u.id
         INNER JOIN listings l ON b.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         LEFT JOIN room_configurations rc ON b.room_config_id = rc.id
         LEFT JOIN payments p ON b.id = p.booking_id
         WHERE b.id = ? AND b.user_id = ?
         ORDER BY p.id DESC
         LIMIT 1",
        [$bookingId, $userId]
    );
    
    if (!$booking) {
        header('Location: ' . app_url('profile'));
        exit;
    }
    
    $listing = [
        'id' => $booking['listing_id'],
        'title' => $booking['listing_title'],
        'address' => $booking['listing_address'],
        'city' => $booking['listing_city'] ?? null,
        'pin_code' => $booking['listing_pincode'] ?? null
    ];
    
    $roomConfig = $booking['room_type'] ? [
        'room_type' => $booking['room_type'],
        'rent_per_month' => $booking['rent_per_month']
    ] : null;
    
    $payment = $booking['payment_id'] ? [
        'id' => $booking['payment_id'],
        'amount' => $booking['payment_amount'],
        'status' => $booking['payment_status'],
        'provider' => $booking['provider'],
        'provider_payment_id' => $booking['provider_payment_id']
    ] : null;
    
    $user = [
        'name' => $booking['user_name'],
        'email' => $booking['user_email'],
        'phone' => $booking['user_phone']
    ];
    
} catch (Exception $e) {
    header('Location: ' . app_url('profile'));
    exit;
}

if (($payment && $payment['status'] === 'success') || ($booking && $booking['status'] === 'confirmed')) {
    header('Location: ' . app_url('profile'));
    exit;
}

$config = require __DIR__ . '/../app/config.php';
$razorpayKeyId = $config['razorpay_key_id'] ?? '';
$razorpayKeySecret = $config['razorpay_key_secret'] ?? '';

if (empty($razorpayKeyId) || empty($razorpayKeySecret)) {
    // Show a user-friendly error instead of just dying
    $pageTitle = "Payment Error";
    require __DIR__ . '/../app/includes/header.php';
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card pg">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                        <h3 class="mt-3">Payment Gateway Not Configured</h3>
                        <p class="text-muted">The payment gateway has not been set up yet. Please contact the administrator.</p>
                        <a href="<?= app_url('profile') ?>" class="btn btn-primary">Go to Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require __DIR__ . '/../app/includes/footer.php';
    exit;
}

$pageTitle = "Payment - " . ($listing['title'] ?? 'Booking');

require __DIR__ . '/../app/includes/header.php';

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$logoUrl = $protocol . '://' . $host . $baseUrl . '/public/assets/images/logo-removebg.png';
$isLocalhost = in_array($host, ['localhost', '127.0.0.1']) || strpos($host, 'localhost') !== false;
?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Booking Summary -->
            <div class="card pg mb-4">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Booking Summary
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Property:</strong>
                            <p class="mb-0"><?= htmlspecialchars($listing['title'] ?? 'N/A') ?></p>
                            <small class="text-muted"><?= htmlspecialchars($listing['address'] ?? '') ?></small>
                        </div>
                        <div class="col-md-6">
                            <strong>Room Type:</strong>
                            <p class="mb-0"><?= htmlspecialchars($roomConfig['room_type'] ?? 'Standard Room') ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Booking Start Date:</strong>
                            <p class="mb-0"><?= date('F 1, Y', strtotime($booking['booking_start_date'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>Duration:</strong>
                            <p class="mb-0">1 Month</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Total Amount:</h5>
                        <h4 class="mb-0 text-primary">₹<?= number_format($booking['total_amount'], 2) ?></h4>
                    </div>
                </div>
            </div>
            
            <!-- Payment Section -->
            <div class="card pg">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-credit-card me-2"></i>Complete Payment
                    </h4>
                </div>
                <div class="card-body">
                    <form id="paymentForm">
                        <input type="hidden" id="bookingId" value="<?= $bookingId ?>">
                        <input type="hidden" id="amount" value="<?= $booking['total_amount'] ?>">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            You will be redirected to Razorpay secure payment gateway to complete your payment.
                        </div>
                        
                        <div class="d-grid">
                            <button type="button" id="payButton" class="btn btn-primary btn-lg">
                                <i class="bi bi-lock me-2"></i>Pay ₹<?= number_format($booking['total_amount'], 2) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Razorpay Checkout Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const payButton = document.getElementById('payButton');
    const bookingIdInput = document.getElementById('bookingId');
    const amountInput = document.getElementById('amount');
    
    if (!payButton || !bookingIdInput || !amountInput) {
        return;
    }
    
    const bookingId = bookingIdInput.value;
    const amount = parseFloat(amountInput.value);
    
    if (typeof Razorpay === 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Payment gateway script failed to load. Please refresh the page.'
        });
        return;
    }
    
    payButton.addEventListener('click', async function() {
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        
        try {
            const response = await fetch('<?= app_url("razorpay-api") ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    booking_id: bookingId,
                    amount: amount
                }),
                credentials: 'same-origin'
            });
            
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                throw new Error('Invalid response from server');
            }
            
            if (!response.ok || data.status !== 'success') {
                const errorMsg = data.message || 'Failed to initialize payment';
                throw new Error(errorMsg);
            }
            
            const orderData = data.data;
            const razorpayKey = '<?= htmlspecialchars($razorpayKeyId) ?>';
            
            if (!razorpayKey || razorpayKey.trim() === '') {
                throw new Error('Payment gateway key is not configured');
            }
            
            const logoUrl = '<?= htmlspecialchars($logoUrl, ENT_QUOTES) ?>';
            
            const options = {
                key: razorpayKey,
                amount: orderData.amount,
                currency: 'INR',
                name: 'Livonto',
                description: 'Booking Payment - <?= htmlspecialchars($listing['title'] ?? 'Booking') ?>',
                order_id: orderData.order_id,
                <?php if (!$isLocalhost): ?>
                image: logoUrl,
                <?php endif; ?>
                handler: function(response) {
                    verifyPayment(response);
                },
                prefill: {
                    name: '<?= htmlspecialchars($user['name'] ?? '') ?>',
                    email: '<?= htmlspecialchars($user['email'] ?? '') ?>',
                    contact: '<?= htmlspecialchars($user['phone'] ?? '') ?>'
                },
                theme: {
                    color: '#007bff'
                },
                modal: {
                    ondismiss: function() {
                        payButton.disabled = false;
                        payButton.innerHTML = '<i class="bi bi-lock me-2"></i>Pay ₹<?= number_format($booking['total_amount'], 2) ?>';
                    }
                }
            };
            
            const razorpay = new Razorpay(options);
            razorpay.open();
            
        } catch (error) {
            let errorMsg = 'Error initializing payment: ' + error.message;
            if (error.message.includes('not configured') || error.message.includes('gateway')) {
                errorMsg = 'Payment gateway is not configured. Please contact administrator.';
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Payment Error',
                text: errorMsg
            });
            
            payButton.disabled = false;
            payButton.innerHTML = '<i class="bi bi-lock me-2"></i>Pay ₹<?= number_format($booking['total_amount'], 2) ?>';
        }
    });
    
    async function verifyPayment(paymentResponse) {
        try {
            const response = await fetch('<?= app_url("razorpay-callback") ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    razorpay_payment_id: paymentResponse.razorpay_payment_id,
                    razorpay_order_id: paymentResponse.razorpay_order_id,
                    razorpay_signature: paymentResponse.razorpay_signature,
                    booking_id: bookingId
                }),
                credentials: 'same-origin'
            });
            
            const responseText = await response.text();
            let data;
            
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                throw new Error('Invalid response from server');
            }
            
            if (!response.ok || data.status !== 'success') {
                const errorMsg = data.message || 'Payment verification failed';
                throw new Error(errorMsg);
            }
            
            if (data.status === 'success') {
                // Generate invoice after successful payment
                generateInvoice(data.data.booking_id);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Payment Successful!',
                    text: 'Your booking has been confirmed. Invoice will be available in your profile.',
                    showConfirmButton: true,
                    confirmButtonText: 'Go to Profile'
                }).then(() => {
                    window.location.href = '<?= app_url("profile") ?>';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Payment Verification Failed',
                    text: data.message || 'Unknown error occurred. Please contact support.'
                });
                payButton.disabled = false;
                payButton.innerHTML = '<i class="bi bi-lock me-2"></i>Pay ₹<?= number_format($booking['total_amount'], 2) ?>';
            }
        } catch (error) {
            let errorMessage = 'Error verifying payment. Please contact support.';
            if (error.message) {
                errorMessage = error.message;
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Verification Error',
                text: errorMessage
            });
            payButton.disabled = false;
            payButton.innerHTML = '<i class="bi bi-lock me-2"></i>Pay ₹<?= number_format($booking['total_amount'], 2) ?>';
        }
    }
    
    // Generate invoice after payment success
    async function generateInvoice(bookingId) {
        try {
            const response = await fetch('<?= app_url("invoice-api") ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    booking_id: bookingId
                }),
                credentials: 'same-origin'
            });
            
            await response.json();
        } catch (error) {
            // Silently fail - invoice generation is optional
        }
    }
});
</script>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

