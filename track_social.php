<?php
require_once 'includes/db.php';
require_once 'includes/social_sharing.php';

// Allow this to be called via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    
    if ($type === 'share') {
        $platform = $_POST['platform'] ?? '';
        $newsletter_id = isset($_POST['newsletter_id']) ? (int)$_POST['newsletter_id'] : 0;
        
        if ($platform && $newsletter_id > 0) {
            recordSocialShare($newsletter_id, $platform);
            
            // Send success response
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    } else if ($type === 'click') {
        $share_id = isset($_POST['share_id']) ? (int)$_POST['share_id'] : 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $referrer = $_POST['referrer'] ?? null;
        
        if ($share_id > 0) {
            recordSocialClick($share_id, $ip_address, $referrer);
            
            // Send success response
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// Send error response for invalid requests
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);