<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Allow both admin and editor users to access template previews
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'editor')) {
    header('Location: login.php');
    exit();
}

$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($template_id <= 0) {
    header('Location: manage_templates.php');
    exit();
}

// Get template content
$stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
$stmt->bind_param('i', $template_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: manage_templates.php');
    exit();
}

$template = $result->fetch_assoc();
$html_content = $template['content'];

// If it's just a fragment, wrap it in a full HTML document
if (strpos($html_content, '<!DOCTYPE') === false) {
    $html_content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($template['name']) . ' Preview</title>
</head>
<body>
    ' . $html_content . '
</body>
</html>';
}

// Output the template directly
header('Content-Type: text/html; charset=utf-8');
echo $html_content;
exit;