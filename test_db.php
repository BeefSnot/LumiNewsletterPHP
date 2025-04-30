<?php
// Set headers for JSON response
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'test_db_connection') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get database credentials
$db_host = $_POST['db_host'] ?? '';
$db_user = $_POST['db_user'] ?? '';
$db_pass = $_POST['db_pass'] ?? '';

// Validate parameters
if (empty($db_host) || empty($db_user)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Try to connect
try {
    $db = @new mysqli($db_host, $db_user, $db_pass);
    
    // Check for connection error
    if ($db->connect_error) {
        echo json_encode([
            'success' => false, 
            'message' => 'Connection failed: ' . $db->connect_error
        ]);
        exit;
    }
    
    // Successfully connected
    $db->close();
    echo json_encode(['success' => true, 'message' => 'Connection successful']);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
}
?>