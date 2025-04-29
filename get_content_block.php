<?php
require_once 'includes/db.php';

// Check if block ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo 'Invalid block ID';
    exit;
}

$blockId = (int)$_GET['id'];

// Get the content block from database
$stmt = $db->prepare("SELECT * FROM content_blocks WHERE id = ?");
$stmt->bind_param("i", $blockId);
$stmt->execute();
$block = $stmt->get_result()->fetch_assoc();

if (!$block) {
    echo 'Content block not found';
    exit;
}

// Add markers to identify this as a content block in the email
$blockMarker = "<!-- CONTENT_BLOCK_START:{$blockId} -->";
$blockMarkerEnd = "<!-- CONTENT_BLOCK_END:{$blockId} -->";

// Return the content with markers
echo $blockMarker . $block['content'] . $blockMarkerEnd;