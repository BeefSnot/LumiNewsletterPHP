<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

// Get pre-built templates
$templatesResult = $db->query("SELECT id, name, content FROM themes WHERE id IN (1, 2, 3) LIMIT 3");
$templates = [];
while ($templatesResult && $row = $templatesResult->fetch_assoc()) {
    $templates[] = $row;
}

// Add default template if none exist
if (count($templates) == 0) {
    // Modern newsletter template
    $defaultTheme = '<div class="email-container" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; font-family: Arial, sans-serif; color: #333333;">
        <div class="email-header" style="background-color: #4285f4; color: #ffffff; padding: 20px; text-align: center;">
            <h1>Your Newsletter Name</h1>
            <p>The latest updates from our team</p>
        </div>
        <div class="email-body" style="padding: 20px;">
            <h2 style="color: #4285f4;">Welcome to our newsletter</h2>
            <p>This is where your main content goes. You can drag and drop components to build your newsletter.</p>
            <div style="margin: 20px 0; text-align: center;">
                <a href="#" style="background-color: #4285f4; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Call to Action</a>
            </div>
        </div>
        <div class="email-footer" style="background-color: #f5f7fa; padding: 20px; text-align: center; font-size: 12px; color: #757575;">
            <p>© ' . date('Y') . ' Your Company Name. All rights reserved.</p>
            <p>You received this email because you signed up for our newsletter.</p>
            <p><a href="#" style="color: #4285f4;">Unsubscribe</a></p>
        </div>
    </div>';
    
    $templates[] = [
        'id' => 'default',
        'name' => 'Modern Newsletter',
        'content' => $defaultTheme
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $themeName = $_POST['theme_name'];
    $themeContent = $_POST['theme_content'];

    // Save the theme to the database
    $stmt = $db->prepare('INSERT INTO themes (name, content) VALUES (?, ?)');
    if ($stmt) {
        $stmt->bind_param('ss', $themeName, $themeContent);
        if ($stmt->execute()) {
            $message = 'Theme created successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error saving theme: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    } else {
        $message = 'Database error: ' . $db->error;
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Theme | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- GrapesJS styles and scripts - Using specific versions for stability -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.18.4/dist/css/grapes.min.css">
    <script src="https://cdn.jsdelivr.net/npm/grapesjs@0.18.4/dist/grapes.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-preset-newsletter@0.2.20/dist/grapesjs-preset-newsletter.min.js"></script>
    
    <style>
        #editor-container {
            height: 700px;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            background-color: white;
        }
        
        #gjs {
            height: 100%;
            width: 100%; /* Add explicit width */
            display: block; /* Ensure it's displayed as a block */
            border-radius: 8px;
        }
        
        #theme_content {
            height: 700px;
            width: 100%;
            padding: 10px;
            font-family: monospace;
            font-size: 14px;
            line-height: 1.5;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .template-picker {
            display: flex;
            overflow-x: auto;
            gap: 15px;
            margin-bottom: 20px;
            padding: 10px 0;
        }
        
        .template-card {
            min-width: 200px;
            border-radius: var(--radius);
            border: 2px solid #e0e0e0;
            overflow: hidden;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .template-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .template-card.active {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.2);
        }
        
        .template-preview {
            height: 140px;
            overflow: hidden;
            background-color: #f5f7fa;
            position: relative;
        }
        
        .template-preview img {
            width: 100%;
            height: auto;
            object-fit: cover;
        }
        
        .template-preview iframe {
            width: 100%;
            height: 300px;
            transform: scale(0.5);
            transform-origin: top left;
            pointer-events: none;
        }
        
        .template-info {
            padding: 10px;
            background-color: white;
            border-top: 1px solid #e0e0e0;
        }
        
        .template-info h4 {
            margin: 0;
            font-size: 14px;
        }
        
        .template-selector {
            margin-bottom: 20px;
        }
        
        .view-switcher {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .view-mode {
            padding: 8px 15px;
            border-radius: var(--radius);
            border: 1px solid #e0e0e0;
            background: white;
            cursor: pointer;
        }
        
        .view-mode.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .editor-placeholder {
            height: 100%;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background-color: #f5f7fa;
            border-radius: var(--radius);
        }
        
        .editor-placeholder i {
            font-size: 4rem;
            color: var(--primary);
            opacity: 0.5;
            margin-bottom: 1rem;
        }
        
        .editor-placeholder p {
            font-size: 1.2rem;
            color: var(--gray);
        }
        
        .notification {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
        }
        
        .notification.success {
            background-color: rgba(52, 168, 83, 0.1);
            color: var(--accent);
        }
        
        .notification.error {
            background-color: rgba(234, 67, 53, 0.1);
            color: var(--error);
        }
        
        .notification i {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }
        
        /* Fix for GrapesJS editor display issues */
        .gjs-editor-cont {
            position: relative !important;
            overflow: visible !important;
        }
        
        .gjs-cv-canvas {
            width: 100% !important;
            height: 100% !important;
        }
        
        /* Error message display */
        #editor-error {
            padding: 20px;
            background-color: rgba(234, 67, 53, 0.1);
            color: var(--error);
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-paper-plane"></i>
                    <h2>LumiNews</h2>
                </div>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="admin.php" class="nav-item"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <li><a href="create_theme.php" class="nav-item active"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="logout.php" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Create Newsletter Theme</h1>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div id="editor-error">
                <strong>Error:</strong> The editor failed to load. Please check your browser console for more details.
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-palette"></i> Theme Builder</h2>
                </div>
                <div class="card-body">
                    <form method="post" id="theme-form">
                        <div class="form-group">
                            <label for="theme_name">Theme Name:</label>
                            <input type="text" id="theme_name" name="theme_name" required placeholder="Enter a name for your theme">
                        </div>
                        
                        <div class="form-group">
                            <label>Start with a Template:</label>
                            <div class="template-picker">
                                <?php foreach ($templates as $index => $template): ?>
                                <div class="template-card" data-template-id="<?php echo htmlspecialchars($template['id']); ?>" onclick="selectTemplate(this, <?php echo $index; ?>)">
                                    <div class="template-preview">
                                        <div id="preview-frame-<?php echo $index; ?>"></div>
                                    </div>
                                    <div class="template-info">
                                        <h4><?php echo htmlspecialchars($template['name']); ?></h4>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="view-switcher">
                            <button type="button" class="view-mode active" data-mode="visual" onclick="switchView('visual')">
                                <i class="fas fa-eye"></i> Visual Editor
                            </button>
                            <button type="button" class="view-mode" data-mode="code" onclick="switchView('code')">
                                <i class="fas fa-code"></i> Code Editor
                            </button>
                        </div>
                        
                        <div id="editor-container">
                            <div id="gjs"></div>
                            <textarea id="theme_content" name="theme_content" style="display: none;"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Theme
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>

    <script>
        let editor;
        let currentMode = 'visual';
        let templates = <?php echo json_encode($templates); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // Render template previews
                templates.forEach((template, index) => {
                    const previewEl = document.getElementById(`preview-frame-${index}`);
                    if (previewEl) {
                        previewEl.innerHTML = template.content;
                    }
                });
                
                // Initialize GrapesJS with better error handling
                initializeEditor();
                
                // Set up form submission
                document.getElementById('theme-form').addEventListener('submit', function(e) {
                    if (currentMode === 'visual' && editor) {
                        document.getElementById('theme_content').value = editor.getHtml() + '<style>' + editor.getCss() + '</style>';
                    }
                });
                
                // Select first template by default
                if (templates.length > 0) {
                    selectTemplate(document.querySelector('.template-card'), 0);
                }
            } catch (error) {
                console.error('Error initializing editor:', error);
                document.getElementById('editor-error').style.display = 'block';
                document.getElementById('editor-error').innerHTML += '<br>Error details: ' + error.message;
            }
        });
        
        function initializeEditor() {
            // Make sure GrapesJS is loaded
            if (typeof grapesjs === 'undefined') {
                throw new Error('GrapesJS library not loaded. Check your internet connection or try a different browser.');
            }
            
            // Initialize the editor
            editor = grapesjs.init({
                container: '#gjs',
                components: templates.length > 0 ? templates[0].content : '',
                height: '100%',
                width: 'auto',
                storageManager: false,
                plugins: ['gjs-preset-newsletter'],
                pluginsOpts: {
                    'gjs-preset-newsletter': {
                        modalTitleImport: 'Import your code',
                        modalLabelImport: 'Paste your HTML or CSS code here',
                        modalBtnImport: 'Import',
                        modalLabelExport: 'Get HTML & CSS',
                        modalBtnExport: 'Export',
                        modalTitleExport: 'Export template',
                        overwriteImport: true
                    }
                },
                deviceManager: {
                    devices: [
                        {
                            name: 'Desktop',
                            width: '100%'
                        }
                    ]
                },
                panels: {
                    defaults: [
                        {
                            id: 'panel-devices',
                            el: '.panel__devices',
                            buttons: [],
                        },
                        {
                            id: 'panel-switcher',
                            el: '.panel__switcher',
                            buttons: [],
                        }
                    ]
                },
                assetManager: {
                    assets: [
                        'https://via.placeholder.com/350x250/78c5d6/fff',
                        'https://via.placeholder.com/350x250/459ba8/fff',
                        'https://via.placeholder.com/350x250/79c267/fff',
                        'https://via.placeholder.com/350x250/c5d647/fff',
                        'https://via.placeholder.com/350x250/f28c33/fff'
                    ],
                    upload: false
                }
            });
            
            // Show success message once editor is loaded
            editor.on('load', () => {
                console.log('Editor loaded successfully');
            });
            
            // Show error message if editor fails to load
            editor.on('error', (err) => {
                console.error('Editor error:', err);
                document.getElementById('editor-error').style.display = 'block';
                document.getElementById('editor-error').innerHTML += '<br>Editor error: ' + err;
            });
        }
        
        function selectTemplate(element, index) {
            try {
                // Remove active class from all templates
                document.querySelectorAll('.template-card').forEach(card => {
                    card.classList.remove('active');
                });
                
                // Add active class to selected template
                element.classList.add('active');
                
                // Load template into editor
                const templateContent = templates[index].content;
                
                if (currentMode === 'visual' && editor) {
                    editor.setComponents(templateContent);
                } else {
                    document.getElementById('theme_content').value = templateContent;
                }
            } catch (error) {
                console.error('Error selecting template:', error);
                alert('Error selecting template. See console for details.');
            }
        }
        
        function switchView(mode) {
            try {
                // Get current content
                let content = '';
                if (currentMode === 'visual' && editor) {
                    content = editor.getHtml() + '<style>' + editor.getCss() + '</style>';
                } else {
                    content = document.getElementById('theme_content').value;
                }
                
                // Update buttons
                document.querySelectorAll('.view-mode').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector(`.view-mode[data-mode="${mode}"]`).classList.add('active');
                
                // Switch view
                if (mode === 'visual') {
                    document.getElementById('gjs').style.display = 'block';
                    document.getElementById('theme_content').style.display = 'none';
                    if (editor) {
                        editor.setComponents(content);
                    }
                } else {
                    document.getElementById('gjs').style.display = 'none';
                    document.getElementById('theme_content').style.display = 'block';
                    document.getElementById('theme_content').value = content;
                }
                
                currentMode = mode;
            } catch (error) {
                console.error('Error switching view:', error);
                alert('Error switching view. See console for details.');
            }
        }
    </script>
</body>
</html>