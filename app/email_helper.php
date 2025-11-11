<?php
/**
 * Email Helper Functions
 * Handles email sending functionality
 */

/**
 * Send email using PHP mail() function
 * For production, consider using PHPMailer or similar library
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
        
        // Prepare headers
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "Reply-To: {$fromEmail}";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        
        // If attachments are provided, use multipart message
        if (!empty($attachments)) {
            // For attachments, we'd need to use PHPMailer or similar
            // For now, log that attachments were requested
            error_log("Email attachments requested but not implemented. Use PHPMailer for attachment support.");
        }
        
        // Send email
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if ($result) {
            error_log("Email sent successfully to: {$to}, Subject: {$subject}");
        } else {
            error_log("Failed to send email to: {$to}, Subject: {$subject}");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error sending email: " . $e->getMessage());
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
        
        // Send email
        $result = sendEmail($recipientEmail, $subject, $message);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error sending invoice email: " . $e->getMessage());
        return false;
    }
}

