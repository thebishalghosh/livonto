<?php
/**
 * Owner Listing Edit Page
 * Allows owners to update room availability only
 */

session_start();
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/functions.php';

requireOwnerLogin();

$listingId = getCurrentOwnerListingId();
$db = db();

// Get listing and room configurations
try {
    $listing = $db->fetchOne(
        "SELECT l.*, ll.city, ll.pin_code
         FROM listings l
         LEFT JOIN listing_locations ll ON l.id = ll.listing_id
         WHERE l.id = ?",
        [$listingId]
    );
    
    if (!$listing) {
        $_SESSION['flash_message'] = 'Listing not found';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . app_url('owner/dashboard'));
        exit;
    }
    
    // Get room configurations - bed-based availability (available_rooms = available beds)
    $rooms = $db->fetchAll(
        "SELECT rc.*, 
                (SELECT COUNT(*) FROM bookings b 
                 WHERE b.room_config_id = rc.id 
                 AND b.status IN ('pending', 'confirmed')) as booked_beds
         FROM room_configurations rc
         WHERE rc.listing_id = ?
         ORDER BY rc.room_type, rc.rent_per_month",
        [$listingId]
    );
    
    // Calculate bed information for each room using unified calculation
    foreach ($rooms as &$room) {
        $room['beds_per_room'] = getBedsPerRoom($room['room_type']);
        $room['total_beds'] = calculateTotalBeds($room['total_rooms'], $room['room_type']);
        $room['booked_beds'] = (int)($room['booked_beds'] ?? 0);
        
        // Use unified calculation: total_beds - booked_beds (ensures consistency)
        $room['available_beds'] = calculateAvailableBeds($room['total_rooms'], $room['room_type'], $room['booked_beds']);
    }
    unset($room);
    
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Error loading listing';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('owner/dashboard'));
    exit;
}

$flashMessage = getFlashMessage();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $updates = [];
    
    // Process room availability updates (bed-based)
    foreach ($rooms as $room) {
        $roomId = $room['id'];
        $currentAvailableBeds = (int)($room['available_beds'] ?? 0);
        
        // Get new value from POST array
        $newAvailableBeds = isset($_POST['available_beds'][$roomId]) 
            ? intval($_POST['available_beds'][$roomId]) 
            : $currentAvailableBeds;
        
        // Skip if no change
        if ($newAvailableBeds == $currentAvailableBeds) {
            continue;
        }
        
        // Calculate bed-based validation
        $bedsPerRoom = getBedsPerRoom($room['room_type']);
        $bookedBeds = (int)($room['booked_beds'] ?? 0);
        
        // Validate: available beds cannot be negative
        if ($newAvailableBeds < 0) {
            $errors[] = "Room type '{$room['room_type']}' cannot have negative available beds";
            continue;
        }
        
        // Calculate total beds needed (available + booked)
        $newTotalBeds = $newAvailableBeds + $bookedBeds;
        $newTotalRooms = ceil($newTotalBeds / $bedsPerRoom);
        
        // Validate: new total beds cannot be less than booked beds (shouldn't happen, but double-check)
        if ($newTotalBeds < $bookedBeds) {
            $errors[] = "Room type '{$room['room_type']}' cannot have fewer beds than booked ({$bookedBeds} beds booked)";
        } else {
            $updates[$roomId] = [
                'total_rooms' => $newTotalRooms,
                'available_beds' => $newAvailableBeds,
                'total_beds' => $newTotalBeds
            ];
        }
    }
    
    if (empty($errors)) {
        try {
            // Check if there are any updates to make
            if (empty($updates)) {
                $_SESSION['flash_message'] = 'No changes to update';
                $_SESSION['flash_type'] = 'info';
                header('Location: ' . app_url('owner/dashboard'));
                exit;
            }
            
            $db->beginTransaction();
            
            foreach ($updates as $roomId => $updateData) {
                $newTotalRooms = (int)$updateData['total_rooms'];
                $newAvailableBeds = (int)$updateData['available_beds'];
                
                // Update both total_rooms and available_rooms (beds) to keep them in sync
                $db->execute(
                    "UPDATE room_configurations 
                     SET total_rooms = ?, available_rooms = ? 
                     WHERE id = ? AND listing_id = ?",
                    [$newTotalRooms, $newAvailableBeds, $roomId, $listingId]
                );
            }
            
            $db->commit();
            
            $_SESSION['flash_message'] = 'Room availability updated successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('owner/dashboard'));
            exit;
            
        } catch (Exception $e) {
            try {
                $db->rollBack();
            } catch (Exception $rollbackEx) {
                // Ignore rollback errors
            }
            error_log("Owner availability update error: " . $e->getMessage());
            $errors[] = 'Error updating availability. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        $flashMessage = ['type' => 'danger', 'message' => implode('<br>', $errors)];
    }
}

