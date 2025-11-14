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
        
        // Get duration from booking (default to 1 if not set)
        $durationMonths = isset($booking['duration_months']) ? (int)$booking['duration_months'] : 1;
        if ($durationMonths < 1) $durationMonths = 1;
        
        // Calculate booking end date based on duration
        $startDate = new DateTime($booking['booking_start_date']);
        $endDate = clone $startDate;
        $endDate->modify("+{$durationMonths} months");
        $endDate->modify('-1 day'); // Last day of the last month
        
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
        
        $invoiceId = $db->lastInsertId();
        
        // Generate and save PDF to storage
        try {
            require_once __DIR__ . '/invoice_pdf_generator.php';
            $pdfPath = generateInvoicePDF($invoiceId);
            if ($pdfPath) {
                error_log("Invoice PDF saved to storage: {$pdfPath}");
            } else {
                error_log("Invoice PDF generation returned null for invoice ID: {$invoiceId}. Check error logs above for details.");
            }
        } catch (Exception $e) {
            // Log error but don't fail invoice creation
            error_log("Failed to generate invoice PDF for invoice ID {$invoiceId}: " . $e->getMessage());
        } catch (Error $e) {
            // Catch fatal errors too
            error_log("Fatal error generating invoice PDF for invoice ID {$invoiceId}: " . $e->getMessage());
        }
        
        // Send email notification
        try {
            require_once __DIR__ . '/email_helper.php';
            sendInvoiceEmail($invoiceId, $booking['user_email'], $booking['user_name']);
        } catch (Exception $e) {
            // Log error but don't fail invoice creation
            error_log("Failed to send invoice email notification: " . $e->getMessage());
        }
        
        return $invoiceId;
        
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
                       b.booking_start_date, b.duration_months, b.special_requests, b.gst_amount as booking_gst_amount,
                       u.name as user_name, u.email as user_email, u.phone as user_phone,
                       u.address as user_address, u.city as user_city, u.state as user_state, u.pincode as user_pincode,
                       l.title as listing_title, l.owner_name,
                       loc.complete_address as listing_address, loc.city as listing_city, loc.pin_code as listing_pincode,
                       rc.room_type, rc.rent_per_month,
                       p.amount, p.gst_amount as payment_gst_amount, p.provider, p.provider_payment_id, p.created_at as payment_date
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
        
        // Get duration from booking (default to 1 if not set)
        $durationMonths = isset($invoice['duration_months']) ? (int)$invoice['duration_months'] : 1;
        if ($durationMonths < 1) $durationMonths = 1;
        
        // Calculate booking end date based on duration
        $startDate = new DateTime($invoice['booking_start_date']);
        $endDate = clone $startDate;
        $endDate->modify("+{$durationMonths} months");
        $endDate->modify('-1 day');
        
        $invoice['booking_end_date'] = $endDate->format('Y-m-d');
        $invoice['duration_months'] = $durationMonths;
        
        // Get GST amount (prefer payment gst_amount, fallback to booking gst_amount)
        $gstAmount = isset($invoice['payment_gst_amount']) ? floatval($invoice['payment_gst_amount']) : 
                     (isset($invoice['booking_gst_amount']) ? floatval($invoice['booking_gst_amount']) : 0);
        $invoice['gst_amount'] = $gstAmount;
        
        // Calculate GST percentage if GST amount exists
        if ($gstAmount > 0 && isset($invoice['total_amount']) && $invoice['total_amount'] > 0) {
            $invoice['gst_percentage'] = ($gstAmount / $invoice['total_amount']) * 100;
        } else {
            $invoice['gst_percentage'] = 0;
        }
        
        return $invoice;
        
    } catch (Exception $e) {
        return null;
    }
}

