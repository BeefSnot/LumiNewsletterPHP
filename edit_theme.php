<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check permissions - only logged in users can edit themes
requireLogin();

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme_id = $_POST['theme_id'];
    $theme_content = $_POST['theme_content'];

    $stmt = $db->prepare('UPDATE themes SET content = ? WHERE id = ?');
    if ($stmt === false) {
        $message = 'Prepare failed: ' . htmlspecialchars($db->error);
        $messageType = 'error';
    } else {
        $stmt->bind_param('si', $theme_content, $theme_id);
        if ($stmt->execute() === false) {
            $message = 'Execute failed: ' . htmlspecialchars($stmt->error);
            $messageType = 'error';
        } else {
            $message = 'Theme updated successfully';
            $messageType = 'success';
        }
        $stmt->close();
    }
}

// Fetch themes
$themesResult = $db->query("SELECT id, name, content FROM themes");
$themes = [];
while ($row = $themesResult->fetch_assoc()) {
    $themes[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Themes | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/tinymce/tinymce.min.js"></script>
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
                    <h1>Edit Themes</h1>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-edit"></i> Edit Themes</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($themes)): ?>
                        <p>No themes found. <a href="create_theme.php">Create a new theme</a>.</p>
                    <?php else: ?>
                        <form method="post">
                            <div class="form-group">
                                <label for="theme_id">Select Theme:</label>
                                <select id="theme_id" name="theme_id" onchange="loadThemeContent(this.value)">
                                    <?php foreach ($themes as $theme): ?>
                                        <option value="<?php echo $theme['id']; ?>"><?php echo htmlspecialchars($theme['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="theme_content">Theme Content:</label>
                                <textarea id="theme_content" name="theme_content" rows="20"></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Theme</button>
                                <a href="create_theme.php" class="btn btn-outline"><i class="fas fa-plus"></i> Create New Theme</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script src="assets/js/sidebar.js"></script>
    <script>
        const themes = <?php echo json_encode($themes); ?>;
        
        // Initialize the editor when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (themes.length > 0) {
                loadThemeContent(themes[0].id);
            }
            
            // Initialize TinyMCE
            tinymce.init({
                selector: '#theme_content',
                height: 500,
                plugins: 'code preview fullscreen',
                toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | code fullscreen',
                content_css: 'assets/css/newsletter-style.css'
            });
        });
        
        function loadThemeContent(themeId) {
            const selectedTheme = themes.find(theme => theme.id == themeId);
            if (selectedTheme) {
                if (tinymce.get('theme_content')) {
                    tinymce.get('theme_content').setContent(selectedTheme.content);
                } else {
                    document.getElementById('theme_content').value = selectedTheme.content;
                }
            }
        }
    </script>
</body>
</html>