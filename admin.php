<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['title'])) {
        $title = $_POST['title'];
        $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'title'");
        $stmt->bind_param('s', $title);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_POST['background'])) {
        $background = $_POST['background'];
        $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'background'");
        $stmt->bind_param('s', $background);
        $stmt->execute();
        $stmt->close();
    }
    
    // Handle the site URL setting
    if (isset($_POST['site_url'])) {
        $site_url = rtrim($_POST['site_url'], '/'); // Remove trailing slash if present
        
        // Check if the setting already exists
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE name = 'site_url'");
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();
        
        if ($count > 0) {
            $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'site_url'");
        } else {
            $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES ('site_url', ?)");
        }
        
        $stmt->bind_param('s', $site_url);
        $stmt->execute();
        $stmt->close();
    }

    $message = 'Settings updated successfully';
}

// Fetch current settings
$settingsResult = $db->query("SELECT name, value FROM settings");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['name']] = $row['value'];
}

define('UPDATE_JSON_URL', 'https://lumihost.net/updates/latest_update.json'); // <--- Hardcoded

$currentVersion = require 'version.php';
$latestUpdateInfo = @file_get_contents(UPDATE_JSON_URL . '?nocache=' . time());
$updateAvailable = false;
$latestVersion = '';
if ($latestUpdateInfo !== false) {
    $latestUpdateInfo = json_decode($latestUpdateInfo, true);
    $latestVersion = $latestUpdateInfo['version'] ?? '';
    if ($latestVersion && version_compare($latestVersion, $currentVersion, '>')) {
        $updateAvailable = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Area | LumiNewsletter</title>
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
                    <li><a href="admin.php" class="nav-item active"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="ab_testing.php" class="nav-item"><i class="fas fa-flask"></i> A/B Testing</a></li>
                    <li><a href="segments.php" class="nav-item"><i class="fas fa-tags"></i> Segments</a></li>
                    <li><a href="privacy_settings.php" class="nav-item"><i class="fas fa-shield-alt"></i> Privacy</a></li>
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
                    <h1>Admin Settings</h1>
                </div>
                <div class="header-right">
                    <?php if ($updateAvailable): ?>
                        <div class="update-notification">
                            <i class="fas fa-download"></i> Update available: v<?php echo htmlspecialchars($latestVersion); ?>
                            <a href="update.php" class="btn btn-sm btn-accent">Update now</a>
                        </div>
                    <?php endif; ?>
                    <form action="update.php" method="get">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Check for Updates
                        </button>
                    </form>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>General Settings</h2>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="title">Newsletter Title:</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($settings['title'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="background">Background URL:</label>
                            <input type="text" id="background" name="background" value="<?php echo htmlspecialchars($settings['background'] ?? ''); ?>" required>
                            <?php if (!empty($settings['background'])): ?>
                                <div class="preview">
                                    <img src="<?php echo htmlspecialchars($settings['background']); ?>" alt="Background preview" class="thumbnail">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_url">Website URL (for embeddable widgets):</label>
                            <input type="url" id="site_url" name="site_url" value="<?php echo htmlspecialchars($settings['site_url'] ?? ''); ?>" placeholder="https://example.com" required>
                            <small>Enter your website's full URL where LumiNewsletter is installed (without trailing slash)</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-grid">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-users"></i> Subscribers</h2>
                    </div>
                    <div class="card-body">
                        <p>Manage your newsletter subscribers and their group memberships.</p>
                        <a href="manage_subscriptions.php" class="btn btn-primary">Manage Subscribers</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-shield"></i> Users</h2>
                    </div>
                    <div class="card-body">
                        <p>Manage admin users and their permissions.</p>
                        <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-server"></i> SMTP Settings</h2>
                    </div>
                    <div class="card-body">
                        <p>Configure your email server settings for newsletter delivery.</p>
                        <a href="manage_smtp.php" class="btn btn-primary">Configure SMTP</a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
</body>
</html>