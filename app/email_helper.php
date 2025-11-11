<?php
/**
 * Email Helper Functions
 * Handles email sending functionality using PHPMailer with SMTP support
 */

// Load logger
require_once __DIR__ . '/logger.php';

// Load PHPMailer if available
$phpmailerLoaded = false;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $phpmailerLoaded = true;
    }
}

/**
 * Send email using PHPMailer (SMTP) or PHP mail() as fallback
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param string $fromEmail Sender email address
 * @param string $fromName Sender name
 * @param array $attachments Array of file paths to attach
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $message, $fromEmail = null, $fromName = null, $attachments = []) {
    try {
        // Get email settings from environment or use defaults
        $fromEmail = $fromEmail ?: getenv('SMTP_FROM_EMAIL') ?: 'noreply@livonto.com';
        $fromName = $fromName ?: getenv('SMTP_FROM_NAME') ?: 'Livonto';
        
        // Validate email
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Logger::error("Invalid recipient email", ['email' => $to]);
            return false;
        }
        
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            Logger::error("Invalid sender email", ['email' => $fromEmail]);
            return false;
        }
        
        // Check if SMTP is configured
        $smtpHost = getenv('SMTP_HOST');
        $smtpUser = getenv('SMTP_USERNAME');
        $smtpPass = getenv('SMTP_PASSWORD');
        $useSMTP = !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass);
        
        // Use PHPMailer if available and SMTP is configured
        if ($phpmailerLoaded && $useSMTP) {
            return sendEmailViaPHPMailer($to, $subject, $message, $fromEmail, $fromName, $attachments);
        }
        
        // Fallback to PHP mail() function
        return sendEmailViaMail($to, $subject, $message, $fromEmail, $fromName);
        
    } catch (Exception $e) {
        Logger::error("Error sending email", ['error' => $e->getMessage(), 'to' => $to, 'subject' => $subject]);
        return false;
    }
}

/**
 * Send email using PHPMailer with SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param string $fromEmail Sender email address
 * @param string $fromName Sender name
 * @param array $attachments Array of file paths to attach
 * @return bool True on success, false on failure
 */
function sendEmailViaPHPMailer($to, $subject, $message, $fromEmail, $fromName, $attachments = []) {
    try {
        if (!$phpmailerLoaded) {
            Logger::error("PHPMailer not loaded, cannot send email via SMTP");
            return false;
        }
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME');
        $mail->Password = getenv('SMTP_PASSWORD');
        $encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = intval(getenv('SMTP_PORT') ?: 587);
        $mail->CharSet = 'UTF-8';
        
        // Enable verbose debug output (only in debug mode)
        if (getenv('APP_DEBUG') === 'true') {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                Logger::debug("PHPMailer: $str");
            };
        }
        
        // Sender
        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);
        
        // Recipient
        $mail->addAddress($to);
        
        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            Logger::info("Email sent successfully via SMTP", ['to' => $to, 'subject' => $subject]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        Logger::error("PHPMailer Error", ['error' => $mail->ErrorInfo, 'to' => $to, 'subject' => $subject]);
        return false;
    }
}

/**
 * Send email using PHP mail() function (fallback)
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param string $fromEmail Sender email address
 * @param string $fromName Sender name
 * @return bool True on success, false on failure
 */
function sendEmailViaMail($to, $subject, $message, $fromEmail, $fromName) {
    // Prepare headers
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "From: {$fromName} <{$fromEmail}>";
    $headers[] = "Reply-To: {$fromEmail}";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    
    // Send email
    $result = mail($to, $subject, $message, implode("\r\n", $headers));
    
    if ($result) {
        Logger::info("Email sent successfully via mail()", ['to' => $to, 'subject' => $subject]);
    } else {
        Logger::warning("Failed to send email via mail()", ['to' => $to, 'subject' => $subject]);
    }
    
    return $result;
}

/**
 * Send invoice email using PHPMailer with embedded logo
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param string $logoPath Path to logo file
 * @param int $invoiceId Invoice ID for logging
 * @return bool True on success, false on failure
 */
