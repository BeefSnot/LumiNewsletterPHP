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

// Function to safely check if a column exists
function columnExists($db, $table, $column) {
    $result = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Function to add a column if it doesn't exist
function addColumnIfNotExists($db, $table, $column, $definition) {
    if (!columnExists($db, $table, $column)) {
        try {
            $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            return true;
        } catch (Exception $e) {
            global $errors;
            $errors[] = "Error adding column $column to $table: " . $e->getMessage();
            return false;
        }
    }
    return false;
}

// Check newsletters table for column inconsistencies
$senderIdExists = columnExists($db, 'newsletters', 'sender_id');
$creatorIdExists = columnExists($db, 'newsletters', 'creator_id');

// Fix the sender_id/creator_id inconsistency
if ($senderIdExists && !$creatorIdExists) {
    // Only sender_id exists, rename it to creator_id for all code that uses it
    try {
        $db->query("ALTER TABLE `newsletters` CHANGE `sender_id` `creator_id` INT");
        $messages[] = "Renamed 'sender_id' column to 'creator_id' in newsletters table";
    } catch (Exception $e) {
        $errors[] = "Error renaming column: " . $e->getMessage();
    }
} elseif (!$senderIdExists && $creatorIdExists) {
    // Only creator_id exists, add sender_id as an alias (view) or update code
    $messages[] = "The 'creator_id' column exists in newsletters table. The code should use creator_id instead of sender_id.";
} elseif (!$senderIdExists && !$creatorIdExists) {
    // Neither exists - add creator_id
    addColumnIfNotExists($db, 'newsletters', 'creator_id', 'INT, ADD FOREIGN KEY (creator_id) REFERENCES users(id)');
    $messages[] = "Added missing 'creator_id' column to newsletters table";
}

// Check for other required newsletter columns
addColumnIfNotExists($db, 'newsletters', 'subject', 'VARCHAR(255) NOT NULL');
addColumnIfNotExists($db, 'newsletters', 'content', 'TEXT NOT NULL');
addColumnIfNotExists($db, 'newsletters', 'theme_id', 'INT NULL');
addColumnIfNotExists($db, 'newsletters', 'sent_at', 'TIMESTAMP NULL DEFAULT NULL');
addColumnIfNotExists($db, 'newsletters', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
addColumnIfNotExists($db, 'newsletters', 'is_ab_test', 'TINYINT(1) DEFAULT 0');
addColumnIfNotExists($db, 'newsletters', 'ab_test_id', 'INT NULL');
addColumnIfNotExists($db, 'newsletters', 'variant', 'CHAR(1) NULL');

// Check and fix group_subscriptions table
addColumnIfNotExists($db, 'group_subscriptions', 'name', 'VARCHAR(255) NULL AFTER email');
addColumnIfNotExists($db, 'group_subscriptions', 'subscribed_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

// Check newsletter_groups table
$checkTable = $db->query("SHOW TABLES LIKE 'newsletter_groups'");
if ($checkTable->num_rows === 0) {
    try {
        $db->query("CREATE TABLE IF NOT EXISTS `newsletter_groups` (
            newsletter_id INT NOT NULL,
            group_id INT NOT NULL,
            PRIMARY KEY (newsletter_id, group_id),
            FOREIGN KEY (newsletter_id) REFERENCES newsletters(id),
            FOREIGN KEY (group_id) REFERENCES `groups`(id)
        )");
        $messages[] = "Created missing newsletter_groups table";
    } catch (Exception $e) {
        $errors[] = "Error creating newsletter_groups table: " . $e->getMessage();
    }
}

// Output results as HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Database Schema | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
</head>
<body>
    <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px;">
        <h1>Database Schema Repair Tool</h1>
        
        <?php if (!empty($messages)): ?>
            <div class="notification success">
                <h3>Changes Made:</h3>
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
            <a href="admin.php" class="btn btn-primary">Return to Admin Panel</a>
            <a href="fix_tables.php" class="btn">Run General Table Fix</a>
        </p>
    </div>
</body>
</html>