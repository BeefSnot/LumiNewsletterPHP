<?php
/**
 * Settings API Endpoint
 * Manage and retrieve system settings
 */

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $segments[1] ?? '';

// Only allow admin users to access settings
if ($auth['role'] !== 'admin' && $method !== 'GET') {
    apiResponse(['error' => 'Unauthorized. Admin privileges required.'], 403);
}

switch ($method) {
    case 'GET':
        if ($action === 'social') {
            // Get social sharing settings
            $result = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'social_%'");
            $settings = [];
            
            while ($row = $result->fetch_assoc()) {
                $key = str_replace('social_', '', $row['setting_key']);
                $settings[$key] = $row['setting_value'];
            }
            
            apiResponse(['social_settings' => $settings]);
        } elseif ($action === 'smtp') {
            // Only allow admin users to see SMTP settings
            if ($auth['role'] !== 'admin') {
                apiResponse(['error' => 'Unauthorized. Admin privileges required.'], 403);
            }
            
            $result = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
            $settings = [];
            
            while ($row = $result->fetch_assoc()) {
                $key = str_replace('smtp_', '', $row['setting_key']);
                // Don't include sensitive data like passwords
                if ($key !== 'password') {
                    $settings[$key] = $row['setting_value'];
                }
            }
            
            apiResponse(['smtp_settings' => $settings]);
        } elseif ($action === 'privacy') {
            // Get privacy settings
            $result = $db->query("SELECT setting_key, setting_value FROM privacy_settings");
            $settings = [];
            
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            apiResponse(['privacy_settings' => $settings]);
        } else {
            // Get general settings
            $result = $db->query("SELECT name, value FROM settings WHERE name NOT LIKE 'smtp_%'");
            $settings = [];
            
            while ($row = $result->fetch_assoc()) {
                $settings[$row['name']] = $row['value'];
            }
            
            apiResponse(['settings' => $settings]);
        }
        break;
        
    case 'PUT':
        if ($action === 'social') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                apiResponse(['error' => 'Invalid JSON data'], 400);
            }
            
            $allowedSettings = [
                'facebook_enabled', 'twitter_enabled', 'linkedin_enabled', 
                'email_enabled', 'share_style', 'share_size'
            ];
            
            $updatedCount = 0;
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedSettings)) {
                    $settingKey = 'social_' . $key;
                    
                    // Check if setting exists
                    $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = ?");
                    $checkStmt->bind_param('s', $settingKey);
                    $checkStmt->execute();
                    $exists = (int)$checkStmt->get_result()->fetch_assoc()['count'] > 0;
                    
                    if ($exists) {
                        // Update existing setting
                        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                        $stmt->bind_param('ss', $value, $settingKey);
                    } else {
                        // Insert new setting
                        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                        $stmt->bind_param('ss', $settingKey, $value);
                    }
                    
                    if ($stmt->execute()) {
                        $updatedCount++;
                    }
                }
            }
            
            apiResponse([
                'message' => 'Social settings updated successfully',
                'updated_count' => $updatedCount
            ]);
        } elseif ($action === 'privacy') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                apiResponse(['error' => 'Invalid JSON data'], 400);
            }
            
            $updatedCount = 0;
            
            foreach ($data as $key => $value) {
                // Check if setting exists
                $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM privacy_settings WHERE setting_key = ?");
                $checkStmt->bind_param('s', $key);
                $checkStmt->execute();
                $exists = (int)$checkStmt->get_result()->fetch_assoc()['count'] > 0;
                
                if ($exists) {
                    // Update existing setting
                    $stmt = $db->prepare("UPDATE privacy_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->bind_param('ss', $value, $key);
                } else {
                    // Insert new setting
                    $stmt = $db->prepare("INSERT INTO privacy_settings (setting_key, setting_value) VALUES (?, ?)");
                    $stmt->bind_param('ss', $key, $value);
                }
                
                if ($stmt->execute()) {
                    $updatedCount++;
                }
            }
            
            apiResponse([
                'message' => 'Privacy settings updated successfully',
                'updated_count' => $updatedCount
            ]);
        } else {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                apiResponse(['error' => 'Invalid JSON data'], 400);
            }
            
            $updatedCount = 0;
            
            foreach ($data as $key => $value) {
                // Don't allow updating SMTP settings via this endpoint
                if (strpos($key, 'smtp_') === 0) {
                    continue;
                }
                
                // Check if setting exists
                $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE name = ?");
                $checkStmt->bind_param('s', $key);
                $checkStmt->execute();
                $exists = (int)$checkStmt->get_result()->fetch_assoc()['count'] > 0;
                
                if ($exists) {
                    // Update existing setting
                    $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = ?");
                    $stmt->bind_param('ss', $value, $key);
                } else {
                    // Insert new setting
                    $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (?, ?)");
                    $stmt->bind_param('ss', $key, $value);
                }
                
                if ($stmt->execute()) {
                    $updatedCount++;
                }
            }
            
            apiResponse([
                'message' => 'Settings updated successfully',
                'updated_count' => $updatedCount
            ]);
        }
        break;
        
    default:
        apiResponse(['error' => 'Method not allowed'], 405);
        break;
}