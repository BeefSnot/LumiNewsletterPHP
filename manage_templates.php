<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access templates management
if (!canAccessEmailBuilder()) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

// Handle template deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $templateId = (int)$_GET['delete'];
    
    // Check if it's a system template (don't allow deletion)
    $checkStmt = $db->prepare("SELECT is_system FROM email_templates WHERE id = ?");
    $checkStmt->bind_param('i', $templateId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $template = $result->fetch_assoc();
        
        if ($template['is_system'] == 1) {
            $message = "System templates cannot be deleted";
            $messageType = 'error';
        } else {
            // Delete the template
            $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ?");
            $stmt->bind_param('i', $templateId);
            
            if ($stmt->execute()) {
                $message = "Template deleted successfully";
                $messageType = 'success';
            } else {
                $message = "Error deleting template: " . $db->error;
                $messageType = 'error';
            }
        }
    } else {
        $message = "Template not found";
        $messageType = 'error';
    }
}

// Display any messages passed via query params
if (isset($_GET['message']) && empty($message)) {
    $message = $_GET['message'];
    $messageType = $_GET['type'] ?? 'info';
}

// Get all email templates
$templates = [];
$templatesResult = $db->query("
    SELECT t.*, u.username as creator_name 
    FROM email_templates t
    LEFT JOIN users u ON t.created_by = u.id
    ORDER BY t.is_system DESC, t.name ASC
");

while ($templatesResult && $row = $templatesResult->fetch_assoc()) {
    $templates[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Email Templates | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .template-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .template-thumbnail {
            height: 160px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .template-thumbnail img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .template-thumbnail .placeholder {
            font-size: 48px;
            color: #ccc;
        }
        
        .template-info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .template-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .template-description {
            color: #666;
            margin-bottom: 10px;
            flex-grow: 1;
        }
        
        .template-meta {
            font-size: 13px;
            color: #888;
            margin-bottom: 15px;
        }
        
        .template-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .system-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Mobile navigation toggle button -->
    <button class="mobile-nav-toggle" id="mobileNavToggle">
        <i class="fas fa-bars" id="menuIcon"></i>
    </button>
    
    <!-- Backdrop for mobile menu -->
    <div class="backdrop" id="backdrop"></div>
    
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Email Templates</h1>
                </div>
                <div class="header-right">
                    <a href="email_builder.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create New Template
                    </a>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($templates)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt empty-icon"></i>
                    <h2>No Email Templates Yet</h2>
                    <p>Create your first email template to get started with the drag-and-drop email builder.</p>
                    <a href="email_builder.php" class="btn btn-primary">Create Template</a>
                </div>
            <?php else: ?>
                <div class="templates-grid">
                    <?php foreach ($templates as $template): ?>
                        <div class="template-card">
                            <div class="template-thumbnail">
                                <?php if ($template['thumbnail']): ?>
                                    <img src="<?php echo htmlspecialchars($template['thumbnail']); ?>" alt="<?php echo htmlspecialchars($template['name']); ?>">
                                <?php else: ?>
                                    <div class="placeholder"><i class="fas fa-file-alt"></i></div>
                                <?php endif; ?>
                                <?php if ($template['is_system']): ?>
                                    <span class="system-badge">System</span>
                                <?php endif; ?>
                            </div>
                            <div class="template-info">
                                <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                                <div class="template-description"><?php echo htmlspecialchars($template['description'] ?: 'No description'); ?></div>
                                <div class="template-meta">
                                    <?php if ($template['created_by']): ?>
                                        Created by <?php echo htmlspecialchars($template['creator_name']); ?><br>
                                    <?php endif; ?>
                                    Last updated: <?php echo date('M j, Y', strtotime($template['updated_at'])); ?>
                                </div>
                                <div class="template-actions">
                                    <a href="email_builder.php?id=<?php echo $template['id']; ?>" class="btn btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="template_preview.php?id=<?php echo $template['id']; ?>" class="btn btn-sm" target="_blank">
                                        <i class="fas fa-eye"></i> Preview
                                    </a>
                                    <?php if (!$template['is_system']): ?>
                                        <a href="manage_templates.php?delete=<?php echo $template['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this template?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="assets/js/sidebar.js"></script>
</body>
</html>