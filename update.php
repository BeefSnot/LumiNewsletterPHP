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
    // Fetch the latest update URL
    $latestUpdateInfo = file_get_contents('https://yourdomain.com/updates/latest_update.json');
    if ($latestUpdateInfo === false) {
        $message = 'Failed to fetch the latest update information.';
    } else {
        $latestUpdateInfo = json_decode($latestUpdateInfo, true);
        $updateUrl = $latestUpdateInfo['update_url'];

        // Download the update package
        $updatePackage = file_get_contents($updateUrl);
        if ($updatePackage === false) {
            $message = 'Failed to download the update package.';
        } else {
            // Save the update package to a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'update_') . '.zip';
            file_put_contents($tempFile, $updatePackage);

            // Extract the update package
            $zip = new ZipArchive;
            if ($zip->open($tempFile) === TRUE) {
                $zip->extractTo(__DIR__);
                $zip->close();
                $message = 'Update applied successfully.';
            } else {
                $message = 'Failed to extract the update package.';
            }

            // Clean up the temporary file
            unlink($tempFile);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <h2>Update Software</h2>
        <?php if ($message): ?>
            <p><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post">
            <button type="submit">Check for Updates</button>
        </form>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>