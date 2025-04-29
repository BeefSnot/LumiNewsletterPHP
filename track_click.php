<?php
require_once 'includes/db.php';
require_once 'includes/geo_track.php';

// Get tracking parameters
$newsletter_id = isset($_GET['nid']) ? (int)$_GET['nid'] : 0;
$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';
$url = isset($_GET['url']) ? urldecode($_GET['url']) : '';

// Get privacy settings
$trackingEnabled = true;
$geoAnalyticsEnabled = true;
$anonymizeIp = false;

$privacyResult = $db->query("SELECT setting_key, setting_value FROM privacy_settings WHERE setting_key IN ('enable_tracking', 'enable_geo_analytics', 'anonymize_ip')");
if ($privacyResult) {
    while ($row = $privacyResult->fetch_assoc()) {
        if ($row['setting_key'] === 'enable_tracking') {
            $trackingEnabled = ($row['setting_value'] === '1');
        } elseif ($row['setting_key'] === 'enable_geo_analytics') {
            $geoAnalyticsEnabled = ($row['setting_value'] === '1');
        } elseif ($row['setting_key'] === 'anonymize_ip') {
            $anonymizeIp = ($row['setting_value'] === '1');
        }
    }
}

// Check if we should track this user
if ($trackingEnabled && $newsletter_id > 0 && !empty($email)) {
    // Check user consent if explicit consent is required
    $requireExplicitConsent = false;
    $result = $db->query("SELECT setting_value FROM privacy_settings WHERE setting_key = 'require_explicit_consent'");
    if ($result && $result->num_rows > 0) {
        $requireExplicitConsent = ($result->fetch_assoc()['setting_value'] === '1');
    }
    
    $hasConsent = true;
    if ($requireExplicitConsent) {
        $stmt = $db->prepare("SELECT tracking_consent FROM subscriber_consent WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $hasConsent = (bool)$result->fetch_assoc()['tracking_consent'];
        } else {
            $hasConsent = false; // No explicit consent record found
        }
        $stmt->close();
    }

    if ($hasConsent) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Anonymize IP if needed
        if ($anonymizeIp && !empty($ip_address)) {
            // Simple anonymization: replace last octet with 0 for IPv4
            $ip_address = preg_replace('/(\d+\.\d+\.\d+\.)\d+/', '$10', $ip_address);
        }
        
        // Insert record
        $stmt = $db->prepare("INSERT INTO link_clicks (newsletter_id, email, original_url, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $newsletter_id, $email, $url, $user_agent, $ip_address);
        $stmt->execute();
        $click_id = $stmt->insert_id;
        $stmt->close();
        
        // Only collect geo data if enabled
        if ($geoAnalyticsEnabled) {
            // Record geographic data
            recordGeoData('click', $click_id);
        }
    }
}

// Redirect to the original URL regardless of tracking status
if (!empty($url)) {
    header("Location: $url");
    exit;
} else {
    // Fallback if no URL provided
    header("Location: index.php");
    exit;
}

function getGeoData($ip) {
    // Use free IP geolocation API
    $response = @file_get_contents("http://ip-api.com/json/{$ip}");
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            return [
                'country' => $data['country'] ?? '',
                'region' => $data['regionName'] ?? '',
                'city' => $data['city'] ?? '',
                'latitude' => $data['lat'] ?? 0,
                'longitude' => $data['lon'] ?? 0
            ];
        }
    }
    return false;
}
?>