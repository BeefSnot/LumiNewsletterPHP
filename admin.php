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

    // Handle social sharing toggle
    if (isset($_POST['social_sharing'])) {
        $socialSharingEnabled = isset($_POST['social_sharing_enabled']) ? '1' : '0';
        
        // Check if setting exists
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE name = 'social_sharing_enabled'");
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();
        
        if ($count > 0) {
            $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = 'social_sharing_enabled'");
        } else {
            $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES ('social_sharing_enabled', ?)");
        }
        
        $stmt->bind_param('s', $socialSharingEnabled);
        $stmt->execute();
        $stmt->close();
        
        $message = 'Social sharing settings updated successfully';
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
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-shield-alt"></i> Privacy</h2>
                    </div>
                    <div class="card-body">
                        <p>Configure privacy policy and data retention settings.</p>
                        <a href="privacy_settings.php" class="btn btn-primary">Privacy Settings</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-layer-group"></i> Groups</h2>
                    </div>
                    <div class="card-body">
                        <p>Create and manage subscriber groups to organize your audience.</p>
                        <a href="manage_groups.php" class="btn btn-primary">Manage Groups</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-share-alt"></i> Social Integration</h2>
                    </div>
                    <div class="card-body">
                        <p>Configure social media sharing options for newsletters.</p>
                        <a href="social_sharing.php" class="btn btn-primary">Social Settings</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-share-alt"></i> Social Media Integration</h2>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="social_sharing_enabled" <?php echo ($settings['social_sharing_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    Enable social sharing for newsletters
                                </label>
                                <small>When enabled, subscribers can share your newsletters on social media platforms</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="social_sharing" class="btn btn-primary">Save Social Settings</button>
                            </div>
                        </form>
                        
                        <div class="mt-3">
                            <p>Configure detailed social sharing options and view analytics:</p>
                            <a href="social_sharing.php" class="btn btn-outline">Social Sharing Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script src="assets/js/sidebar.js"></script>
</body>
</html>