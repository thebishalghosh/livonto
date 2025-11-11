<?php
/**
 * Test Email Script
 * Sends a test invoice email to verify logo and email template
 */

// Start session and load config
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/email_helper.php';

// Test email recipient
$testEmail = 'thebishalghosh@gmail.com';
$testName = 'Bishal Ghosh';

// Check if email should be sent (to prevent accidental sends)
$sendEmail = isset($_GET['send']) && $_GET['send'] === 'yes';

if (!$sendEmail) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Test Email - Confirmation</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 { color: #8b6bd1; }
            .info {
                background: #e3f2fd;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #8b6bd1;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 20px;
            }
            .btn:hover {
                background: #6f55b2;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Test Email Sender</h1>
            <div class="info">
                <p><strong>Recipient:</strong> <?= htmlspecialchars($testEmail) ?></p>
                <p><strong>Name:</strong> <?= htmlspecialchars($testName) ?></p>
                <p>This will send a test invoice email with the logo embedded.</p>
            </div>
            <p>Click the button below to send the test email:</p>
            <a href="?send=yes" class="btn">Send Test Email</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Send test email
try {
    // Get site settings
    $siteName = getSetting('site_name', 'Livonto');
    $supportEmail = getSetting('support_email', 'support@livonto.com');
    $baseUrl = rtrim(app_url(''), '/');
    
    // Check if logo file exists
    $logoPath = __DIR__ . '/public/assets/images/logo-removebg.png';
    $logoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logoPath);
    $hasLogo = file_exists($logoPath);
    
    // Build email subject
    $subject = "Test Invoice Email - {$siteName}";
    
    // Build email body with test data
    $invoiceDate = date('F d, Y');
    $totalAmount = '₹10,000.00';
    
    // Use CID for logo if PHPMailer is available
    $logoImg = '';
    if ($hasLogo) {
        global $phpmailerLoaded;
        if ($phpmailerLoaded) {
            // Use CID reference (will be embedded by PHPMailer)
            $logoImg = "<img src='cid:logo' alt='{$siteName} Logo' style='max-width: 200px; height: auto; display: inline-block; filter: brightness(0) invert(1); opacity: 0.95;' />";
        } else {
            // Fallback: try hosted URL
            $logoUrl = rtrim($baseUrl, '/') . '/public/assets/images/logo-removebg.png';
            if (substr($logoUrl, 0, 1) !== '/') {
                $logoUrl = '/' . ltrim($logoUrl, '/');
            }
            $logoImg = "<img src='{$logoUrl}' alt='{$siteName} Logo' style='max-width: 200px; height: auto; display: inline-block; filter: brightness(0) invert(1); opacity: 0.95;' />";
        }
    }
    
    $message = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <meta http-equiv='X-UA-Compatible' content='IE=edge'>
        <title>Test Invoice Email</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
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
            .email-header {
                background: linear-gradient(135deg, #8b6bd1 0%, #6f55b2 100%);
                padding: 50px 40px;
                text-align: center;
                position: relative;
                overflow: hidden;
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
            .divider {
                height: 1px;
                background: linear-gradient(90deg, transparent, #e2e8f0 50%, transparent);
                margin: 32px 0;
            }
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
            @media only screen and (max-width: 600px) {
                body { padding: 10px; }
                .email-container { border-radius: 12px; }
                .email-body { padding: 30px 24px; }
                .email-header { padding: 40px 24px; }
                .email-header h1 { font-size: 26px; }
                .invoice-card { padding: 24px; }
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
                .email-footer { padding: 30px 24px; }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <!-- Header -->
            <div class='email-header'>
                " . ($logoImg ? "<div class='logo-container'>{$logoImg}</div>" : "") . "
                <h1>Invoice Generated</h1>
                <div class='subtitle'>Your payment has been confirmed</div>
            </div>
            
            <!-- Body -->
            <div class='email-body'>
                <div class='greeting'>
                    Hello <strong>{$testName}</strong>,
                </div>
                
                <div class='intro-text'>
                    This is a test email to verify the invoice email template and logo display. 
                    The logo should be visible in the header above.
                </div>
                
                <!-- Invoice Details Card -->
                <div class='invoice-card'>
                    <div class='invoice-title'>Invoice Details (Test)</div>
                    
                    <table class='invoice-table'>
                        <tr>
                            <td class='invoice-label'>Invoice Number</td>
                            <td class='invoice-value'>TEST-INV-0001</td>
                        </tr>
                        <tr>
                            <td class='invoice-label'>Invoice Date</td>
                            <td class='invoice-value'>{$invoiceDate}</td>
                        </tr>
                        <tr>
                            <td class='invoice-label'>Property</td>
                            <td class='invoice-value'>Test Property - Premium PG</td>
                        </tr>
                        <tr>
                            <td class='invoice-label'>Total Amount</td>
                            <td class='invoice-value amount'>{$totalAmount}</td>
                        </tr>
                        <tr>
                            <td class='invoice-label'>Payment Status</td>
                            <td class='invoice-value status'>✓ Paid</td>
                        </tr>
                    </table>
                </div>
                
                <div class='info-note'>
                    <strong>📄 Download Invoice:</strong> You can view and download your invoice PDF from your profile page.
                </div>
                
                <div class='divider'></div>
                
                <div class='closing'>
                    This is a test email. If you can see the logo in the header, the email template is working correctly.<br><br>
                    Best regards,<br>
                    <strong>The {$siteName} Team</strong>
                </div>
            </div>
            
            <!-- Footer -->
            <div class='email-footer'>
                <div class='footer-text'>
                    This is a test email. Please do not reply to this message.
                </div>
                <div class='footer-text'>
                    Need help? Contact us at <a href='mailto:{$supportEmail}' class='footer-link'>{$supportEmail}</a>
                </div>
                <div class='footer-copyright'>
                    &copy; " . date('Y') . " {$siteName}. All rights reserved.
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email with embedded logo if using PHPMailer
    global $phpmailerLoaded;
    if ($phpmailerLoaded && $hasLogo) {
        // Use PHPMailer directly to embed logo as CID
        $result = sendInvoiceEmailViaPHPMailer($testEmail, $subject, $message, $logoPath, 0);
    } else {
        // Use regular sendEmail function
        $result = sendEmail($testEmail, $subject, $message, null, null, []);
    }
    
    if ($result) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Test Email Sent</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    max-width: 600px;
                    margin: 50px auto;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .container {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .success {
                    background: #d4edda;
                    color: #155724;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .info {
                    background: #e3f2fd;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>✅ Test Email Sent Successfully!</h1>
                <div class='success'>
                    <p><strong>Email sent to:</strong> " . htmlspecialchars($testEmail) . "</p>
                    <p>Please check your inbox (and spam folder) to verify:</p>
                    <ul>
                        <li>Logo is visible in the email header</li>
                        <li>Email template displays correctly</li>
                        <li>All styling is preserved</li>
                    </ul>
                </div>
                <div class='info'>
                    <p><strong>Note:</strong> If using PHPMailer with SMTP, the logo is embedded as a CID attachment. 
                    If using PHP mail(), the logo may use a hosted URL which might not work in all email clients.</p>
                </div>
            </div>
        </body>
        </html>";
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Test Email Failed</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    max-width: 600px;
                    margin: 50px auto;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .container {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .error {
                    background: #f8d7da;
                    color: #721c24;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>❌ Test Email Failed</h1>
                <div class='error'>
                    <p>Failed to send test email. Please check:</p>
                    <ul>
                        <li>SMTP configuration in .env file</li>
                        <li>Error logs in storage/logs/app.log</li>
                        <li>PHP error logs</li>
                    </ul>
                </div>
            </div>
        </body>
        </html>";
    }
    
} catch (Exception $e) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 600px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .error {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>❌ Error</h1>
            <div class='error'>
                <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            </div>
        </div>
    </body>
    </html>";
}

