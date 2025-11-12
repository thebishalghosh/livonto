<?php
/**
 * Migration Script: Recalculate Availability for All Room Configurations
 * 
 * This script recalculates the available_rooms (available beds) for all
 * room configurations based on actual bookings in the database.
 * 
 * Run this script once to fix existing data after implementing bed-based availability.
 * 
 * Usage: php sql/migrations/recalculate_availability.php
 */

require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/functions.php';

echo "Starting availability recalculation...\n\n";

try {
    $db = db();
    
    // Get all room configurations
    $roomConfigs = $db->fetchAll(
        "SELECT id, listing_id, room_type, total_rooms 
         FROM room_configurations 
         ORDER BY listing_id, id"
    );
    
    $totalConfigs = count($roomConfigs);
    $updated = 0;
    $errors = 0;
    
    echo "Found {$totalConfigs} room configurations to process.\n\n";
    
    foreach ($roomConfigs as $config) {
        try {
            $roomConfigId = $config['id'];
            $listingId = $config['listing_id'];
            $roomType = $config['room_type'];
            $totalRooms = $config['total_rooms'];
            
            // Count actual booked beds (pending + confirmed bookings)
            $bookedBeds = (int)$db->fetchValue(
                "SELECT COUNT(*) FROM bookings 
                 WHERE room_config_id = ? AND status IN ('pending', 'confirmed')",
                [$roomConfigId]
            );
            
            // Calculate total beds and available beds
            $totalBeds = calculateTotalBeds($totalRooms, $roomType);
            $availableBeds = max(0, $totalBeds - $bookedBeds);
            
            // Update available_rooms (which represents available beds)
            $db->execute(
                "UPDATE room_configurations SET available_rooms = ? WHERE id = ?",
                [$availableBeds, $roomConfigId]
            );
            
            $updated++;
            echo "✓ Updated room_config_id {$roomConfigId} (Listing {$listingId}, {$roomType}): {$totalBeds} total beds, {$bookedBeds} booked, {$availableBeds} available\n";
            
        } catch (Exception $e) {
            $errors++;
            echo "✗ Error processing room_config_id {$config['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "Recalculation Complete!\n";
    echo "========================================\n";
    echo "Total configurations: {$totalConfigs}\n";
    echo "Successfully updated: {$updated}\n";
    echo "Errors: {$errors}\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

