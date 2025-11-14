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
        return $mail->send();
        
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
    return mail($to, $subject, $message, implode("\r\n", $headers));
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
        
        // Build email subject
        $subject = "Invoice #{$invoice['invoice_number']} - {$siteName}";
        
        // Build email body
        $invoiceDate = date('F d, Y', strtotime($invoice['invoice_date']));
        $totalAmount = '₹' . number_format($invoice['total_amount'], 2);
        
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
                .logo-text {
                    font-size: 32px;
                    font-weight: 700;
                    color: #ffffff;
                    margin-bottom: 24px;
                    position: relative;
                    z-index: 1;
                    letter-spacing: 2px;
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
                    <div class='logo-text'>{$siteName}</div>
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
        
        // Send email
        $result = sendEmail($recipientEmail, $subject, $message, null, null, []);
        return $result;
        
    } catch (Exception $e) {
        Logger::error("Error sending invoice email", ['error' => $e->getMessage(), 'invoice_id' => $invoiceId]);
        return false;
    }
}

/**
 * Send welcome email to new user after registration
 * 
 * @param string $userEmail User email address
 * @param string $userName User name
 * @param string $referralCode User's referral code
 * @param bool $wasReferred Whether user was referred by someone
 * @return bool True on success, false on failure
 */
function sendWelcomeEmail($userEmail, $userName, $referralCode = '', $wasReferred = false) {
    try {
        // Check if getSetting function exists
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/functions.php';
        }
        
        $siteName = getSetting('site_name', 'Livonto');
        $supportEmail = getSetting('support_email', 'support@livonto.com');
        $baseUrl = app_url('');
        
        // Build email subject
        $subject = "Welcome to {$siteName}! Your Account is Ready";
        
        // Build referral section if user has a referral code
        $referralSection = '';
        if (!empty($referralCode)) {
            $referralLink = $baseUrl . 'register?ref=' . urlencode($referralCode);
            $referralSection = '
                <div class="info-note" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; padding: 20px 24px; border-radius: 8px; margin: 32px 0; font-size: 15px; color: #92400e; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);">
                    <strong style="color: #78350f; font-weight: 600;">🎁 Your Referral Code:</strong><br>
                    <div style="margin-top: 12px; font-size: 24px; font-weight: 700; color: #78350f; letter-spacing: 2px;">' . htmlspecialchars($referralCode) . '</div>
                    <div style="margin-top: 12px;">
                        <strong>Share and Earn:</strong> When your friend makes a booking, they get ₹500 off instantly, and you get ₹1,500 cash within 7 working days!
                    </div>
                    <div style="margin-top: 12px;">
                        <strong>Your Referral Link:</strong><br>
                        <a href="' . htmlspecialchars($referralLink) . '" style="color: #78350f; word-break: break-all; text-decoration: underline;">' . htmlspecialchars($referralLink) . '</a>
                    </div>
                </div>';
        }
        
        // Build referred by section if user was referred
        $referredBySection = '';
        if ($wasReferred) {
            $referredBySection = '
                <div class="info-note" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-left: 4px solid #3b82f6; padding: 20px 24px; border-radius: 8px; margin: 32px 0; font-size: 15px; color: #1e40af; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);">
                    <strong style="color: #1e3a8a; font-weight: 600;">🎉 Special Offer:</strong><br>
                    You registered using a referral code! You\'ll get ₹500 off on your first booking. Start exploring our listings now!
                </div>';
        }
        
        // Build email body
        $message = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <meta http-equiv='X-UA-Compatible' content='IE=edge'>
            <title>Welcome to {$siteName}</title>
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
                .logo-text {
                    font-size: 32px;
                    font-weight: 700;
                    color: #ffffff;
                    margin-bottom: 24px;
                    position: relative;
                    z-index: 1;
                    letter-spacing: 2px;
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
                .welcome-card {
                    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                    border-radius: 16px;
                    padding: 32px;
                    margin: 40px 0;
                    border: 2px solid #e9ecef;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
                    position: relative;
                    overflow: hidden;
                    text-align: center;
                }
                .welcome-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 4px;
                    height: 100%;
                    background: linear-gradient(180deg, #8b6bd1 0%, #6f55b2 100%);
                }
                .welcome-icon {
                    font-size: 64px;
                    margin-bottom: 16px;
                }
                .welcome-title {
                    font-size: 24px;
                    font-weight: 700;
                    color: #2d3748;
                    margin-bottom: 12px;
                }
                .welcome-message {
                    font-size: 16px;
                    color: #4a5568;
                    margin-bottom: 24px;
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
                .steps-card {
                    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                    border-radius: 16px;
                    padding: 32px;
                    margin: 40px 0;
                    border: 2px solid #e9ecef;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
                }
                .steps-title {
                    font-size: 14px;
                    color: #718096;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    font-weight: 600;
                    margin-bottom: 24px;
                    padding-bottom: 12px;
                    border-bottom: 1px solid #e9ecef;
                }
                .step-item {
                    display: flex;
                    align-items: start;
                    margin-bottom: 24px;
                    padding-bottom: 24px;
                    border-bottom: 1px solid #f1f3f5;
                }
                .step-item:last-child {
                    margin-bottom: 0;
                    padding-bottom: 0;
                    border-bottom: none;
                }
                .step-number {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #8b6bd1 0%, #6f55b2 100%);
                    color: #ffffff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    font-size: 18px;
                    margin-right: 20px;
                    flex-shrink: 0;
                }
                .step-content {
                    flex: 1;
                }
                .step-title {
                    font-size: 16px;
                    font-weight: 600;
                    color: #2d3748;
                    margin-bottom: 8px;
                }
                .step-description {
                    font-size: 15px;
                    color: #4a5568;
                    line-height: 1.6;
                }
                .cta-button {
                    display: inline-block;
                    padding: 14px 32px;
                    background: linear-gradient(135deg, #8b6bd1 0%, #6f55b2 100%);
                    color: #ffffff !important;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 16px;
                    box-shadow: 0 4px 12px rgba(139, 107, 209, 0.3);
                    margin: 20px 0;
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
                    .welcome-card, .steps-card { padding: 24px; }
                    .step-item {
                        flex-direction: column;
                        text-align: center;
                    }
                    .step-number {
                        margin-right: 0;
                        margin-bottom: 12px;
                    }
                    .email-footer { padding: 30px 24px; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <!-- Header -->
                <div class='email-header'>
                    <div class='logo-text'>{$siteName}</div>
                    <h1>Welcome Aboard!</h1>
                    <div class='subtitle'>Your account has been created successfully</div>
                </div>
                
                <!-- Body -->
                <div class='email-body'>
                    <div class='greeting'>
                        Hello <strong>{$userName}</strong>,
                    </div>
                    
                    <div class='intro-text'>
                        Thank you for joining {$siteName}! We're thrilled to have you as part of our community. 
                        Your account has been successfully created and you're all set to start exploring amazing PG accommodations.
                    </div>
                    
                    <!-- Welcome Card -->
                    <div class='welcome-card'>
                        <div class='welcome-icon'>🎉</div>
                        <div class='welcome-title'>Account Confirmed!</div>
                        <div class='welcome-message'>You can now browse listings, book visits, and make reservations.</div>
                        <a href='" . htmlspecialchars($baseUrl) . "' class='cta-button'>Start Exploring</a>
                    </div>
                    
                    {$referredBySection}
                    
                    {$referralSection}
                    
                    <!-- Next Steps Card -->
                    <div class='steps-card'>
                        <div class='steps-title'>Get Started in 3 Easy Steps</div>
                        
                        <div class='step-item'>
                            <div class='step-number'>1</div>
                            <div class='step-content'>
                                <div class='step-title'>Browse Listings</div>
                                <div class='step-description'>Explore our curated selection of PG accommodations. Filter by location, price, and amenities to find your perfect match.</div>
                            </div>
                        </div>
                        
                        <div class='step-item'>
                            <div class='step-number'>2</div>
                            <div class='step-content'>
                                <div class='step-title'>Schedule a Visit</div>
                                <div class='step-description'>Book a visit to see the property in person. Our hosts are ready to show you around and answer any questions.</div>
                            </div>
                        </div>
                        
                        <div class='step-item'>
                            <div class='step-number'>3</div>
                            <div class='step-content'>
                                <div class='step-title'>Complete Your Booking</div>
                                <div class='step-description'>Once you've found your ideal PG, complete your booking with secure payment. Your new home awaits!</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class='info-note'>
                        <strong>💡 Pro Tip:</strong> Complete your profile with your details and preferences to get personalized recommendations and faster bookings.
                    </div>
                    
                    <div class='divider'></div>
                    
                    <div class='closing'>
                        We're here to help you find the perfect accommodation. If you have any questions or need assistance, don't hesitate to reach out to our support team.<br><br>
                        Happy house hunting!<br>
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
                    <div class='footer-text'>
                        Visit our website: <a href='" . htmlspecialchars($baseUrl) . "' class='footer-link'>" . htmlspecialchars($baseUrl) . "</a>
                    </div>
                    <div class='footer-copyright'>
                        &copy; " . date('Y') . " {$siteName}. All rights reserved.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email
        $result = sendEmail($userEmail, $subject, $message);
        
        if ($result) {
            Logger::info("Welcome email sent successfully", [
                'user_email' => $userEmail,
                'user_name' => $userName
            ]);
        } else {
            Logger::error("Failed to send welcome email", [
                'user_email' => $userEmail,
                'user_name' => $userName
            ]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        Logger::error("Error sending welcome email", [
            'error' => $e->getMessage(),
            'user_email' => $userEmail,
            'user_name' => $userName
        ]);
        return false;
    }
}

/**
 * Get admin email from site settings
 * @return string Admin email address
 */
function getAdminEmail() {
    // Check if getSetting function exists, if not load functions.php
    if (!function_exists('getSetting')) {
        require_once __DIR__ . '/functions.php';
    }
    try {
        return getSetting('admin_email', 'admin@livonto.com');
    } catch (Exception $e) {
        Logger::error("Error getting admin email from settings", ['error' => $e->getMessage()]);
        return 'admin@livonto.com'; // Fallback default
    }
}

/**
 * Send admin notification email
 * Generic function to send notifications to admin
 * 
 * @param string $subject Email subject
 * @param string $title Notification title
 * @param string $message Notification message/body
 * @param array $details Additional details to display (key-value pairs)
 * @param string $actionUrl Optional URL for action button
 * @param string $actionText Optional text for action button
 * @return bool True on success, false on failure
 */
function sendAdminNotification($subject, $title, $message, $details = [], $actionUrl = null, $actionText = 'View Details') {
    try {
        // Check if getSetting function exists
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/functions.php';
        }
        
        $adminEmail = getAdminEmail();
        
        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            Logger::error("Invalid admin email for notification", ['email' => $adminEmail]);
            return false;
        }
        
        $siteName = getSetting('site_name', 'Livonto');
        $supportEmail = getSetting('support_email', 'support@livonto.com');
        
        // Build details HTML
        $detailsHtml = '';
        if (!empty($details)) {
            $detailsHtml = '<div class="details-card">
                <div class="details-title">Details</div>
                <table class="details-table">';
            foreach ($details as $key => $value) {
                $detailsHtml .= '<tr>
                    <td>' . htmlspecialchars($key) . '</td>
                    <td>' . htmlspecialchars($value) . '</td>
                </tr>';
            }
            $detailsHtml .= '</table>
            </div>';
        }
        
        // Build action button HTML
        $actionButtonHtml = '';
        if (!empty($actionUrl)) {
            $actionButtonHtml = '<div style="text-align: center; margin: 32px 0;">
                <a href="' . htmlspecialchars($actionUrl) . '" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #8b6bd1 0%, #6f55b2 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(139, 107, 209, 0.3);">' . htmlspecialchars($actionText) . '</a>
            </div>';
        }
        
        $emailBody = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    line-height: 1.6;
                    color: #1a202c;
                    background-color: #f0f4f8;
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
                }
                .logo-text {
                    font-size: 32px;
                    font-weight: 700;
                    color: #ffffff;
                    margin-bottom: 24px;
                    letter-spacing: 2px;
                }
                .email-header h1 {
                    color: #ffffff;
                    font-size: 28px;
                    font-weight: 700;
                    margin: 0;
                }
                .email-body {
                    padding: 50px 40px;
                }
                .greeting {
                    font-size: 18px;
                    color: #2d3748;
                    margin-bottom: 24px;
                    font-weight: 500;
                }
                .message-text {
                    font-size: 16px;
                    color: #4a5568;
                    margin-bottom: 32px;
                    line-height: 1.8;
                }
                .details-card {
                    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                    border-radius: 16px;
                    padding: 32px;
                    margin: 32px 0;
                    border: 2px solid #e9ecef;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
                }
                .details-title {
                    font-size: 14px;
                    color: #718096;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    font-weight: 600;
                    margin-bottom: 24px;
                    padding-bottom: 12px;
                    border-bottom: 1px solid #e9ecef;
                }
                .details-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .details-table tr {
                    border-bottom: 1px solid #f1f3f5;
                }
                .details-table tr:last-child {
                    border-bottom: none;
                }
                .details-table td {
                    padding: 16px 0;
                    vertical-align: middle;
                }
                .details-table td:first-child {
                    font-size: 15px;
                    color: #718096;
                    font-weight: 500;
                }
                .details-table td:last-child {
                    text-align: right;
                    font-size: 16px;
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
                @media only screen and (max-width: 600px) {
                    .email-body { padding: 30px 24px; }
                    .email-header { padding: 40px 24px; }
                    .details-card { padding: 24px; }
                    .details-table td {
                        display: block;
                        text-align: left !important;
                        padding: 12px 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <div class='logo-text'>{$siteName}</div>
                    <h1>{$title}</h1>
                </div>
                <div class='email-body'>
                    <div class='greeting'>Hello Admin,</div>
                    <div class='message-text'>{$message}</div>
                    {$detailsHtml}
                    {$actionButtonHtml}
                </div>
                <div class='email-footer'>
                    <div class='footer-text'>This is an automated notification from {$siteName}.</div>
                    <div class='footer-text'>Need help? Contact: <a href='mailto:{$supportEmail}' style='color: #8b6bd1;'>{$supportEmail}</a></div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $result = sendEmail($adminEmail, $subject, $emailBody);
        
        if ($result) {
            Logger::info("Admin notification email sent", ['subject' => $subject, 'admin_email' => $adminEmail]);
        } else {
            Logger::error("Failed to send admin notification email", ['subject' => $subject, 'admin_email' => $adminEmail]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        Logger::error("Error sending admin notification", ['error' => $e->getMessage(), 'subject' => $subject]);
        return false;
    }
}
