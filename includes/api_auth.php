<?php
/**
 * API Authentication Middleware
 * Validates API keys and handles request logging
 */

function validateApiKey() {
    global $db;
    
    // Check if API key is provided
    $headers = getallheaders();
    $api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : null;
    
    if (!$api_key) {
        return [
            'status' => false,
            'code' => 401,
            'message' => 'API key missing. Please provide X-API-Key header.'
        ];
    }
    
    // Check if API key exists and is active
    $stmt = $db->prepare("SELECT ak.*, u.role FROM api_keys ak 
                         JOIN users u ON ak.user_id = u.id
                         WHERE ak.api_key = ? AND ak.status = 'active'");
    $stmt->bind_param('s', $api_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [
            'status' => false,
            'code' => 401,
            'message' => 'Invalid or inactive API key.'
        ];
    }
    
    $api_key_data = $result->fetch_assoc();
    
    // Log this API request
    $endpoint = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $status_code = 200; // Default success, can be updated later
    
    $stmt = $db->prepare("INSERT INTO api_requests (api_key_id, endpoint, method, ip_address, status_code) 
                        VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('isssi', $api_key_data['id'], $endpoint, $method, $ip, $status_code);
    $stmt->execute();
    
    // Update last used timestamp
    $db->query("UPDATE api_keys SET last_used = NOW() WHERE id = " . $api_key_data['id']);
    
    return [
        'status' => true,
        'api_key_id' => $api_key_data['id'],
        'user_id' => $api_key_data['user_id'],
        'role' => $api_key_data['role']
    ];
}

function apiResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}