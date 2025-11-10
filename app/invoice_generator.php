<?php
/**
 * Invoice Generator
 * Generates invoices for bookings after successful payment
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Generate invoice number
 * Format: INV-YYYYMMDD-XXXX (e.g., INV-20240115-0001)
 */
function generateInvoiceNumber($db) {
    $today = date('Ymd');
    $prefix = 'INV-' . $today . '-';
    
    // Get the last invoice number for today
    $lastInvoice = $db->fetchValue(
        "SELECT invoice_number FROM invoices 
         WHERE invoice_number LIKE ? 
         ORDER BY invoice_number DESC 
         LIMIT 1",
        [$prefix . '%']
    );
    
    if ($lastInvoice) {
        // Extract the sequence number
        $sequence = intval(substr($lastInvoice, -4));
        $sequence++;
    } else {
        $sequence = 1;
    }
    
    return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

/**
 * Create invoice for a booking after successful payment
 */
function createInvoice($bookingId, $paymentId) {
    try {
        $db = db();
        
        // Verify invoices table exists (will throw if it doesn't)
        $db->fetchValue("SELECT 1 FROM invoices LIMIT 1");
        
        $booking = $db->fetchOne(
            "SELECT b.*, 
                    u.name as user_name, u.email as user_email, u.phone as user_phone,
                    u.address as user_address, u.city as user_city, u.state as user_state, u.pincode as user_pincode,
                    l.title as listing_title, l.owner_name,
                    loc.complete_address as listing_address, loc.city as listing_city, loc.pin_code as listing_pincode,
                    rc.room_type, rc.rent_per_month
             FROM bookings b
             INNER JOIN users u ON b.user_id = u.id
             INNER JOIN listings l ON b.listing_id = l.id
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             LEFT JOIN room_configurations rc ON b.room_config_id = rc.id
             WHERE b.id = ?",
            [$bookingId]
        );
        
        if (!$booking) {
            throw new Exception('Booking not found');
        }
        
        // Get payment details separately
        $payment = $db->fetchOne(
            "SELECT amount, provider, provider_payment_id, created_at 
             FROM payments 
             WHERE id = ? AND booking_id = ?",
            [$paymentId, $bookingId]
        );
        
        if (!$payment) {
            throw new Exception('Payment record not found');
        }
        
        // Merge payment data into booking array
        $booking['amount'] = $payment['amount'];
        $booking['provider'] = $payment['provider'];
        $booking['provider_payment_id'] = $payment['provider_payment_id'];
        $booking['payment_date'] = $payment['created_at'];
        
        // Check if invoice already exists
        $existingInvoice = $db->fetchOne(
            "SELECT id FROM invoices WHERE booking_id = ? AND payment_id = ?",
            [$bookingId, $paymentId]
        );
        
        if ($existingInvoice) {
            return $existingInvoice['id'];
        }
        
        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber($db);
        
        // Calculate booking end date (1 month from start date)
        $startDate = new DateTime($booking['booking_start_date']);
        $endDate = clone $startDate;
        $endDate->modify('+1 month');
        $endDate->modify('-1 day'); // Last day of the month
        
        // Create invoice
        $db->execute(
            "INSERT INTO invoices (invoice_number, booking_id, payment_id, user_id, invoice_date, total_amount, status)
             VALUES (?, ?, ?, ?, CURDATE(), ?, 'paid')",
            [
                $invoiceNumber,
                $bookingId,
                $paymentId,
                $booking['user_id'],
                $booking['amount']
            ]
        );
        
        return $db->lastInsertId();
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Get invoice data for display
 */
function getInvoiceData($invoiceId, $userId = null) {
    try {
        $db = db();
        
        $sql = "SELECT i.*,
                       b.booking_start_date, b.special_requests,
                       u.name as user_name, u.email as user_email, u.phone as user_phone,
                       u.address as user_address, u.city as user_city, u.state as user_state, u.pincode as user_pincode,
                       l.title as listing_title, l.owner_name,
                       loc.complete_address as listing_address, loc.city as listing_city, loc.pin_code as listing_pincode,
                       rc.room_type, rc.rent_per_month,
                       p.amount, p.provider, p.provider_payment_id, p.created_at as payment_date
                FROM invoices i
                INNER JOIN bookings b ON i.booking_id = b.id
                INNER JOIN users u ON i.user_id = u.id
                INNER JOIN listings l ON b.listing_id = l.id
                LEFT JOIN listing_locations loc ON l.id = loc.listing_id
                LEFT JOIN room_configurations rc ON b.room_config_id = rc.id
                INNER JOIN payments p ON i.payment_id = p.id
                WHERE i.id = ?";
        
        $params = [$invoiceId];
        
        // If userId is provided, verify ownership
        if ($userId !== null) {
            $sql .= " AND i.user_id = ?";
            $params[] = $userId;
        }
        
        $invoice = $db->fetchOne($sql, $params);
        
        if (!$invoice) {
            return null;
        }
        
        // Calculate booking end date
        $startDate = new DateTime($invoice['booking_start_date']);
        $endDate = clone $startDate;
        $endDate->modify('+1 month');
        $endDate->modify('-1 day');
        
        $invoice['booking_end_date'] = $endDate->format('Y-m-d');
        $invoice['duration_months'] = 1;
        
        return $invoice;
        
    } catch (Exception $e) {
        return null;
    }
}

