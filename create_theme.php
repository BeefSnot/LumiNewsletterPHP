<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $themeName = $_POST['theme_name'];
    $themeContent = $_POST['theme_content'];

    // Save the theme to the database
    $stmt = $db->prepare('INSERT INTO themes (name, content) VALUES (?, ?)');
    $stmt->bind_param('ss', $themeName, $themeContent);
    $stmt->execute();
    $stmt->close();

    $message = 'Theme created successfully';
}

// Fetch the latest updates
$updatesResult = $db->query("SELECT title, content FROM updates ORDER BY created_at DESC LIMIT 5");
$updates = [];
while ($row = $updatesResult->fetch_assoc()) {
    $updates[] = $row;
}

// Generate the updates HTML
$updatesHtml = '';
foreach ($updates as $update) {
    $updatesHtml .= '<h2>' . htmlspecialchars($update['title']) . '</h2>';
    $updatesHtml .= '<p>' . htmlspecialchars($update['content']) . '</p>';
}

// Default theme content with placeholders
$defaultThemeContent = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Theme</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/newsletter.css">
    <style>
        .hero {
            background: #1a1d29;
            color: #ffffff;
            padding: 50px 0;
            text-align: center;
        }
        .content {
            padding: 20px;
            background: #151720;
            color: #ffffff;
        }
        .content h2 {
            color: #1592e8;
        }
        .content p {
            color: #ffffff;
        }
        .footer {
            background: #1a1d29;
            color: #ffffff;
            padding: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="hero">
        <h1>Welcome to Our Newsletter</h1>
        <p>Stay updated with the latest news and updates</p>
    </header>
    <main class="content">
        $updatesHtml
    </main>
    <footer class="footer">
        <p>&copy; 2025 Lumi Host. All Rights Reserved.</p>
    </footer>
</body>
</html>
HTML;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Theme</title>
    <link rel="stylesheet" href="assets/css/newsletter.css">
</head>
<body>
    <header>
        <h1>Create Theme</h1>
        <nav>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="create_theme.php">Create Theme</a></li>
                <li><a href="send_newsletter.php">Send Newsletter</a></li>
                <li><a href="manage_newsletters.php">Manage Newsletters</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h2>Create a New Theme</h2>
        <?php if (isset($message)): ?>
            <p><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="theme_name">Theme Name:</label>
            <input type="text" id="theme_name" name="theme_name" required>
            <label for="theme_content">Theme Content (HTML):</label>
            <textarea id="theme_content" name="theme_content" required><?php echo htmlspecialchars($defaultThemeContent); ?></textarea>
            <button type="submit">Create Theme</button>
        </form>
    </main>
</body>
</html>