<?php
/**
 * Send Visit Booking Status Email Notification
 * Sends email to user when admin changes visit booking status
 * 
 * @param string $userEmail User email address
 * @param string $status New status (pending, confirmed, cancelled, completed)
 * @param int $bookingId Visit booking ID
 * @return bool True on success, false on failure
 */
function sendStatusMail($userEmail, $status, $bookingId) {
    try {
        // Load required files
        require_once __DIR__ . '/../email_helper.php';
        require_once __DIR__ . '/../functions.php';
        
        // Get database instance
        $db = db();
        
        // Fetch visit booking details with user, listing and location info
        $booking = $db->fetchOne(
            "SELECT vb.id, vb.preferred_date, vb.preferred_time, vb.message, vb.status,
                    u.name as user_name, u.email as user_email,
                    l.title as listing_title,
                    loc.city as listing_city, loc.complete_address as listing_address, loc.pin_code as listing_pincode,
                    loc.google_maps_link as listing_maps_link
             FROM visit_bookings vb
             LEFT JOIN users u ON vb.user_id = u.id
             LEFT JOIN listings l ON vb.listing_id = l.id
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             WHERE vb.id = ?",
            [$bookingId]
        );
        
        if (!$booking) {
            Logger::error("Visit booking not found for status email", ['booking_id' => $bookingId]);
            return false;
        }
        
        // Get site settings
        $siteName = getSetting('site_name', 'Livonto');
        $supportEmail = getSetting('support_email', 'support@livonto.com');
        
        // Status messages and colors
        $statusConfig = [
            'pending' => [
                'title' => 'Visit Request Received',
                'message' => 'Your visit request has been received and is pending confirmation.',
                'color' => '#fbbf24',
                'icon' => '⏳'
            ],
            'confirmed' => [
                'title' => 'Visit Confirmed',
                'message' => 'Great news! Your visit has been confirmed. We look forward to meeting you.',
                'color' => '#43e97b',
                'icon' => '✓'
            ],
            'completed' => [
                'title' => 'Visit Completed',
                'message' => 'Your visit has been marked as completed. Thank you for visiting!',
                'color' => '#4facfe',
                'icon' => '✓'
            ],
            'cancelled' => [
                'title' => 'Visit Cancelled',
                'message' => 'Your visit request has been cancelled. If you have any questions, please contact us.',
                'color' => '#ef4444',
                'icon' => '✗'
            ]
        ];
        
        $statusInfo = $statusConfig[$status] ?? $statusConfig['pending'];
        
        // Format date and time
        $visitDate = date('F d, Y', strtotime($booking['preferred_date']));
        $visitTime = date('h:i A', strtotime($booking['preferred_time']));
        
        // Build email subject
        $subject = "Visit Booking {$statusInfo['title']} - {$siteName}";
        
        // Build email body
        $message = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <meta http-equiv='X-UA-Compatible' content='IE=edge'>
            <title>{$statusInfo['title']}</title>
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
                .status-card {
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
                .status-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 4px;
                    height: 100%;
                    background: {$statusInfo['color']};
                }
                .status-icon {
                    font-size: 48px;
                    margin-bottom: 16px;
                }
                .status-title {
                    font-size: 24px;
                    font-weight: 700;
                    color: #2d3748;
                    margin-bottom: 12px;
                }
                .status-message {
                    font-size: 16px;
                    color: #4a5568;
                    margin-bottom: 24px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 8px 20px;
                    border-radius: 24px;
                    font-size: 14px;
                    font-weight: 600;
                    background: {$statusInfo['color']};
                    color: #ffffff;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .details-card {
                    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                    border-radius: 16px;
                    padding: 32px;
                    margin: 40px 0;
                    border: 2px solid #e9ecef;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
                    position: relative;
                    overflow: hidden;
                }
                .details-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 4px;
                    height: 100%;
                    background: linear-gradient(180deg, #8b6bd1 0%, #6f55b2 100%);
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
                    padding-right: 20px;
                    font-size: 15px;
                    color: #718096;
                    font-weight: 500;
                }
                .details-table td:last-child {
                    text-align: right;
                    padding-left: 20px;
                    font-size: 16px;
                    color: #2d3748;
                    font-weight: 600;
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
                    .status-card, .details-card { padding: 24px; }
                    .details-table td {
                        display: block;
                        padding: 12px 0;
                        text-align: left !important;
                    }
                    .details-table td:first-child {
                        padding-right: 0;
                        padding-bottom: 4px;
                    }
                    .details-table td:last-child {
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
                    <h1>{$statusInfo['title']}</h1>
                    <div class='subtitle'>Visit Booking Update</div>
                </div>
                
                <!-- Body -->
                <div class='email-body'>
                    <div class='greeting'>
                        Hello <strong>{$booking['user_name']}</strong>,
                    </div>
                    
                    <div class='intro-text'>
                        {$statusInfo['message']}
                    </div>
                    
                    <!-- Status Card -->
                    <div class='status-card'>
                        <div class='status-icon'>{$statusInfo['icon']}</div>
                        <div class='status-title'>{$statusInfo['title']}</div>
                        <div class='status-message'>{$statusInfo['message']}</div>
                        <div class='status-badge'>{$status}</div>
                    </div>
                    
                    <!-- Visit Details Card -->
                    <div class='details-card'>
                        <div class='details-title'>Visit Details</div>
                        
                        <table class='details-table'>
                            <tr>
                                <td>Booking ID</td>
                                <td>#{$booking['id']}</td>
                            </tr>
                            <tr>
                                <td>Property</td>
                                <td>{$booking['listing_title']}</td>
                            </tr>
                            <tr>
                                <td>Visit Date</td>
                                <td>{$visitDate}</td>
                            </tr>
                            <tr>
                                <td>Visit Time</td>
                                <td>{$visitTime}</td>
                            </tr>
                            " . (!empty($booking['listing_city']) ? "
                            <tr>
                                <td>Location</td>
                                <td>{$booking['listing_city']}" . (!empty($booking['listing_pincode']) ? " - {$booking['listing_pincode']}" : "") . "</td>
                            </tr>
                            " : "") . "
                            " . (!empty($booking['listing_address']) ? "
                            <tr>
                                <td>Full Address</td>
                                <td>" . nl2br(htmlspecialchars($booking['listing_address'])) . "</td>
                            </tr>
                            " : "") . "
                            " . (!empty($booking['listing_maps_link']) ? "
                            <tr>
                                <td>Google Maps</td>
                                <td>
                                    <a href='" . htmlspecialchars($booking['listing_maps_link']) . "' 
                                       target='_blank' 
                                       rel='noopener' 
                                       style='color:#8b6bd1; text-decoration:underline;'>
                                        View on Google Maps
                                    </a>
                                </td>
                            </tr>
                            " : "") . "
                        </table>
                    </div>
                    
                    " . (!empty($booking['message']) ? "
                    <div class='info-note'>
                        <strong>📝 Your Message:</strong><br>
                        " . nl2br(htmlspecialchars($booking['message'])) . "
                    </div>
                    " : "") . "
                    
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
        $result = sendEmail($userEmail, $subject, $message);
        
        if ($result) {
            Logger::info("Visit booking status email sent successfully", [
                'booking_id' => $bookingId,
                'user_email' => $userEmail,
                'status' => $status
            ]);
        } else {
            Logger::error("Failed to send visit booking status email", [
                'booking_id' => $bookingId,
                'user_email' => $userEmail,
                'status' => $status
            ]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        Logger::error("Error sending visit booking status email", [
            'error' => $e->getMessage(),
            'booking_id' => $bookingId,
            'user_email' => $userEmail,
            'status' => $status
        ]);
        return false;
    }
}