$pageTitle = "Update Availability - " . htmlspecialchars($listing['title']);
require __DIR__ . '/../../app/includes/owner_header.php';
?>

<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1">Update Room Availability</h2>
            <p class="text-muted small mb-0"><?= htmlspecialchars($listing['title']) ?></p>
        </div>
        <div>
            <a href="<?= app_url('owner/dashboard') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
            <?= $flashMessage['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Room Configuration</h5>
        </div>
        <div class="card-body">
            <?php if (empty($rooms)): ?>
                <p class="text-muted">No rooms configured for this listing.</p>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Room Type</th>
                                    <th>Rent/Month</th>
                                    <th>Current Rooms</th>
                                    <th>Booked Beds</th>
                                    <th>Available Beds</th>
                                    <th>New Available Beds</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <?php
                                    $currentTotalRooms = (int)($room['total_rooms'] ?? 0);
                                    $bookedBeds = (int)($room['booked_beds'] ?? 0);
                                    $availableBeds = (int)($room['available_beds'] ?? 0);
                                    $totalBeds = (int)($room['total_beds'] ?? 0);
                                    $bedsPerRoom = (int)($room['beds_per_room'] ?? 1);
                                    
                                    // Calculate minimum rooms needed (based on booked beds)
                                    $minRooms = ceil($bookedBeds / $bedsPerRoom);
                                    ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars(ucfirst(str_replace(' sharing', '', $room['room_type'] ?? 'N/A'))) ?>
                                            <br><small class="text-muted"><?= $bedsPerRoom ?> bed<?= $bedsPerRoom > 1 ? 's' : '' ?> per room</small>
                                        </td>
                                        <td>₹<?= number_format($room['rent_per_month'] ?? 0) ?></td>
                                        <td>
                                            <?= $currentTotalRooms ?> room<?= $currentTotalRooms != 1 ? 's' : '' ?>
                                            <br><small class="text-muted"><?= $totalBeds ?> beds</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?= $bookedBeds ?> bed<?= $bookedBeds != 1 ? 's' : '' ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $availableBeds > 0 ? 'success' : 'danger' ?>">
                                                <?= $availableBeds ?> bed<?= $availableBeds != 1 ? 's' : '' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   name="available_beds[<?= $room['id'] ?>]" 
                                                   value="<?= $availableBeds ?>" 
                                                   min="0"
                                                   id="available_beds_<?= $room['id'] ?>"
                                                   data-room-id="<?= $room['id'] ?>"
                                                   data-beds-per-room="<?= $bedsPerRoom ?>"
                                                   data-booked-beds="<?= $bookedBeds ?>"
                                                   required>
                                            <small class="text-muted">Min: 0 beds</small>
                                            <div class="mt-1">
                                                <small class="text-muted" id="calculated_rooms_<?= $room['id'] ?>">
                                                    Will result in: <?= ceil(($availableBeds + $bookedBeds) / $bedsPerRoom) ?> room<?= ceil(($availableBeds + $bookedBeds) / $bedsPerRoom) > 1 ? 's' : '' ?>
                                                </small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Note:</strong> You can directly update available beds. Total rooms will be automatically calculated based on available beds + booked beds. 
                        Each booking reserves 1 bed. Available beds cannot be negative.
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Availability
                        </button>
                        <a href="<?= app_url('owner/dashboard') ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../app/includes/owner_footer.php'; ?>

<script>
// Auto-calculate total rooms when available beds change
document.addEventListener('DOMContentLoaded', function() {
    const availableBedInputs = document.querySelectorAll('input[name^="available_beds"]');
    
    availableBedInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const roomId = this.dataset.roomId;
            const bedsPerRoom = parseInt(this.dataset.bedsPerRoom);
            const bookedBeds = parseInt(this.dataset.bookedBeds);
            const availableBeds = parseInt(this.value) || 0;
            
            // Calculate total beds and rooms
            const totalBeds = availableBeds + bookedBeds;
            const totalRooms = Math.ceil(totalBeds / bedsPerRoom);
            
            // Update the calculated rooms display
            const calculatedRoomsEl = document.getElementById('calculated_rooms_' + roomId);
            if (calculatedRoomsEl) {
                calculatedRoomsEl.textContent = 'Will result in: ' + totalRooms + ' room' + (totalRooms > 1 ? 's' : '');
            }
        });
        
        // Trigger on page load to show initial calculation
        input.dispatchEvent(new Event('input'));
    });
});
</script>

