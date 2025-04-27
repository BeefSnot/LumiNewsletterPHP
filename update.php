<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

define('UPDATE_JSON_URL', 'https://lumihost.net/updates/latest_update.json'); // Or your own update URL

$currentVersion = require 'version.php';
$message = '';
$messageType = '';
$updateAvailable = false;
$latestVersion = '';
$changelog = '';
$updateUrl = '';

$latestUpdateInfo = @file_get_contents(UPDATE_JSON_URL);
if ($latestUpdateInfo !== false) {
    $latestUpdateInfo = json_decode($latestUpdateInfo, true);
    $latestVersion = $latestUpdateInfo['version'] ?? '';
    $changelog = $latestUpdateInfo['changelog'] ?? '';
    $updateUrl = $latestUpdateInfo['update_url'] ?? '';
    if ($latestVersion && version_compare($latestVersion, $currentVersion, '>')) {
        $updateAvailable = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $updateAvailable) {
    $updatePackage = @file_get_contents($updateUrl);
    if ($updatePackage === false) {
        $message = 'Failed to download the update package.';
        $messageType = 'error';
        error_log($message);
    } else {
        $tempFile = tempnam(sys_get_temp_dir(), 'update_') . '.zip';
        file_put_contents($tempFile, $updatePackage);
        
        // Create a temporary extraction directory
        $extractPath = sys_get_temp_dir() . '/lumi_update_' . time();
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $zip = new ZipArchive;
        $extractResult = $zip->open($tempFile);
        if ($extractResult === TRUE) {
            // First extract to temp directory
            $zip->extractTo($extractPath);
            $zip->close();
            
            // Check if there's a single directory in the extracted content
            $items = scandir($extractPath);
            $rootDir = null;
            foreach ($items as $item) {
                if ($item != '.' && $item != '..' && is_dir($extractPath . '/' . $item)) {
                    $rootDir = $extractPath . '/' . $item;
                    break;
                }
            }
            
            // If we found a single directory, copy its contents instead
            if ($rootDir !== null) {
                // Copy files from subfolder to current directory
                recursiveCopy($rootDir, __DIR__);
            } else {
                // No subfolder, copy all files directly
                recursiveCopy($extractPath, __DIR__);
            }
            
            // Clean up
            recursiveDelete($extractPath);
            $message = 'Update applied successfully! LumiNewsletter has been updated to version ' . $latestVersion;
            $messageType = 'success';
            file_put_contents(__DIR__ . '/version.php', "<?php\nreturn '" . $latestVersion . "';\n");
        } else {
            $message = 'Failed to extract the update package. ZipArchive error code: ' . $extractResult;
            $messageType = 'error';
            error_log($message);
        }
        unlink($tempFile);
    }
}

// Helper function to recursively copy files
function recursiveCopy($source, $dest) {
    $dir = opendir($source);
    @mkdir($dest, 0755, true);
    
    // Files that should never be overwritten
    $protectedFiles = [
        'config.php',
        'includes/config.php',
        '.htaccess',
        'version.php',
        'db.php',
        'includes/db.php'
    ];
    
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $sourcePath = $source . '/' . $file;
            $destPath = $dest . '/' . $file;
            
            // Skip protected files - silently
            $relativePath = str_replace(__DIR__ . '/', '', $destPath);
            if (in_array($relativePath, $protectedFiles)) {
                continue;
            }
            
            if (is_dir($sourcePath)) {
                recursiveCopy($sourcePath, $destPath);
            } else {
                // Check if destination file exists and make backup if needed
                if (file_exists($destPath) && !in_array(pathinfo($destPath, PATHINFO_EXTENSION), ['jpg', 'png', 'gif', 'svg', 'ico'])) {
                    // Create backups folder if it doesn't exist
                    if (!file_exists($dest . '/update_backups')) {
                        mkdir($dest . '/update_backups', 0755, true);
                    }
                    // Make a backup
                    copy($destPath, $dest . '/update_backups/' . basename($destPath) . '.bak');
                }
                
                // Now copy the file
                copy($sourcePath, $destPath);
            }
        }
    }
    closedir($dir);
}

// Helper function to recursively delete directory
function recursiveDelete($dir) {
    if (!file_exists($dir)) return;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? recursiveDelete($path) : unlink($path);
    }
    return rmdir($dir);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Software | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .version-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        
        .version-info i {
            margin-right: 12px;
            font-size: 1.5rem;
            color: var(--primary);
        }

        .update-available {
            background-color: rgba(52, 168, 83, 0.1);
            border-left: 4px solid var(--accent);
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
        }
        
        .update-available h3 {
            color: var(--accent);
            margin-top: 0;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            font-size: 1.2rem;
        }
        
        .update-available h3 i {
            margin-right: 8px;
        }
        
        .update-changelog {
            background-color: var(--gray-light);
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            max-height: 200px;
            overflow-y: auto;
            font-size: 0.95rem;
        }
        
        .update-changelog h4 {
            margin-top: 0;
            color: var(--gray);
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .update-changelog h4 i {
            margin-right: 8px;
        }
        
        .update-changelog ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .update-changelog li {
            margin-bottom: 5px;
        }
        
        .update-btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            transition: background-color 0.3s ease;
        }
        
        .update-btn i {
            margin-right: 8px;
        }
        
        .update-btn:hover {
            background-color: #2d9348;
        }
        
        .up-to-date {
            display: flex;
            align-items: center;
            background-color: var(--gray-light);
            padding: 15px;
            border-radius: var(--radius);
        }
        
        .up-to-date i {
            margin-right: 12px;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .notification {
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .notification i {
            font-size: 1.5rem;
            margin-right: 12px;
        }
        
        .notification.success {
            background-color: rgba(52, 168, 83, 0.1);
            border-left: 4px solid var(--accent);
            color: var(--accent);
        }
        
        .notification.error {
            background-color: rgba(234, 67, 53, 0.1);
            border-left: 4px solid var(--error);
            color: var(--error);
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
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
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
                    <h1>Update Software</h1>
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
                    <h2><i class="fas fa-sync-alt"></i> Software Update</h2>
                </div>
                <div class="card-body">
                    <div class="version-info">
                        <i class="fas fa-code-branch"></i>
                        <span>Current version: <strong><?php echo htmlspecialchars($currentVersion); ?></strong></span>
                    </div>
                    
                    <?php if ($updateAvailable): ?>
                        <div class="update-available">
                            <h3><i class="fas fa-download"></i> Update Available!</h3>
                            <p>A new version of LumiNewsletter (v<?php echo htmlspecialchars($latestVersion); ?>) is ready to install.</p>
                        </div>
                        
                        <div class="update-changelog">
                            <h4><i class="fas fa-list"></i> What's New</h4>
                            <?php 
                            $changelogLines = explode("\n", $changelog);
                            if (count($changelogLines) > 1): 
                            ?>
                                <ul>
                                    <?php foreach ($changelogLines as $line): ?>
                                        <?php $line = trim($line); ?>
                                        <?php if (!empty($line)): ?>
                                            <li><?php echo htmlspecialchars(ltrim($line, '- ')); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p><?php echo htmlspecialchars($changelog); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <form method="post">
                            <button type="submit" class="update-btn">
                                <i class="fas fa-download"></i> Download & Install Update
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="up-to-date">
                            <i class="fas fa-check-circle"></i>
                            <span>Your LumiNewsletter is up to date! No updates are available at this time.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
</body>
</html>