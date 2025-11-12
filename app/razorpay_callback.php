<?php
/**
 * Razorpay Callback
 * Verifies and processes Razorpay payment callbacks
 */

// Prevent any output before headers
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header early
header('Content-Type: application/json');

// Suppress any warnings/notices that might break JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        // Clean all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Ensure we can output
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        
        $errorDetails = 'Unknown fatal error';
        if ($error) {
            $errorDetails = $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'];
        }
        
        // Always output JSON, even if encoding fails
        $response = [
            'status' => 'error',
            'message' => 'Internal server error occurred',
            'errors' => ['fatal_error' => $errorDetails]
        ];
        
        $json = @json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || $json === null) {
            echo '{"status":"error","message":"Internal server error occurred"}';
        } else {
            echo $json;
        }
        exit;
    }
});

// Clear any output buffer
ob_clean();

// Check if user is logged in
if (!isLoggedIn()) {
    ob_end_clean();
    jsonError('Please login to continue', [], 401);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    jsonError('Invalid request method', [], 405);
    exit;
}

$userId = getCurrentUserId();

$config = require __DIR__ . '/config.php';
$razorpayKeySecret = $config['razorpay_key_secret'] ?? '';

if (empty($razorpayKeySecret)) {
    ob_end_clean();
    jsonError('Payment gateway not configured', [], 500);
    exit;
}

// Get request data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    jsonError('Invalid JSON in request', [], 400);
    exit;
}

$razorpayPaymentId = $input['razorpay_payment_id'] ?? '';
$razorpayOrderId = $input['razorpay_order_id'] ?? '';
$razorpaySignature = $input['razorpay_signature'] ?? '';
$bookingId = intval($input['booking_id'] ?? 0);

if (empty($razorpayPaymentId) || empty($razorpayOrderId) || empty($razorpaySignature) || $bookingId <= 0) {
    ob_end_clean();
    jsonError('Invalid payment data', [], 400);
    exit;
}

try {
    $db = db();
    
    // Verify booking belongs to user
    $booking = $db->fetchOne(
        "SELECT id, total_amount, status, room_config_id FROM bookings WHERE id = ? AND user_id = ?",
        [$bookingId, $userId]
    );
    
    if (!$booking) {
        ob_end_clean();
        jsonError('Booking not found', [], 404);
        exit;
    }
    
    // Verify Razorpay signature
    $payload = $razorpayOrderId . '|' . $razorpayPaymentId;
    $generatedSignature = hash_hmac('sha256', $payload, $razorpayKeySecret);
    
    if ($generatedSignature !== $razorpaySignature) {
        ob_end_clean();
        jsonError('Payment verification failed', [], 400);
        exit;
    }
    
    // Verify payment with Razorpay API
    $razorpayKeyId = $config['razorpay_key_id'] ?? '';
    $ch = curl_init('https://api.razorpay.com/v1/payments/' . $razorpayPaymentId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($razorpayKeyId . ':' . $razorpayKeySecret)
    ]);
    
    $certPath = ini_get('curl.cainfo');
    if (empty($certPath) || !file_exists($certPath)) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    } else {
        curl_setopt($ch, CURLOPT_CAINFO, $certPath);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        ob_end_clean();
        jsonError('Failed to verify payment', [], 500);
        exit;
    }
    
    if ($httpCode !== 200) {
        ob_end_clean();
        jsonError('Failed to verify payment', [], 500);
        exit;
    }
    
    $paymentData = json_decode($response, true);
    
    if (!$paymentData || $paymentData['status'] !== 'authorized' && $paymentData['status'] !== 'captured') {
        ob_end_clean();
        jsonError('Payment not successful', [], 400);
        exit;
    }
    
    // Verify amount matches
    $paidAmount = floatval($paymentData['amount'] / 100); // Convert from paise
    if (abs($booking['total_amount'] - $paidAmount) > 0.01) {
        ob_end_clean();
        jsonError('Amount mismatch', [], 400);
        exit;
    }
    
    $payment = $db->fetchOne(
        "SELECT id, status FROM payments WHERE booking_id = ? AND (status = 'initiated' OR status = 'success') ORDER BY id DESC LIMIT 1",
        [$bookingId]
    );
    
    if (!$payment) {
        ob_end_clean();
        jsonError('Payment record not found', [], 404);
        exit;
    }
    
    $paymentId = $payment['id'];
    
    if ($payment['status'] === 'success') {
        ob_end_clean();
        jsonSuccess('Payment already verified', [
            'booking_id' => $bookingId,
            'payment_id' => $razorpayPaymentId
        ]);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Update payment record
        $db->execute(
            "UPDATE payments 
             SET provider = 'razorpay', 
                 provider_payment_id = ?, 
                 status = 'success'
             WHERE id = ?",
            [$razorpayPaymentId, $paymentId]
        );
        
        // Update booking status
        $db->execute(
            "UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'",
            [$bookingId]
        );
        
        // Note: available_rooms was already decreased when booking was created (pending)
        // So we don't need to decrease again when confirmed, as it's already accounted for
        // The availability was already updated in app/book_api.php when the booking was created
        
        $db->commit();
        ob_end_clean();
        jsonSuccess('Payment verified and booking confirmed', [
            'booking_id' => $bookingId,
            'payment_id' => $razorpayPaymentId
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    $errorResponse = [
        'status' => 'error',
        'message' => 'Failed to process payment: ' . $e->getMessage(),
        'errors' => ['error' => $e->getMessage()]
    ];
    
    http_response_code(500);
    $json = @json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || $json === null) {
        echo '{"status":"error","message":"Failed to process payment"}';
    } else {
        echo $json;
    }
    exit;
}

