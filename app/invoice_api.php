<?php
/**
 * Invoice API
 * Generates invoices for confirmed bookings
 */

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/invoice_generator.php';

ob_clean();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    jsonError('Invalid request method', [], 405);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    ob_end_clean();
    jsonError('Please login to continue', [], 401);
    exit;
}

$userId = getCurrentUserId();

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$bookingId = intval($input['booking_id'] ?? 0);

if ($bookingId <= 0) {
    ob_end_clean();
    jsonError('Invalid booking ID', [], 400);
    exit;
}

try {
    $db = db();
    
    // Verify booking belongs to user and is confirmed
    $booking = $db->fetchOne(
        "SELECT b.id, b.status, p.id as payment_id, p.status as payment_status
         FROM bookings b
         INNER JOIN payments p ON b.id = p.booking_id
         WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed' AND p.status = 'success'
         ORDER BY p.id DESC
         LIMIT 1",
        [$bookingId, $userId]
    );
    
    if (!$booking) {
        ob_end_clean();
        jsonError('Booking not found or not eligible for invoice', [], 404);
        exit;
    }
    
    // Check if invoice already exists
    $existingInvoice = $db->fetchOne(
        "SELECT id, invoice_number FROM invoices WHERE booking_id = ? AND payment_id = ? LIMIT 1",
        [$bookingId, $booking['payment_id']]
    );
    
    if ($existingInvoice) {
        ob_end_clean();
        jsonSuccess('Invoice already exists', [
            'invoice_id' => $existingInvoice['id'],
            'invoice_number' => $existingInvoice['invoice_number']
        ]);
        exit;
    }
    
    // Generate invoice
    $invoiceId = createInvoice($bookingId, $booking['payment_id']);
    
    if ($invoiceId === null) {
        ob_end_clean();
        jsonError('Failed to generate invoice. Please contact support.', [], 500);
        exit;
    }
    
    // Get invoice details
    $invoice = $db->fetchOne(
        "SELECT id, invoice_number FROM invoices WHERE id = ?",
        [$invoiceId]
    );
    
    ob_end_clean();
    jsonSuccess('Invoice generated successfully', [
        'invoice_id' => $invoice['id'],
        'invoice_number' => $invoice['invoice_number']
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    jsonError('Failed to generate invoice: ' . $e->getMessage(), [], 500);
}

