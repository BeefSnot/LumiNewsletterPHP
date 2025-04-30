<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin can access this script
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$messages = [];
$errors = [];

// Check if group_subscriptions table exists
$checkTable = $db->query("SHOW TABLES LIKE 'group_subscriptions'");
if ($checkTable->num_rows > 0) {
    // Check if name column exists
    $checkColumn = $db->query("SHOW COLUMNS FROM `group_subscriptions` LIKE 'name'");
    if ($checkColumn->num_rows === 0) {
        try {
            // Add name column if it doesn't exist
            $db->query("ALTER TABLE `group_subscriptions` ADD COLUMN `name` VARCHAR(255) NULL AFTER `email`");
            $messages[] = "Successfully added 'name' column to group_subscriptions table";
        } catch (Exception $e) {
            $errors[] = "Error adding name column: " . $e->getMessage();
        }
    } else {
        $messages[] = "The 'name' column already exists in the group_subscriptions table";
    }
} else {
    // Create the group_subscriptions table with all required columns
    try {
        $db->query("CREATE TABLE IF NOT EXISTS `group_subscriptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `group_id` INT NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `name` VARCHAR(255) NULL,
            `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`group_id`),
            INDEX (`email`)
        )");
        $messages[] = "Successfully created the group_subscriptions table with all required columns";
    } catch (Exception $e) {
        $errors[] = "Error creating group_subscriptions table: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Subscriptions Table | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
</head>
<body>
    <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px;">
        <h1>Fix Group Subscriptions Table Structure</h1>
        
        <?php if (!empty($messages)): ?>
            <div class="notification success">
                <h3>Success:</h3>
                <ul>
                    <?php foreach ($messages as $message): ?>
                        <li><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="notification error">
                <h3>Errors:</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <p>
            <a href="manage_subscriptions.php" class="btn btn-primary">Return to Manage Subscriptions</a>
            <a href="fix_tables.php" class="btn">Run Full Table Repair</a>
        </p>
    </div>
</body>
</html>