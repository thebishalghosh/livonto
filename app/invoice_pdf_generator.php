<?php
/**
 * Invoice PDF Generator
 * Generates PDF from invoice HTML and saves to storage/invoices/
 * Uses DomPDF to render the invoice exactly as it appears on the page
 */

// Load Composer autoloader for DomPDF
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/invoice_generator.php';

/**
 * Generate and save invoice PDF to storage
 * 
 * @param int $invoiceId Invoice ID
 * @return string|null Path to saved PDF file (relative to root) or null on failure
 */
function generateInvoicePDF($invoiceId) {
    try {
        if (!class_exists('Dompdf\Dompdf')) {
            error_log("DomPDF not installed. Install via: composer require dompdf/dompdf");
            return null;
        }
        
        $invoice = getInvoiceData($invoiceId);
        if (!$invoice) {
            error_log("Invoice not found: {$invoiceId}");
            return null;
        }
        
        $baseUrl = rtrim(app_url(''), '/');
        
        $logoPath = __DIR__ . '/../public/assets/images/logo-removebg.png';
        $logoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logoPath);
        
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoMime = mime_content_type($logoPath) ?: 'image/png';
            $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
        }
        
        $html = generateInvoiceHTML($invoice, $baseUrl, $logoBase64);
        
        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $invoiceDir = __DIR__ . '/../storage/invoices/';
        $invoiceDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $invoiceDir);
        $invoiceDir = rtrim($invoiceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        if (!is_dir($invoiceDir)) {
            $oldUmask = umask(0);
            $created = @mkdir($invoiceDir, 0755, true);
            umask($oldUmask);
            
            if (!$created && !is_dir($invoiceDir)) {
                error_log("Failed to create invoices directory: {$invoiceDir}");
                return null;
            }
        }
        
        if (!is_writable($invoiceDir)) {
            @chmod($invoiceDir, 0755);
            if (!is_writable($invoiceDir)) {
                error_log("Invoices directory is not writable: {$invoiceDir}");
                return null;
            }
        }
        
        $filename = 'invoice_' . $invoice['invoice_number'] . '_' . time() . '.pdf';
        $filepath = $invoiceDir . $filename;
        
        $pdfOutput = $dompdf->output();
        
        if (empty($pdfOutput)) {
            error_log("DomPDF output is empty for invoice ID: {$invoiceId}");
            return null;
        }
        
        $bytesWritten = @file_put_contents($filepath, $pdfOutput);
        
        if ($bytesWritten === false || $bytesWritten === 0 || !file_exists($filepath)) {
            error_log("Failed to save PDF file: {$filepath}");
            return null;
        }
        
        @chmod($filepath, 0644);
        
        return 'storage/invoices/' . $filename;
        
    } catch (Exception $e) {
        error_log("Error generating invoice PDF: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate HTML for invoice PDF (matches invoice.php structure)
 * 
 * @param array $invoice Invoice data
 * @param string $baseUrl Base URL for assets
 * @param string $logoBase64 Base64 encoded logo image (optional)
 * @return string HTML content
 */
function generateInvoiceHTML($invoice, $baseUrl, $logoBase64 = '') {
    $invoiceDate = date('F d, Y', strtotime($invoice['invoice_date']));
    $status = strtoupper($invoice['status'] ?? 'PAID');
    $statusClass = ($status === 'PAID') ? 'status-paid' : '';
    
    // Get duration from invoice (default to 1 if not set)
    $durationMonths = isset($invoice['duration_months']) ? (int)$invoice['duration_months'] : 1;
    if ($durationMonths < 1) $durationMonths = 1;
    
    $startDate = new DateTime($invoice['booking_start_date']);
    $endDate = clone $startDate;
    $endDate->modify("+{$durationMonths} months");
    $endDate->modify('-1 day'); // Last day of the last month
    $bookingEndDate = $endDate->format('F d, Y');
    $bookingStartDate = date('F d, Y', strtotime($invoice['booking_start_date']));
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <style>
    @charset "UTF-8";
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: "DejaVu Sans", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      font-size: 12px;
      line-height: 1.4;
      color: #333;
      background: #fff;
      padding: 20mm;
    }
    
    .invoice-container {
      max-width: 210mm;
      margin: 0 auto;
      background: #fff;
    }
    
    .invoice-header {
      width: 100%;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 2px solid #8b6bd1;
    }
    
    .invoice-header-table {
      width: 100%;
      border-collapse: collapse;
      border: none;
      border-spacing: 0;
      margin: 0;
      padding: 0;
    }
    
    .invoice-header-table tr {
      border: none;
    }
    
    .invoice-header-table td {
      border: none;
      padding: 0;
      vertical-align: middle;
      margin: 0;
    }
    
    .logo-section {
      width: 50%;
      text-align: left;
    }
    
    .logo-section img {
      height: 90px;
      max-width: 250px;
      display: block;
    }
    
    .invoice-info {
      width: 50%;
      text-align: right;
      vertical-align: middle;
    }
    
    .invoice-info h1 {
      font-size: 28px;
      color: #8b6bd1;
      margin-bottom: 10px;
      font-weight: 700;
    }
    
    .invoice-info p {
      margin: 3px 0;
      font-size: 11px;
      color: #666;
    }
    
    .invoice-info strong {
      color: #333;
      font-weight: 600;
    }
    
    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      margin-top: 5px;
    }
    
    .status-paid { background: #d1fae5; color: #065f46; }
    
    .invoice-details {
      margin-bottom: 20px;
    }
    
    .details-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    
    .details-table td {
      padding: 8px;
      vertical-align: top;
      font-size: 11px;
    }
    
    .details-table .label {
      font-weight: 600;
      color: #666;
      width: 30%;
    }
    
    .details-table .value {
      color: #333;
    }
    
    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    
    .items-table thead {
      background-color: #8b6bd1;
      color: #ffffff;
    }
    
    .items-table th {
      padding: 10px;
      text-align: left;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      border: 1px solid #6f55b2;
      background-color: #8b6bd1;
      color: #ffffff;
    }
    
    .items-table th.text-right {
      text-align: right;
      background-color: #8b6bd1;
      color: #ffffff;
    }
    
    .items-table th.text-center {
      text-align: center;
      background-color: #8b6bd1;
      color: #ffffff;
    }
    
    .items-table td {
      padding: 10px;
      border: 1px solid #e0e0e0;
      font-size: 11px;
      vertical-align: top;
    }
    
    .items-table tbody tr:nth-child(even) {
      background: #f9f9f9;
    }
    
    .items-table .text-right {
      text-align: right;
    }
    
    .items-table .text-center {
      text-align: center;
    }
    
    .items-table .item-title {
      font-weight: 600;
      color: #333;
      margin-bottom: 3px;
    }
    
    .items-table .item-desc {
      font-size: 10px;
      color: #666;
    }
    
    .totals-section {
      margin-top: 20px;
      margin-left: auto;
      width: 300px;
    }
    
    .totals-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .totals-table td {
      padding: 6px 10px;
      font-size: 11px;
      border-bottom: 1px solid #e0e0e0;
    }
    
    .totals-table td:first-child {
      text-align: right;
      color: #666;
    }
    
    .totals-table td:last-child {
      text-align: right;
      font-weight: 600;
      color: #333;
    }
    
    .totals-table .total-row {
      border-top: 2px solid #8b6bd1;
      border-bottom: 2px solid #8b6bd1;
      font-size: 14px;
      font-weight: 700;
      color: #8b6bd1;
    }
    
    .totals-table .total-row td {
      padding: 10px;
      border-bottom: none;
    }
    
    .payment-info {
      margin-top: 20px;
      padding: 12px;
      background: #f9f9f9;
      border-left: 3px solid #8b6bd1;
      font-size: 11px;
    }
    
    .payment-info p {
      margin: 3px 0;
    }
    
    .payment-info strong {
      color: #666;
      display: inline-block;
      width: 120px;
    }
    
    .footer {
      margin-top: 25px;
      padding-top: 15px;
      border-top: 1px solid #e0e0e0;
      text-align: center;
      font-size: 10px;
      color: #999;
    }
  </style>
</head>
<body>
    <div class="invoice-container">
    <!-- Header -->
    <div class="invoice-header">
      <table class="invoice-header-table">
        <tr>
          <td class="logo-section">';
    
    if (!empty($logoBase64)) {
        $html .= '<img src="' . htmlspecialchars($logoBase64) . '" alt="Livonto" style="height: 90px; max-width: 250px; display: block;">';
    }
    
    $html .= '
          </td>
          <td class="invoice-info">
            <h1>INVOICE</h1>
            <p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>
            <p><strong>Date:</strong> ' . htmlspecialchars($invoiceDate) . '</p>
            <span class="status-badge ' . htmlspecialchars($statusClass) . '">' . htmlspecialchars($status) . '</span>
          </td>
        </tr>
      </table>
    </div>

    <!-- Bill To & Property Details -->
    <div class="invoice-details">
      <table class="details-table">
        <tr>
          <td class="label">Bill To:</td>
          <td class="value">
            <strong>' . htmlspecialchars($invoice['user_name']) . '</strong><br>
            ' . htmlspecialchars($invoice['user_email']) . '<br>';
    
    if (!empty($invoice['user_phone'])) {
        $html .= htmlspecialchars($invoice['user_phone']) . '<br>';
    }
    if (!empty($invoice['user_address'])) {
        $html .= nl2br(htmlspecialchars($invoice['user_address'])) . '<br>';
    }
    if (!empty($invoice['user_city']) || !empty($invoice['user_state']) || !empty($invoice['user_pincode'])) {
        $html .= htmlspecialchars(trim(($invoice['user_city'] ?? '') . ', ' . ($invoice['user_state'] ?? '') . ' - ' . ($invoice['user_pincode'] ?? '')));
    }
    
    $html .= '
          </td>
          <td class="label">Property:</td>
          <td class="value">
            <strong>' . htmlspecialchars($invoice['listing_title']) . '</strong><br>';
    
    if (!empty($invoice['listing_address'])) {
        $html .= htmlspecialchars($invoice['listing_address']) . '<br>';
    }
    if (!empty($invoice['listing_city'])) {
        $html .= htmlspecialchars($invoice['listing_city']);
        if (!empty($invoice['listing_pincode'])) {
            $html .= ' - ' . htmlspecialchars($invoice['listing_pincode']);
        }
    }
    
    $html .= '
          </td>
        </tr>
      </table>
    </div>

    <!-- Items Table -->
    <table class="items-table">
      <thead>
        <tr>
          <th style="width: 50%;">Description</th>
          <th style="width: 15%;" class="text-center">Duration</th>
          <th style="width: 15%;" class="text-center">Room Type</th>
          <th style="width: 20%;" class="text-right">Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <div class="item-title">PG Booking - ' . htmlspecialchars($invoice['listing_title']) . '</div>
            <div class="item-desc">
              Booking Period: ' . htmlspecialchars($bookingStartDate) . ' to ' . htmlspecialchars($bookingEndDate) . '
            </div>
          </td>
          <td class="text-center">' . (int)$invoice['duration_months'] . ' Month' . ((int)$invoice['duration_months'] > 1 ? 's' : '') . '</td>
          <td class="text-center">' . htmlspecialchars($invoice['room_type'] ?? 'N/A') . '</td>
          <td class="text-right"><strong>&#8377;' . number_format($invoice['rent_per_month'] ?? $invoice['total_amount'], 2) . '</strong></td>
        </tr>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
      <table class="totals-table">
        <tr>
          <td>Security Deposit:</td>
          <td>&#8377;' . number_format($invoice['total_amount'], 2) . '</td>
        </tr>
        <tr>
          <td>Tax (GST):</td>
          <td>&#8377;0.00</td>
        </tr>
        <tr class="total-row">
          <td>Total Amount Paid (Security Deposit):</td>
          <td>&#8377;' . number_format($invoice['total_amount'], 2) . '</td>
        </tr>
      </table>
      <p style="margin-top: 10px; font-size: 10px; color: #666; font-style: italic;">
        <strong>Note:</strong> This invoice is for security deposit payment only. Monthly rent will be collected separately as per the booking agreement.
      </p>
    </div>

    <!-- Payment Information -->
    <div class="payment-info">
      <p><strong>Payment Method:</strong> ' . htmlspecialchars(strtoupper($invoice['provider'] ?? 'Razorpay')) . '</p>
      <p><strong>Transaction ID:</strong> ' . htmlspecialchars($invoice['provider_payment_id'] ?? 'N/A') . '</p>
      <p><strong>Payment Date:</strong> ' . (!empty($invoice['payment_date']) ? date('F d, Y h:i A', strtotime($invoice['payment_date'])) : 'N/A') . '</p>
      <p><strong>Payment Type:</strong> Security Deposit</p>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>This is a computer-generated invoice and does not require a signature.</p>
      <p>&copy; ' . date('Y') . ' Livonto. All rights reserved. | For queries, contact support@livonto.com</p>
    </div>
  </div>
</body>
</html>';
    
    return $html;
}

/**
 * Get PDF path for an invoice
 * 
 * @param int $invoiceId Invoice ID
 * @return string|null PDF path or null if not found
 */
function getInvoicePDFPath($invoiceId) {
    try {
        $db = db();
        $invoice = $db->fetchOne("SELECT invoice_number FROM invoices WHERE id = ?", [$invoiceId]);
        if (!$invoice) {
            return null;
        }
        
        $invoiceDir = __DIR__ . '/../storage/invoices/';
        $invoiceDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $invoiceDir);
        $invoiceDir = rtrim($invoiceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        if (!is_dir($invoiceDir)) {
            return null;
        }
        
        // Find PDF file by invoice number
        $files = glob($invoiceDir . 'invoice_' . $invoice['invoice_number'] . '_*.pdf');
        if (!empty($files)) {
            // Return the most recent one
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            // Get project root directory
            $projectRoot = dirname(__DIR__);
            $projectRoot = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot);
            $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            
            // Convert absolute path to relative path from project root
            $filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $files[0]);
            if (strpos($filePath, $projectRoot) === 0) {
                $relativePath = substr($filePath, strlen($projectRoot));
            } else {
                // Fallback: extract just the filename and directory structure
                $relativePath = 'storage/invoices/' . basename($files[0]);
            }
            
            return str_replace('\\', '/', $relativePath);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting invoice PDF path: " . $e->getMessage());
        return null;
    }
}

