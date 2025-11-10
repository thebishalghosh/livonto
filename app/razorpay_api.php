<?php
/**
 * Razorpay API
 * Creates Razorpay orders for payments
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

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

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
$razorpayKeyId = $config['razorpay_key_id'] ?? '';
$razorpayKeySecret = $config['razorpay_key_secret'] ?? '';

if (empty($razorpayKeyId) || empty($razorpayKeySecret)) {
    ob_end_clean();
    jsonError('Payment gateway not configured', [], 500);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$bookingId = intval($input['booking_id'] ?? 0);
$amount = floatval($input['amount'] ?? 0);

if ($bookingId <= 0 || $amount <= 0) {
    ob_end_clean();
    jsonError('Invalid booking ID or amount', [], 400);
    exit;
}

try {
    $db = db();
    
    // Verify booking belongs to user
    $booking = $db->fetchOne(
        "SELECT id, total_amount, status FROM bookings WHERE id = ? AND user_id = ?",
        [$bookingId, $userId]
    );
    
    if (!$booking) {
        ob_end_clean();
        jsonError('Booking not found', [], 404);
        exit;
    }
    
    // Check if booking is already confirmed
    if ($booking['status'] === 'confirmed') {
        ob_end_clean();
        jsonError('Booking is already confirmed', [], 400);
        exit;
    }
    
    // Verify amount matches
    if (abs($booking['total_amount'] - $amount) > 0.01) {
        ob_end_clean();
        jsonError('Amount mismatch', [], 400);
        exit;
    }
    
    // Convert amount to paise (Razorpay uses smallest currency unit)
    $amountInPaise = (int)($amount * 100);
    
    // Create Razorpay order
    $orderData = [
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'receipt' => 'booking_' . $bookingId . '_' . time(),
        'notes' => [
            'booking_id' => $bookingId,
            'user_id' => $userId
        ]
    ];
    
    // Initialize cURL for Razorpay API
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
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
    
    if ($curlError || $httpCode !== 200) {
        ob_end_clean();
        $errorMsg = 'Failed to create payment order';
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            if ($errorData && isset($errorData['error'])) {
                $errorMsg = $errorData['error']['description'] ?? $errorData['error']['message'] ?? $errorMsg;
            }
        }
        jsonError($errorMsg, [], 500);
        exit;
    }
    
    $razorpayOrder = json_decode($response, true);
    
    if (!$razorpayOrder || !isset($razorpayOrder['id'])) {
        ob_end_clean();
        jsonError('Invalid response from payment gateway', [], 500);
        exit;
    }
    
    // Update payment record with order ID
    $payment = $db->fetchOne(
        "SELECT id FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1",
        [$bookingId]
    );
    
    if ($payment) {
        $db->execute(
            "UPDATE payments SET provider = 'razorpay', provider_payment_id = ? WHERE id = ?",
            [$razorpayOrder['id'], $payment['id']]
        );
    } else {
        // Create payment record if it doesn't exist
        $db->execute(
            "INSERT INTO payments (booking_id, amount, provider, provider_payment_id, status)
             VALUES (?, ?, 'razorpay', ?, 'initiated')",
            [$bookingId, $amount, $razorpayOrder['id']]
        );
    }
    
    ob_end_clean();
    jsonSuccess('Order created successfully', [
        'order_id' => $razorpayOrder['id'],
        'amount' => $amountInPaise,
        'currency' => 'INR'
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    jsonError('Failed to create payment order. Please try again.', [], 500);
}

