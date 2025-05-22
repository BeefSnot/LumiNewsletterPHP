<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only admin users can access this page
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$messages = [];
$errors = [];

// Fetch content blocks
$contentBlocks = [];
try {
    $contentBlocksResult = $db->query("SELECT * FROM content_blocks ORDER BY created_at DESC");
    if ($contentBlocksResult) {
        while ($row = $contentBlocksResult->fetch_assoc()) {
            $contentBlocks[] = $row;
        }
    } else {
        throw new Exception("Failed to fetch content blocks: " . $db->error);
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// Fetch email templates
$emailTemplates = [];
try {
    $emailTemplatesResult = $db->query("SELECT * FROM email_templates ORDER BY created_at DESC");
    if ($emailTemplatesResult) {
        while ($row = $emailTemplatesResult->fetch_assoc()) {
            $emailTemplates[] = $row;
        }
    } else {
        throw new Exception("Failed to fetch email templates: " . $db->error);
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Library | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        .content-library {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .content-item {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px;
            width: calc(50% - 10px);
        }
        .content-item h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }
        .content-item p {
            margin: 0 0 10px;
            color: #666;
        }
        .content-item .actions {
            margin-top: 10px;
        }
        .content-item .actions a {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Content Library</h1>
                </div>
            </header>

            <?php if (!empty($errors)): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo implode('<br>', $errors); ?>
                </div>
            <?php endif; ?>

            <div class="content-library">
                <div class="content-section">
                    <h2>Content Blocks</h2>
                    <?php if (!empty($contentBlocks)): ?>
                        <?php foreach ($contentBlocks as $block): ?>
                            <div class="content-item">
                                <h3><?php echo htmlspecialchars($block['name']); ?></h3>
                                <p><?php echo htmlspecialchars($block['description'] ?? 'No description available'); ?></p>
                                <div class="actions">
                                    <a href="content_blocks.php?edit=<?php echo $block['id']; ?>" class="btn btn-sm">Edit</a>
                                    <a href="content_blocks.php?delete=<?php echo $block['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this block?');">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No content blocks found.</p>
                    <?php endif; ?>
                </div>

                <div class="content-section">
                    <h2>Email Templates</h2>
                    <?php if (!empty($emailTemplates)): ?>
                        <?php foreach ($emailTemplates as $template): ?>
                            <div class="content-item">
                                <h3><?php echo htmlspecialchars($template['name']); ?></h3>
                                <p><?php echo htmlspecialchars($template['description'] ?? 'No description available'); ?></p>
                                <div class="actions">
                                    <a href="manage_templates.php?edit=<?php echo $template['id']; ?>" class="btn btn-sm">Edit</a>
                                    <a href="manage_templates.php?delete=<?php echo $template['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this template?');">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No email templates found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>