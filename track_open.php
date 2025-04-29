<?php
require_once 'includes/db.php';

// Get tracking parameters
$newsletter_id = isset($_GET['nid']) ? (int)$_GET['nid'] : 0;
$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// Only record if we have valid data
if ($newsletter_id > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Insert record
    $stmt = $db->prepare("INSERT INTO email_opens (newsletter_id, email, user_agent, ip_address) 
                          VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $newsletter_id, $email, $user_agent, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// Return a transparent 1x1 pixel
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?>