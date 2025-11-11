<?php
/**
 * Email Template Test Page
 * Preview the invoice email template with logo
 */

// Load logo as base64 for testing
$logoPath = __DIR__ . '/public/assets/images/logo-removebg.png';
$logoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logoPath);
$logoBase64 = '';
$logoImg = '';

if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $logoMime = mime_content_type($logoPath) ?: 'image/png';
    $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
    $logoImg = "<img src='{$logoBase64}' alt='Livonto Logo' style='max-width: 200px; height: auto; display: inline-block; filter: brightness(0) invert(1); opacity: 0.95;' />";
} else {
    $logoImg = "<p style='color: white;'>Logo not found at: " . htmlspecialchars($logoPath) . "</p>";
}

$siteName = 'Livonto';
$supportEmail = 'support@livonto.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Invoice Generated - <?= htmlspecialchars($siteName) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a202c;
            background-color: #f0f4f8;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: 20px 0;
        }
        
        .email-container {
            max-width: 640px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        /* Header Section */
        .email-header {
            background: linear-gradient(135deg, #8b6bd1 0%, #6f55b2 100%);
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .email-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.5; }
        }
        
        .logo-container {
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }
        
        .logo-container img {
            max-width: 200px;
            height: auto;
            display: inline-block;
            filter: brightness(0) invert(1);
            opacity: 0.95;
        }
        
        .header-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 40px;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .email-header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.8px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .email-header .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin-top: 12px;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }
        
        /* Body Section */
        .email-body {
            padding: 50px 40px;
            background-color: #ffffff;
        }
        
        .greeting {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 24px;
            font-weight: 500;
        }
        
        .greeting strong {
            color: #1a202c;
            font-weight: 700;
        }
        
        .intro-text {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 40px;
            line-height: 1.8;
        }
        
        /* Invoice Card */
        .invoice-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 16px;
            padding: 32px;
            margin: 40px 0;
            border: 2px solid #e9ecef;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            position: relative;
            overflow: hidden;
        }
        
        .invoice-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #8b6bd1 0%, #6f55b2 100%);
        }
        
        .invoice-title {
            font-size: 14px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .invoice-table tr {
            border-bottom: 1px solid #f1f3f5;
        }
        
        .invoice-table tr:last-child {
            border-bottom: none;
        }
        
        .invoice-table td {
            padding: 16px 0;
            vertical-align: middle;
        }
        
        .invoice-table td:first-child {
            padding-right: 20px;
        }
        
        .invoice-table td:last-child {
            text-align: right;
            padding-left: 20px;
        }
        
        .invoice-label {
            font-size: 15px;
            color: #718096;
            font-weight: 500;
        }
        
        .invoice-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
        }
        
        .invoice-value.amount {
            color: #8b6bd1;
            font-size: 24px;
            font-weight: 700;
        }
        
        .invoice-value.status {
            color: #22543d;
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            padding: 6px 16px;
            border-radius: 24px;
            font-size: 13px;
            display: inline-block;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(34, 84, 61, 0.15);
        }
        
        /* Info Note */
        .info-note {
            background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
            border-left: 4px solid #4299e1;
            padding: 20px 24px;
            border-radius: 8px;
            margin: 32px 0;
            font-size: 15px;
            color: #2c5282;
            box-shadow: 0 2px 8px rgba(66, 153, 225, 0.1);
        }
        
        .info-note strong {
            color: #2b6cb0;
            font-weight: 600;
        }
        
        /* CTA Button */
        .cta-section {
            text-align: center;
            margin: 40px 0;
        }
        
        .cta-button {
            display: inline-block;
            padding: 18px 48px;
            background: linear-gradient(135deg, #8b6bd1 0%, #6f55b2 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 8px 24px rgba(139, 107, 209, 0.35);
            transition: all 0.3s ease;
            letter-spacing: 0.3px;
        }
        
        .cta-button:hover {
            box-shadow: 0 12px 32px rgba(139, 107, 209, 0.45);
            transform: translateY(-3px);
        }
        
        /* Divider */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0 50%, transparent);
            margin: 32px 0;
        }
        
        /* Closing */
        .closing {
            margin-top: 32px;
            font-size: 16px;
            color: #4a5568;
            line-height: 1.8;
        }
        
        .closing strong {
            color: #2d3748;
            font-weight: 600;
        }
        
        /* Footer */
        .email-footer {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            padding: 40px;
            text-align: center;
            border-top: 2px solid #e9ecef;
        }
        
        .footer-text {
            font-size: 14px;
            color: #718096;
            line-height: 1.8;
            margin-bottom: 12px;
        }
        
        .footer-text:last-child {
            margin-bottom: 0;
        }
        
        .footer-link {
            color: #8b6bd1;
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer-link:hover {
            text-decoration: underline;
        }
        
        .footer-copyright {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #a0aec0;
            font-size: 13px;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .email-container {
                border-radius: 12px;
            }
            
            .email-body {
                padding: 30px 24px;
            }
            
            .email-header {
                padding: 40px 24px;
            }
            
            .email-header h1 {
                font-size: 26px;
            }
            
            .header-icon {
                width: 64px;
                height: 64px;
                font-size: 32px;
            }
            
            .invoice-card {
                padding: 24px;
            }
            
            .invoice-table td {
                display: block;
                padding: 12px 0;
                text-align: left !important;
            }
            
            .invoice-table td:first-child {
                padding-right: 0;
                padding-bottom: 4px;
            }
            
            .invoice-table td:last-child {
                padding-left: 0;
                padding-top: 0;
            }
            
            .email-footer {
                padding: 30px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <?php if ($logoImg): ?>
            <div class="logo-container">
                <?= $logoImg ?>
            </div>
            <?php endif; ?>
            <h1>Invoice Generated</h1>
            <div class="subtitle">Your payment has been confirmed</div>
        </div>
        
        <!-- Body -->
        <div class="email-body">
            <div class="greeting">
                Hello <strong>John Doe</strong>,
            </div>
            
            <div class="intro-text">
                Thank you for your booking! We're excited to confirm that your invoice has been generated successfully. 
                Your payment has been processed and your booking is confirmed.
            </div>
            
            <!-- Invoice Details Card -->
            <div class="invoice-card">
                <div class="invoice-title">Invoice Details</div>
                
                <table class="invoice-table">
                    <tr>
                        <td class="invoice-label">Invoice Number</td>
                        <td class="invoice-value">INV-20250111-0001</td>
                    </tr>
                    <tr>
                        <td class="invoice-label">Invoice Date</td>
                        <td class="invoice-value">January 11, 2025</td>
                    </tr>
                    <tr>
                        <td class="invoice-label">Property</td>
                        <td class="invoice-value">Premium PG Near IIT</td>
                    </tr>
                    <tr>
                        <td class="invoice-label">Total Amount</td>
                        <td class="invoice-value amount">₹10,000.00</td>
                    </tr>
                    <tr>
                        <td class="invoice-label">Payment Status</td>
                        <td class="invoice-value status">✓ Paid</td>
                    </tr>
                </table>
            </div>
            
            <!-- Download Invoice Note -->
            <div class="info-note">
                <strong>📄 Download Invoice:</strong> You can view and download your invoice PDF from your profile page.
            </div>
            
            <div class="divider"></div>
            
            <div class="closing">
                If you have any questions or need assistance, our support team is here to help.<br><br>
                Best regards,<br>
                <strong>The <?= htmlspecialchars($siteName) ?> Team</strong>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <div class="footer-text">
                This is an automated email. Please do not reply to this message.
            </div>
            <div class="footer-text">
                Need help? Contact us at <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" class="footer-link"><?= htmlspecialchars($supportEmail) ?></a>
            </div>
            <div class="footer-copyright">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>

