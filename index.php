<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
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
    <title><?php echo htmlspecialchars($settings['title'] ?? 'Newsletter Dashboard'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body::before {
            background: url('<?php echo htmlspecialchars($settings['background'] ?? '../images/forest.png'); ?>') no-repeat center center;
            background-size: cover;
        }
    </style>
</head>
<body>
    <header>
        <h1><?php echo htmlspecialchars($settings['title'] ?? 'Newsletter Dashboard'); ?></h1>
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
        <h2>Welcome to the Newsletter Dashboard</h2>
        <p>Use the navigation to manage your newsletters and themes.</p>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>