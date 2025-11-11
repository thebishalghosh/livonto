<?php
/**
 * Complete Expired Bookings
 * Automatically marks bookings as 'completed' when booking period ends
 * 
 * This script should be run daily via cron job:
 * 0 0 * * * /usr/bin/php /path/to/app/complete_expired_bookings.php
 * 
 * Or can be called manually from admin panel
 */

require __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

try {
    $db = db();
    
    // Booking is completed when the booking period ends
    // Mark bookings as completed if:
    // 1. Status is 'confirmed'
    // 2. Booking end date has passed (booking_start_date + duration_months months <= today)
    // Note: duration_months defaults to 1 if not set (for backward compatibility)
    
    // Get bookings that need to be completed
    // Use COALESCE to default duration_months to 1 if NULL
    $bookingsToComplete = $db->fetchAll(
        "SELECT id, room_config_id 
         FROM bookings 
         WHERE status = 'confirmed' 
         AND DATE_ADD(booking_start_date, INTERVAL COALESCE(duration_months, 1) MONTH) <= CURDATE()"
    );
    
    if (!empty($bookingsToComplete)) {
        $db->beginTransaction();
        
        try {
            // Update booking status
            $db->execute(
                "UPDATE bookings 
                 SET status = 'completed', updated_at = NOW()
                 WHERE status = 'confirmed' 
                 AND DATE_ADD(booking_start_date, INTERVAL COALESCE(duration_months, 1) MONTH) <= CURDATE()"
            );
            
            // Increase available_rooms for each completed booking
            foreach ($bookingsToComplete as $booking) {
                if ($booking['room_config_id']) {
                    $db->execute(
                        "UPDATE room_configurations 
                         SET available_rooms = LEAST(total_rooms, available_rooms + 1) 
                         WHERE id = ?",
                        [$booking['room_config_id']]
                    );
                }
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    $affectedRows = count($bookingsToComplete);
    
    if ($affectedRows > 0) {
        echo "Successfully marked {$affectedRows} booking(s) as completed.\n";
    } else {
        echo "No bookings to mark as completed.\n";
    }
    
} catch (Exception $e) {
    error_log("Error completing expired bookings: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

