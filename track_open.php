<?php
require_once 'includes/db.php';

// Get tracking parameters
$newsletter_id = isset($_GET['nid']) ? (int)$_GET['nid'] : 0;
$email = isset($_GET['email']) ? $_GET['email'] : '';

// Only record if we have valid data
if ($newsletter_id > 0 && !empty($email)) {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Insert record
    $stmt = $db->prepare("INSERT INTO email_opens (newsletter_id, email, user_agent, ip_address) 
                          VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $newsletter_id, $email, $user_agent, $ip_address);
    $stmt->execute();
    $open_id = $stmt->insert_id;
    $stmt->close();
    
    // Attempt to get geo location data
    $geoData = getGeoData($ip_address);
    if ($geoData && isset($geoData['latitude'], $geoData['longitude'])) {
        $stmt = $db->prepare("INSERT INTO email_geo_data (open_id, country, region, city, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdd", $open_id, $geoData['country'], $geoData['region'], $geoData['city'], $geoData['latitude'], $geoData['longitude']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Record device info
    $deviceInfo = parseUserAgent($user_agent);
    if ($deviceInfo) {
        $stmt = $db->prepare("INSERT INTO email_devices (open_id, device_type, browser, os) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $open_id, $deviceInfo['device'], $deviceInfo['browser'], $deviceInfo['os']);
        $stmt->execute();
        $stmt->close();
    }
}

// Return a transparent 1x1 pixel
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

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

function parseUserAgent($userAgent) {
    $device = 'unknown';
    $browser = 'unknown';
    $os = 'unknown';
    
    // Simple browser detection
    if (strpos($userAgent, 'Chrome') !== false) $browser = 'Chrome';
    elseif (strpos($userAgent, 'Firefox') !== false) $browser = 'Firefox';
    elseif (strpos($userAgent, 'Safari') !== false) $browser = 'Safari';
    elseif (strpos($userAgent, 'Edge') !== false) $browser = 'Edge';
    elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) $browser = 'Internet Explorer';
    
    // Simple OS detection
    if (strpos($userAgent, 'Windows') !== false) $os = 'Windows';
    elseif (strpos($userAgent, 'Android') !== false) $os = 'Android';
    elseif (strpos($userAgent, 'iPhone') !== false) $os = 'iOS';
    elseif (strpos($userAgent, 'iPad') !== false) $os = 'iOS';
    elseif (strpos($userAgent, 'Mac') !== false) $os = 'macOS';
    elseif (strpos($userAgent, 'Linux') !== false) $os = 'Linux';
    
    // Simple device detection
    if (strpos($userAgent, 'Mobile') !== false) $device = 'Mobile';
    elseif (strpos($userAgent, 'Tablet') !== false) $device = 'Tablet';
    elseif (strpos($userAgent, 'iPad') !== false) $device = 'Tablet';
    else $device = 'Desktop';
    
    return [
        'device' => $device,
        'browser' => $browser,
        'os' => $os
    ];
}
?>