<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access this page
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// In a real implementation, you would:
// 1. Get the OAuth code from $_GET['code']
// 2. Exchange it for an access token
// 3. Store the token in the database
// 4. Redirect to the Etsy integration page

// For this demo, we'll just simulate success
$message = "Etsy authorization successful!";
$messageType = "success";

// Simulate storing a token
$db->query("UPDATE etsy_settings SET setting_value = 'SIMULATED_TOKEN_" . time() . "' WHERE setting_key = 'oauth_token'");

// Redirect back to the integration page
header("Location: etsy_integration.php?message=" . urlencode($message) . "&type=" . urlencode($messageType));
exit();
?>