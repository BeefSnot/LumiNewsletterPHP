<?php
require_once 'includes/db.php';

// Get tracking parameters
$newsletter_id = isset($_GET['nid']) ? (int)$_GET['nid'] : 0;
$email = isset($_GET['email']) ? $_GET['email'] : '';

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
            // For IPv6 you'd need a different approach
        }
        
        // Insert record
        $stmt = $db->prepare("INSERT INTO email_opens (newsletter_id, email, user_agent, ip_address) 
                              VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $newsletter_id, $email, $user_agent, $ip_address);
        $stmt->execute();
        $open_id = $stmt->insert_id;
        $stmt->close();
        
        // Only collect geo data if enabled
        if ($geoAnalyticsEnabled) {
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
    }
}

// Return a transparent 1x1 pixel regardless of tracking status
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

// The rest of your functions remain the same
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
    // Simple browser detection
    $device = 'unknown';
    $browser = 'unknown';
    $os = 'unknown';
    
    // Device detection
    if (preg_match('/(tablet|ipad|playbook)/i', $userAgent)) {
        $device = 'tablet';
    } else if (preg_match('/(mobile|iphone|ipod|android|blackberry|opera mini|iemobile)/i', $userAgent)) {
        $device = 'mobile';
    } else {
        $device = 'desktop';
    }
    
    // Browser detection
    if (preg_match('/MSIE|Trident/i', $userAgent)) {
        $browser = 'Internet Explorer';
    } else if (preg_match('/Firefox/i', $userAgent)) {
        $browser = 'Firefox';
    } else if (preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Chrome';
    } else if (preg_match('/Safari/i', $userAgent)) {
        $browser = 'Safari';
    } else if (preg_match('/Opera/i', $userAgent)) {
        $browser = 'Opera';
    } else if (preg_match('/Edge/i', $userAgent)) {
        $browser = 'Edge';
    }
    
    // OS detection
    if (preg_match('/windows|win32/i', $userAgent)) {
        $os = 'Windows';
    } else if (preg_match('/macintosh|mac os x/i', $userAgent)) {
        $os = 'macOS';
    } else if (preg_match('/linux/i', $userAgent)) {
        $os = 'Linux';
    } else if (preg_match('/android/i', $userAgent)) {
        $os = 'Android';
    } else if (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
        $os = 'iOS';
    }
    
    return [
        'device' => $device,
        'browser' => $browser,
        'os' => $os
    ];
}
?>