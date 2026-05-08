<?php
/**
 * Logout with Activity Logging
 * Filename: logout.php
 * 
 * REPLACE your existing logout.php with this version
 */

session_start();
include 'db.php';

// Log logout activity BEFORE destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    logActivity($conn, 'logout', null, null, 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>