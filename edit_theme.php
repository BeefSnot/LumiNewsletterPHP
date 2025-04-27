<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme_id = $_POST['theme_id'];
    $theme_content = $_POST['theme_content'];

    $stmt = $db->prepare('UPDATE themes SET content = ? WHERE id = ?');
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($db->error));
    }
    $stmt->bind_param('si', $theme_content, $theme_id);
    if ($stmt->execute() === false) {
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }
    $stmt->close();

    $message = 'Theme updated successfully';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-plus"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-envelope"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-tasks"></i> Manage Newsletters</a></li>
                    <li><a href="edit_theme.php" class="nav-item"><i class="fas fa-edit"></i> Edit Themes</a></li>
                    <li><a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Edit Themes</h1>
                </div>
            </header>
            
            <div class="card">
                <div class="card-header">
                    <h2>Edit Themes</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <p><?php echo $message; ?></p>
                    <?php endif; ?>
                    <form method="post">
                        <label for="theme_id">Select Theme:</label>
                        <select id="theme_id" name="theme_id" onchange="loadThemeContent(this.value)">
                            <?php foreach ($themes as $theme): ?>
                                <option value="<?php echo $theme['id']; ?>"><?php echo $theme['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label for="theme_content">Theme Content:</label>
                        <textarea id="theme_content" name="theme_content"></textarea>
                        
                        <button type="submit">Update Theme</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    <script>
        const themes = <?php echo json_encode($themes); ?>;
        function loadThemeContent(themeId) {
            const selectedTheme = themes.find(theme => theme.id == themeId);
            if (selectedTheme) {
                document.getElementById('theme_content').value = selectedTheme.content;
            }
        }
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>