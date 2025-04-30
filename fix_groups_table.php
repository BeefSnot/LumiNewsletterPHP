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

// Check if groups table exists
$checkTable = $db->query("SHOW TABLES LIKE 'groups'");
if ($checkTable->num_rows > 0) {
    // Check if description column exists
    $checkColumn = $db->query("SHOW COLUMNS FROM `groups` LIKE 'description'");
    if ($checkColumn->num_rows === 0) {
        try {
            // Add description column if it doesn't exist
            $db->query("ALTER TABLE `groups` ADD COLUMN `description` TEXT NULL AFTER `name`");
            $messages[] = "Successfully added 'description' column to groups table";
        } catch (Exception $e) {
            $errors[] = "Error adding description column: " . $e->getMessage();
        }
    } else {
        $messages[] = "The 'description' column already exists in the groups table";
    }
    
    // Check if created_at column exists
    $checkColumn = $db->query("SHOW COLUMNS FROM `groups` LIKE 'created_at'");
    if ($checkColumn->num_rows === 0) {
        try {
            // Add created_at column if it doesn't exist
            $db->query("ALTER TABLE `groups` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            $messages[] = "Successfully added 'created_at' column to groups table";
        } catch (Exception $e) {
            $errors[] = "Error adding created_at column: " . $e->getMessage();
        }
    } else {
        $messages[] = "The 'created_at' column already exists in the groups table";
    }
} else {
    // Create the groups table with all required columns
    try {
        $db->query("CREATE TABLE IF NOT EXISTS `groups` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $messages[] = "Successfully created the groups table with all required columns";
    } catch (Exception $e) {
        $errors[] = "Error creating groups table: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Groups Table | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
</head>
<body>
    <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px;">
        <h1>Fix Groups Table Structure</h1>
        
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
            <a href="manage_groups.php" class="btn btn-primary">Return to Manage Groups</a>
            <a href="fix_tables.php" class="btn">Run Full Table Repair</a>
        </p>
    </div>
</body>
</html>