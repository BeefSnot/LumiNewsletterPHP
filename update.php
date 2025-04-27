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
            $message = 'Update applied successfully. Please refresh the page.';
            file_put_contents(__DIR__ . '/version.php', "<?php\nreturn '" . $latestVersion . "';\n");
        } else {
            $message = 'Failed to extract the update package. ZipArchive error code: ' . $extractResult;
            error_log($message);
        }
        unlink($tempFile);
    }
}

// Helper function to recursively copy files
function recursiveCopy($source, $dest) {
    $dir = opendir($source);
    @mkdir($dest, 0755, true);
    
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $sourcePath = $source . '/' . $file;
            $destPath = $dest . '/' . $file;
            
            if (is_dir($sourcePath)) {
                recursiveCopy($sourcePath, $destPath);
            } else {
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
    <title>Update Software</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Update Software</h1>
        <nav>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="admin.php">Admin Area</a></li>
                <li><a href="create_theme.php">Create Theme</a></li>
                <li><a href="send_newsletter.php">Send Newsletter</a></li>
                <li><a href="manage_newsletters.php">Manage Newsletters</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h2>Software Update</h2>
        <p>Current version: <strong><?php echo htmlspecialchars($currentVersion); ?></strong></p>
        <?php if ($updateAvailable): ?>
            <p style="color:green;">Update available: <strong><?php echo htmlspecialchars($latestVersion); ?></strong></p>
            <p><?php echo nl2br(htmlspecialchars($changelog)); ?></p>
            <form method="post">
                <button type="submit">Download & Install Update</button>
            </form>
        <?php else: ?>
            <p>No updates available.</p>
        <?php endif; ?>
        <?php if ($message): ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>