function sendInvoiceEmailViaPHPMailer($to, $subject, $message, $logoPath, $invoiceId) {
    try {
        if (!$phpmailerLoaded) {
            Logger::error("PHPMailer not loaded, cannot send invoice email via SMTP");
            return false;
        }
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME');
        $mail->Password = getenv('SMTP_PASSWORD');
        $encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = intval(getenv('SMTP_PORT') ?: 587);
        $mail->CharSet = 'UTF-8';
        
        // Enable verbose debug output (only in debug mode)
        if (getenv('APP_DEBUG') === 'true') {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                Logger::debug("PHPMailer: $str");
            };
        }
        
        // Get email settings
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'noreply@livonto.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'Livonto';
        
        // Sender
        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);
        
        // Recipient
        $mail->addAddress($to);
        
        // Embed logo as CID attachment
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'logo');
            Logger::debug("Logo embedded in invoice email", ['logo_path' => $logoPath, 'cid' => 'logo']);
        } else {
            Logger::warning("Logo file not found for invoice email", ['logo_path' => $logoPath]);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            Logger::info("Invoice email sent successfully via SMTP", ['to' => $to, 'subject' => $subject, 'invoice_id' => $invoiceId]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        Logger::error("PHPMailer Error sending invoice email", ['error' => isset($mail) ? $mail->ErrorInfo : $e->getMessage(), 'to' => $to, 'subject' => $subject, 'invoice_id' => $invoiceId]);
        return false;
    }
}

/**
 * Send invoice email notification
 * 
 * @param int $invoiceId Invoice ID
 * @param string $recipientEmail Recipient email address
 * @param string $recipientName Recipient name
 * @return bool True on success, false on failure
 */
function sendInvoiceEmail($invoiceId, $recipientEmail, $recipientName) {
    try {
        require_once __DIR__ . '/invoice_generator.php';
        
        // Get invoice data
        $invoice = getInvoiceData($invoiceId);
        if (!$invoice) {
            Logger::error("Invoice not found", ['invoice_id' => $invoiceId]);
            return false;
        }
        
        // Get site settings
        $siteName = getSetting('site_name', 'Livonto');
        $supportEmail = getSetting('support_email', 'support@livonto.com');
        $baseUrl = rtrim(app_url(''), '/');
        
        // Check if logo file exists
        $logoPath = __DIR__ . '/../public/assets/images/logo-removebg.png';
        $logoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logoPath);
        $hasLogo = file_exists($logoPath);
        
        // Build email subject
        $subject = "Invoice #{$invoice['invoice_number']} - {$siteName}";
        
        // Build email body
        $invoiceUrl = app_url('invoice?id=' . $invoiceId);
        $invoiceDate = date('F d, Y', strtotime($invoice['invoice_date']));
        $totalAmount = '₹' . number_format($invoice['total_amount'], 2);
        
        // Use CID for logo if PHPMailer is available, otherwise try hosted URL or base64
        $logoImg = '';
        if ($hasLogo) {
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
            <title>Invoice #{$invoice['invoice_number']}</title>
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
                        Hello <strong>{$recipientName}</strong>,
                    </div>
                    
                    <div class='intro-text'>
                        Thank you for your booking! We're excited to confirm that your invoice has been generated successfully. 
                        Your payment has been processed and your booking is confirmed.
                    </div>
                    
                    <!-- Invoice Details Card -->
                    <div class='invoice-card'>
                        <div class='invoice-title'>Invoice Details</div>
                        
                        <table class='invoice-table'>
                            <tr>
                                <td class='invoice-label'>Invoice Number</td>
                                <td class='invoice-value'>{$invoice['invoice_number']}</td>
                            </tr>
                            <tr>
                                <td class='invoice-label'>Invoice Date</td>
                                <td class='invoice-value'>{$invoiceDate}</td>
                            </tr>
                            <tr>
                                <td class='invoice-label'>Property</td>
                                <td class='invoice-value'>{$invoice['listing_title']}</td>
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
                        If you have any questions or need assistance, our support team is here to help.<br><br>
                        Best regards,<br>
                        <strong>The {$siteName} Team</strong>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class='email-footer'>
                    <div class='footer-text'>
                        This is an automated email. Please do not reply to this message.
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
        if ($phpmailerLoaded && $hasLogo) {
            // Use PHPMailer directly to embed logo as CID
            return sendInvoiceEmailViaPHPMailer($recipientEmail, $subject, $message, $logoPath, $invoiceId);
        } else {
            // Use regular sendEmail function
            $result = sendEmail($recipientEmail, $subject, $message, null, null, []);
            return $result;
        }
        
    } catch (Exception $e) {
        Logger::error("Error sending invoice email", ['error' => $e->getMessage(), 'invoice_id' => $invoiceId]);
        return false;
    }
}
