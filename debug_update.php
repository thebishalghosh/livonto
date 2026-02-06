<?php
// debug_update.php
// Robust debugger for the specific update issue

require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Update Logic Debugger</h1>";

try {
    $db = db();
    echo "<p style='color:green'>✓ Database connection successful.</p>";

    // 1. Get a target listing
    $listingId = 12; // Target the specific ID you mentioned
    $listing = $db->fetchOne("SELECT * FROM listings WHERE id = ?", [$listingId]);

    if (!$listing) {
        // Fallback to any listing
        $listing = $db->fetchOne("SELECT * FROM listings LIMIT 1");
        if (!$listing) {
            die("<p style='color:red'>No listings found to test.</p>");
        }
        $listingId = $listing['id'];
    }

    echo "<h3>Target Listing: ID #$listingId</h3>";
    echo "<pre>Current Title: " . htmlspecialchars($listing['title']) . "</pre>";
    echo "<pre>Current Owner: " . htmlspecialchars($listing['owner_name']) . "</pre>";
    echo "<pre>Current Email: " . htmlspecialchars($listing['owner_email'] ?? 'NULL') . "</pre>";

    // 2. Define New Values
    $newTitle = "Debug Title " . time();
    $newOwner = "Debug Owner " . time();
    $newEmail = "debug" . time() . "@example.com";

    echo "<h3>Attempting Update To:</h3>";
    echo "<ul>";
    echo "<li>Title: $newTitle</li>";
    echo "<li>Owner: $newOwner</li>";
    echo "<li>Email: $newEmail</li>";
    echo "</ul>";

    // 3. Execute Update (Mimicking listing_edit.php logic)
    $sql = "UPDATE listings SET
            title = ?,
            description = ?,
            owner_name = ?,
            owner_email = ?,
            available_for = ?,
            gender_allowed = ?,
            preferred_tenants = ?,
            security_deposit_amount = ?,
            notice_period = ?,
            status = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";

    // Use existing values for fields we aren't testing
    $params = [
        $newTitle,
        $listing['description'], // Keep existing
        $newOwner,
        $newEmail,
        $listing['available_for'], // Keep existing
        $listing['gender_allowed'], // Keep existing
        $listing['preferred_tenants'], // Keep existing
        $listing['security_deposit_amount'], // Keep existing
        $listing['notice_period'], // Keep existing
        $listing['status'], // Keep existing
        $listingId
    ];

    echo "<h3>Executing Query...</h3>";

    try {
        // Use the global helper to get the raw PDO connection
        $pdo = db_connection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            $rowCount = $stmt->rowCount();
            echo "<p style='color:green'>✓ Execute returned TRUE.</p>";
            echo "<p style='font-weight:bold'>Rows Affected: $rowCount</p>";

            if ($rowCount > 0) {
                echo "<p style='color:green'>SUCCESS! The database reported changes.</p>";
            } else {
                echo "<p style='color:orange'>WARNING: 0 rows affected. This usually means the data was identical to what was already there.</p>";
            }

            // Verify the change
            $updatedListing = $db->fetchOne("SELECT title, owner_name, owner_email FROM listings WHERE id = ?", [$listingId]);
            echo "<h3>Verification Fetch:</h3>";
            echo "<pre>" . print_r($updatedListing, true) . "</pre>";

        } else {
            echo "<p style='color:red'>✗ Execute returned FALSE.</p>";
            echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
        }

    } catch (PDOException $e) {
        echo "<p style='color:red'>✗ PDO Exception: " . $e->getMessage() . "</p>";
        echo "<pre>Error Code: " . $e->getCode() . "</pre>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Critical Error: " . $e->getMessage() . "</p>";
}
?>
