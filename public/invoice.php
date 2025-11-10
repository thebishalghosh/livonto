<?php
/**
 * Invoice View - Simple Professional A4 Format
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/invoice_generator.php';

if (!isLoggedIn()) {
    header('Location: ' . app_url('login'));
    exit;
}

$userId = getCurrentUserId();
$invoiceId = intval($_GET['id'] ?? 0);

if ($invoiceId <= 0) {
    header('Location: ' . app_url('profile'));
    exit;
}

$invoice = getInvoiceData($invoiceId, $userId);

if (!$invoice) {
    header('Location: ' . app_url('profile'));
    exit;
}

$pageTitle = "Invoice #" . htmlspecialchars($invoice['invoice_number']);
$baseUrl = rtrim(app_url(''), '/');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - Livonto</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      font-size: 12px;
      line-height: 1.4;
      color: #333;
      background: #f5f5f5;
      padding: 20px;
    }
    
    .invoice-container {
      max-width: 210mm;
      margin: 0 auto;
      background: #fff;
      padding: 20mm;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    
    .invoice-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 2px solid #8b6bd1;
    }
    
    .logo-section img {
      height: 90px;
      max-width: 250px;
    }
    
    .invoice-info {
      text-align: right;
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
      background: linear-gradient(90deg, #8b6bd1 0%, #6f55b2 100%);
      color: #fff;
    }
    
    .items-table th {
      padding: 10px;
      text-align: left;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      border: 1px solid #6f55b2;
    }
    
    .items-table th.text-right {
      text-align: right;
    }
    
    .items-table th.text-center {
      text-align: center;
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
    
    .actions {
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
    }
    
    .btn {
      padding: 8px 16px;
      border-radius: 5px;
      text-decoration: none;
      font-size: 12px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
    }
    
    .btn-outline {
      background: transparent;
      border: 1px solid #8b6bd1;
      color: #8b6bd1;
    }
    
    .btn-outline:hover {
      background: #8b6bd1;
      color: #fff;
    }
    
    .btn-primary {
      background: linear-gradient(90deg, #8b6bd1 0%, #6f55b2 100%);
      color: #fff;
    }
    
    .btn-primary:hover {
      background: linear-gradient(90deg, #6f55b2 0%, #8b6bd1 100%);
    }
    
    @media print {
      @page {
        size: A4;
        margin: 0;
      }
      body {
        background: #fff;
        padding: 0;
      }
      .invoice-container {
        max-width: 100%;
        padding: 15mm;
        box-shadow: none;
      }
      .actions {
        display: none;
      }
      .items-table tbody tr:nth-child(even) {
        background: #fff;
      }
    }
  </style>
</head>
<body>
  <div class="invoice-container">
    <!-- Actions -->
    <div class="actions no-print">
      <a href="<?= app_url('profile') ?>" class="btn btn-outline">
        <i class="bi bi-arrow-left"></i> Back to Profile
      </a>
      <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn btn-outline">
          <i class="bi bi-printer"></i> Print
        </button>
        <a href="<?= app_url('invoice?id=' . $invoiceId . '&download=1') ?>" class="btn btn-primary">
          <i class="bi bi-download"></i> Download PDF
        </a>
      </div>
    </div>

    <!-- Header -->
    <div class="invoice-header">
      <div class="logo-section">
        <img src="<?= htmlspecialchars($baseUrl . '/public/assets/images/logo-removebg.png') ?>" alt="Livonto" onerror="this.style.display='none'">
      </div>
      <div class="invoice-info">
        <h1>INVOICE</h1>
        <p><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></p>
        <p><strong>Date:</strong> <?= date('F d, Y', strtotime($invoice['invoice_date'])) ?></p>
        <?php
          $status = strtoupper($invoice['status'] ?? 'PAID');
          $statusClass = ($status === 'PAID') ? 'status-paid' : '';
        ?>
        <span class="status-badge <?= $statusClass ?>"><?= $status ?></span>
      </div>
    </div>

    <!-- Bill To & Property Details -->
    <div class="invoice-details">
      <table class="details-table">
        <tr>
          <td class="label">Bill To:</td>
          <td class="value">
            <strong><?= htmlspecialchars($invoice['user_name']) ?></strong><br>
            <?= htmlspecialchars($invoice['user_email']) ?><br>
            <?php if (!empty($invoice['user_phone'])): ?>
              <?= htmlspecialchars($invoice['user_phone']) ?><br>
            <?php endif; ?>
            <?php if (!empty($invoice['user_address'])): ?>
              <?= nl2br(htmlspecialchars($invoice['user_address'])) ?><br>
            <?php endif; ?>
            <?php if (!empty($invoice['user_city']) || !empty($invoice['user_state']) || !empty($invoice['user_pincode'])): ?>
              <?= htmlspecialchars(trim(($invoice['user_city'] ?? '') . ', ' . ($invoice['user_state'] ?? '') . ' - ' . ($invoice['user_pincode'] ?? ''))) ?>
            <?php endif; ?>
          </td>
          <td class="label">Property:</td>
          <td class="value">
            <strong><?= htmlspecialchars($invoice['listing_title']) ?></strong><br>
            <?php if (!empty($invoice['listing_address'])): ?>
              <?= htmlspecialchars($invoice['listing_address']) ?><br>
            <?php endif; ?>
            <?php if (!empty($invoice['listing_city'])): ?>
              <?= htmlspecialchars($invoice['listing_city']) ?>
              <?php if (!empty($invoice['listing_pincode'])): ?>
                - <?= htmlspecialchars($invoice['listing_pincode']) ?>
              <?php endif; ?>
            <?php endif; ?>
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
            <div class="item-title">PG Booking - <?= htmlspecialchars($invoice['listing_title']) ?></div>
            <div class="item-desc">
              Booking Period: <?= date('F d, Y', strtotime($invoice['booking_start_date'])) ?> to <?= date('F d, Y', strtotime($invoice['booking_end_date'])) ?>
            </div>
          </td>
          <td class="text-center"><?= (int)$invoice['duration_months'] ?> Month<?= ((int)$invoice['duration_months'] > 1) ? 's' : '' ?></td>
          <td class="text-center"><?= htmlspecialchars($invoice['room_type'] ?? 'N/A') ?></td>
          <td class="text-right"><strong>₹<?= number_format($invoice['rent_per_month'] ?? $invoice['total_amount'], 2) ?></strong></td>
        </tr>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
      <table class="totals-table">
        <tr>
          <td>Subtotal:</td>
          <td>₹<?= number_format($invoice['total_amount'], 2) ?></td>
        </tr>
        <tr>
          <td>Tax (GST):</td>
          <td>₹0.00</td>
        </tr>
        <tr class="total-row">
          <td>Total Amount:</td>
          <td>₹<?= number_format($invoice['total_amount'], 2) ?></td>
        </tr>
      </table>
    </div>

    <!-- Payment Information -->
    <div class="payment-info">
      <p><strong>Payment Method:</strong> <?= htmlspecialchars(strtoupper($invoice['provider'] ?? 'Razorpay')) ?></p>
      <p><strong>Transaction ID:</strong> <?= htmlspecialchars($invoice['provider_payment_id'] ?? 'N/A') ?></p>
      <p><strong>Payment Date:</strong> <?= !empty($invoice['payment_date']) ? date('F d, Y h:i A', strtotime($invoice['payment_date'])) : 'N/A' ?></p>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>This is a computer-generated invoice and does not require a signature.</p>
      <p>&copy; <?= date('Y') ?> Livonto. All rights reserved. | For queries, contact support@livonto.com</p>
    </div>
  </div>
</body>
</html>
