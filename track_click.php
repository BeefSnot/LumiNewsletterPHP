<?php
require_once 'includes/db.php';
require_once 'includes/geo_track.php';

// Get tracking parameters
$newsletter_id = isset($_GET['nid']) ? (int)$_GET['nid'] : 0;
$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';
$url = isset($_GET['url']) ? urldecode($_GET['url']) : '';

$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// Only record if we have valid data
if ($newsletter_id > 0 && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($url)) {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Insert record
    $stmt = $db->prepare("INSERT INTO link_clicks (newsletter_id, email, original_url, user_agent, ip_address) 
                         VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $newsletter_id, $email, $url, $user_agent, $ip_address);
    $stmt->execute();
    $click_id = $stmt->insert_id;
    $stmt->close();
    
    // Record geographic data
    recordGeoData('click', $click_id);
}

// Redirect to the original URL
if (!empty($url)) {
    header("Location: $url");
    exit;
} else {
    // Fallback if no URL provided
    header("Location: index.php");
    exit;
}
?>