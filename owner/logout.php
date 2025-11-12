<?php
/**
 * Owner Logout
 */

session_start();

// Clear owner session variables
unset($_SESSION['owner_logged_in']);
unset($_SESSION['owner_listing_id']);
unset($_SESSION['owner_name']);
unset($_SESSION['owner_email']);
unset($_SESSION['owner_login_time']);

// Destroy session if no other user is logged in
if (empty($_SESSION['user_id'])) {
    session_destroy();
}

header('Location: ' . app_url('owner/login'));
exit;

