<?php
/**
 * Main API Router
 * Handles API requests and routes them to the appropriate endpoints
 */
require_once 'includes/db.php';
require_once 'includes/api_auth.php';

// Enable CORS for API requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-API-Key");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Extract request path and parameters
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api.php/', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);
$resource = $segments[0] ?? '';

// Authenticate API request
$auth = validateApiKey();
if (!$auth['status']) {
    apiResponse(['error' => $auth['message']], $auth['code']);
}

// Route the request to the appropriate resource
switch ($resource) {
    case 'subscribers':
        require_once 'api/subscribers.php';
        break;
        
    case 'newsletters':
        require_once 'api/newsletters.php';
        break;
        
    case 'groups':
        require_once 'api/groups.php';
        break;
        
    case 'analytics':
        require_once 'api/analytics.php';
        break;
    
    case 'status':
        // Simple status endpoint to check if API is working
        apiResponse([
            'status' => 'ok',
            'version' => require 'version.php',
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $auth['user_id'],
            'role' => $auth['role']
        ]);
        break;
        
    default:
        apiResponse([
            'error' => 'Unknown API resource: ' . $resource,
            'available_resources' => [
                'subscribers', 'newsletters', 'groups', 'analytics', 'status'
            ]
        ], 404);
}