<?php
/**
 * Geographic tracking for email analytics
 */
function recordGeoData($type, $record_id) {
    global $db;
    
    // Get IP address (with CloudFlare support)
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Skip for localhost
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return;
    }
    
    // Use a free IP geolocation service
    $geo_data = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city,lat,lon");
    
    if ($geo_data !== false) {
        $geo = json_decode($geo_data, true);
        
        if ($geo && $geo['status'] === 'success') {
            $null_id = NULL;
            $stmt = $db->prepare("INSERT INTO email_geo_data 
                                (open_id, click_id, country, region, city, latitude, longitude) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
                                
            if ($type === 'open') {
                $stmt->bind_param("iisssdd", $record_id, $null_id, 
                                $geo['country'], $geo['regionName'], 
                                $geo['city'], $geo['lat'], $geo['lon']);
            } else {
                $stmt->bind_param("iisssdd", $null_id, $record_id, 
                                $geo['country'], $geo['regionName'], 
                                $geo['city'], $geo['lat'], $geo['lon']);
            }
            
            $stmt->execute();
            $stmt->close();
        }
    }
}