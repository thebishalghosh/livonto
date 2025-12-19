<?php
/**
 * Send Booking Status Email Notification
 * Sends email to user when admin changes booking status
 * 
 * @param string $userEmail User email address
 * @param string $status New status (pending, confirmed, cancelled, completed)
 * @param int $bookingId Booking ID
 * @return bool True on success, false on failure
 */
function sendBookingStatusMail($userEmail, $status, $bookingId) {
    try {
        // Load required files
        require_once __DIR__ . '/../email_helper.php';
        require_once __DIR__ . '/../functions.php';
        
        // Get database instance
        $db = db();
        
        // Fetch booking details with user, listing, location, room config, and payment info
        $booking = $db->fetchOne(
            "SELECT b.id, b.booking_start_date, b.total_amount, b.status, b.special_requests,
                    b.duration_months,
                    u.name as user_name, u.email as user_email, u.phone as user_phone,
                    l.title as listing_title,
                    loc.city as listing_city, loc.complete_address as listing_address, loc.pin_code as listing_pincode,
                    loc.google_maps_link as listing_maps_link,
                    rc.room_type, rc.rent_per_month,
                    p.id as payment_id, p.status as payment_status, p.amount as payment_amount,
                    i.invoice_number
             FROM bookings b
             LEFT JOIN users u ON b.user_id = u.id
             LEFT JOIN listings l ON b.listing_id = l.id
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             LEFT JOIN room_configurations rc ON b.room_config_id = rc.id
             LEFT JOIN payments p ON b.id = p.booking_id
             LEFT JOIN invoices i ON b.id = i.booking_id
             WHERE b.id = ?",
            [$bookingId]
        );
        
        if (!$booking) {
            Logger::error("Booking not found for status email", ['booking_id' => $bookingId]);
            return false;
        }
        
        // Get site settings
        $siteName = getSetting('site_name', 'Livonto');
        $supportEmail = getSetting('support_email', 'support@livonto.com');
        
        // Status messages and colors
        $statusConfig = [
            'pending' => [
                'title' => 'Booking Under Review',
                'message' => 'Your booking request has been received and is currently under review. We will process it shortly.',
                'color' => '#fbbf24',
                'icon' => '⏳'
            ],
            'confirmed' => [
                'title' => 'Booking Confirmed',
                'message' => 'Great news! Your booking has been confirmed. Welcome to your new home!',
                'color' => '#43e97b',
                'icon' => '✓'
            ],
            'completed' => [
                'title' => 'Booking Completed',
                'message' => 'Your booking period has been completed. Thank you for being with us!',
                'color' => '#4facfe',
                'icon' => '✓'
            ],
            'cancelled' => [
                'title' => 'Booking Cancelled',
                'message' => 'Your booking has been cancelled. If you have any questions or concerns, please contact our support team.',
                'color' => '#ef4444',
                'icon' => '✗'
            ]
        ];
        
        $statusInfo = $statusConfig[$status] ?? $statusConfig['pending'];
        
        // Calculate booking end date (1 month from start date by default, or use duration_months)
        $durationMonths = $booking['duration_months'] ?? 1;
        $startDate = new DateTime($booking['booking_start_date']);
        $endDate = clone $startDate;
        $endDate->modify("+{$durationMonths} month");
        
        // Format dates
        $startDateFormatted = date('F d, Y', strtotime($booking['booking_start_date']));
        $endDateFormatted = $endDate->format('F d, Y');
        
        // Format amounts
        $totalAmount = formatCurrency($booking['total_amount'] ?? 0);
        $rentAmount = formatCurrency($booking['rent_per_month'] ?? 0);
        $paymentAmount = formatCurrency($booking['payment_amount'] ?? 0);
        
        // Payment status badge
        $paymentStatusBadge = '';
        if (!empty($booking['payment_status'])) {
            $paymentStatusColors = [
                'success' => 'background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);',
                'initiated' => 'background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);',
                'failed' => 'background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);'
            ];
            $paymentStatusColor = $paymentStatusColors[$booking['payment_status']] ?? $paymentStatusColors['initiated'];
            $paymentStatusText = ucfirst($booking['payment_status']);
            if ($booking['payment_status'] === 'success') {
                $paymentStatusText = 'Paid';
            }
            $paymentStatusBadge = "<span style='display: inline-block; padding: 6px 16px; border-radius: 24px; font-size: 13px; font-weight: 600; color: #ffffff; {$paymentStatusColor} box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);'>{$paymentStatusText}</span>";
        }
        
        // Room type display
        $roomTypeDisplay = ucfirst(str_replace(' sharing', '', $booking['room_type'] ?? 'N/A'));
        if ($roomTypeDisplay !== 'N/A') {
            $roomTypeDisplay .= ' Sharing';
        }
        
        // Build email subject
        $subject = "Booking {$statusInfo['title']} - {$siteName}";
        
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
                .amount-highlight {
                    color: #8b6bd1;
                    font-size: 20px;
                    font-weight: 700;
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
                    <div class='subtitle'>Booking Update</div>
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
                    
                    <!-- Booking Details Card -->
                    <div class='details-card'>
                        <div class='details-title'>Booking Details</div>
                        
                        <table class='details-table'>
                            <tr>
                                <td>Booking ID</td>
                                <td>#{$booking['id']}</td>
                            </tr>
                            <tr>
                                <td>Property</td>
                                <td>{$booking['listing_title']}</td>
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
                            <tr>
                                <td>Room Type</td>
                                <td>{$roomTypeDisplay}</td>
                            </tr>
                            <tr>
                                <td>Monthly Rent</td>
                                <td>{$rentAmount}</td>
                            </tr>
                            <tr>
                                <td>Booking Start Date</td>
                                <td>{$startDateFormatted}</td>
                            </tr>
                            <tr>
                                <td>Booking End Date</td>
                                <td>{$endDateFormatted}</td>
                            </tr>
                            <tr>
                                <td>Duration</td>
                                <td>{$durationMonths} " . ($durationMonths == 1 ? 'Month' : 'Months') . "</td>
                            </tr>
                            <tr>
                                <td>Total Amount</td>
                                <td class='amount-highlight'>{$totalAmount}</td>
                            </tr>
                            " . (!empty($booking['payment_status']) ? "
                            <tr>
                                <td>Payment Status</td>
                                <td>{$paymentStatusBadge}</td>
                            </tr>
                            " : "") . "
                            " . (!empty($booking['invoice_number']) ? "
                            <tr>
                                <td>Invoice Number</td>
                                <td>{$booking['invoice_number']}</td>
                            </tr>
                            " : "") . "
                        </table>
                    </div>
                    
                    " . (!empty($booking['special_requests']) ? "
                    <div class='info-note'>
                        <strong>📝 Special Requests:</strong><br>
                        " . nl2br(htmlspecialchars($booking['special_requests'])) . "
                    </div>
                    " : "") . "
                    
                    " . ($status === 'confirmed' ? "
                    <div class='info-note'>
                        <strong>🎉 Next Steps:</strong><br>
                        Your booking is confirmed! You can view your booking details and download your invoice from your profile page. If you have any questions, feel free to contact our support team.
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
            Logger::info("Booking status email sent successfully", [
                'booking_id' => $bookingId,
                'user_email' => $userEmail,
                'status' => $status
            ]);
        } else {
            Logger::error("Failed to send booking status email", [
                'booking_id' => $bookingId,
                'user_email' => $userEmail,
                'status' => $status
            ]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        Logger::error("Error sending booking status email", [
            'error' => $e->getMessage(),
            'booking_id' => $bookingId,
            'user_email' => $userEmail,
            'status' => $status
        ]);
        return false;
    }
}

