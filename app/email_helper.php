<?php
/**
 * Email Helper Functions
 * Handles email sending functionality using PHPMailer with SMTP support
 */

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
            error_log("Invalid recipient email: {$to}");
            return false;
        }
        
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid sender email: {$fromEmail}");
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
        error_log("Error sending email: " . $e->getMessage());
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
            error_log("PHPMailer not loaded, cannot send email via SMTP");
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
                error_log("PHPMailer: $str");
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
            error_log("Email sent successfully via SMTP to: {$to}, Subject: {$subject}");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
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
        error_log("Email sent successfully via mail() to: {$to}, Subject: {$subject}");
    } else {
        error_log("Failed to send email via mail() to: {$to}, Subject: {$subject}");
    }
    
    return $result;
}

/**
 * Send invoice email notification
 * 
 * @param int $invoiceId Invoice ID
 * @param string $recipientEmail Recipient email address
 * @param string $recipientName Recipient name
 * @param bool $attachPDF Whether to attach PDF invoice
 * @return bool True on success, false on failure
 */
function sendInvoiceEmail($invoiceId, $recipientEmail, $recipientName, $attachPDF = false) {
    try {
        require_once __DIR__ . '/invoice_generator.php';
        
        // Get invoice data
        $invoice = getInvoiceData($invoiceId);
        if (!$invoice) {
            error_log("Invoice not found: {$invoiceId}");
            return false;
        }
        
        // Get site settings
        $siteName = getSetting('site_name', 'Livonto');
        $supportEmail = getSetting('support_email', 'support@livonto.com');
        $baseUrl = rtrim(app_url(''), '/');
        
        // Build email subject
        $subject = "Invoice #{$invoice['invoice_number']} - {$siteName}";
        
        // Build email body
        $invoiceUrl = app_url('invoice?id=' . $invoiceId);
        $invoiceDate = date('F d, Y', strtotime($invoice['invoice_date']));
        $totalAmount = '₹' . number_format($invoice['total_amount'], 2);
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #8b6bd1 0%, #6f55b2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .invoice-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #8b6bd1; }
                .invoice-details p { margin: 8px 0; }
                .invoice-details strong { color: #8b6bd1; }
                .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #8b6bd1 0%, #6f55b2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: 600; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0;'>Invoice Generated</h1>
                </div>
                <div class='content'>
                    <p>Dear {$recipientName},</p>
                    
                    <p>Thank you for your booking! Your invoice has been generated successfully.</p>
                    
                    <div class='invoice-details'>
                        <p><strong>Invoice Number:</strong> {$invoice['invoice_number']}</p>
                        <p><strong>Invoice Date:</strong> {$invoiceDate}</p>
                        <p><strong>Total Amount:</strong> {$totalAmount}</p>
                        <p><strong>Property:</strong> {$invoice['listing_title']}</p>
                        <p><strong>Payment Status:</strong> Paid</p>
                    </div>
                    
                    <p>You can view and download your invoice by clicking the button below:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$invoiceUrl}' class='button'>View Invoice</a>
                    </div>
                    
                    " . ($attachPDF ? "<p>A PDF copy of your invoice is attached to this email.</p>" : "") . "
                    
                    <p>If you have any questions or concerns, please don't hesitate to contact us.</p>
                    
                    <p>Best regards,<br>
                    <strong>{$siteName} Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>For support, contact: <a href='mailto:{$supportEmail}' style='color: #8b6bd1;'>{$supportEmail}</a></p>
                    <p>&copy; " . date('Y') . " {$siteName}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Prepare attachments
        $attachments = [];
        if ($attachPDF && $phpmailerLoaded) {
            require_once __DIR__ . '/invoice_pdf_generator.php';
            $pdfPath = getInvoicePDFPath($invoiceId);
            if ($pdfPath && file_exists(__DIR__ . '/../' . $pdfPath)) {
                $attachments[] = __DIR__ . '/../' . $pdfPath;
            }
        }
        
        // Send email
        $result = sendEmail($recipientEmail, $subject, $message, null, null, $attachments);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error sending invoice email: " . $e->getMessage());
        return false;
    }
}
