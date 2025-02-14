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

    $message = 'Settings updated successfully';
}

// Fetch current settings
$settingsResult = $db->query("SELECT name, value FROM settings");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['name']] = $row['value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Area</title>
    <link rel="stylesheet" href="assets/css/newsletter.css">
</head>
<body>
    <header>
        <h1>Admin Area</h1>
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
        <h2>Admin Settings</h2>
        <?php if ($message): ?>
            <p><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($settings['title'] ?? ''); ?>" required>
            
            <label for="background">Background URL:</label>
            <input type="text" id="background" name="background" value="<?php echo htmlspecialchars($settings['background'] ?? ''); ?>" required>
            
            <button type="submit">Update Settings</button>
        </form>
    </main>
</body>
</html>