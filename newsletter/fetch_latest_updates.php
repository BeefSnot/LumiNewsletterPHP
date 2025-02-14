<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

// Fetch the latest updates
$updatesResult = $db->query("SELECT title, content, created_at FROM updates ORDER BY created_at DESC LIMIT 5");
$updates = [];
while ($row = $updatesResult->fetch_assoc()) {
    $updates[] = $row;
}

echo json_encode($updates);
?>