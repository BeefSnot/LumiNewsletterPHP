<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access this utility
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$messages = [];
$errors = [];

// Helper functions
function tableExists($db, $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function columnExists($db, $table, $column) {
    if (!tableExists($db, $table)) return false;
    $result = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function addColumnIfNotExists($db, $table, $column, $definition) {
    if (!columnExists($db, $table, $column)) {
        try {
            $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            return "Added column '$column' to table '$table'";
        } catch (Exception $e) {
            return "Error adding column '$column' to '$table': " . $e->getMessage();
        }
    }
    return null;
}

function renameColumnIfExists($db, $table, $oldColumn, $newColumn, $definition) {
    if (columnExists($db, $table, $oldColumn) && !columnExists($db, $table, $newColumn)) {
        try {
            $db->query("ALTER TABLE `$table` CHANGE `$oldColumn` `$newColumn` $definition");
            return "Renamed column '$oldColumn' to '$newColumn' in table '$table'";
        } catch (Exception $e) {
            return "Error renaming column '$oldColumn' to '$newColumn' in '$table': " . $e->getMessage();
        }
    }
    return null;
}

function createTableIfNotExists($db, $tableName, $createStatement) {
    if (!tableExists($db, $tableName)) {
        try {
            $db->query($createStatement);
            return "Created table '$tableName'";
        } catch (Exception $e) {
            return "Error creating table '$tableName': " . $e->getMessage();
        }
    }
    return null;
}

// Start fixing database schema
$messages[] = "Starting database schema repair...";

// Define all required tables with their create statements
$requiredTables = [
    'users' => "CREATE TABLE `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `role` ENUM('admin','editor','user') NOT NULL DEFAULT 'user',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'groups' => "CREATE TABLE `groups` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'group_subscriptions' => "CREATE TABLE `group_subscriptions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL,
        `name` VARCHAR(255) NULL,
        `group_id` INT NOT NULL,
        `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `email_group` (`email`, `group_id`)
    )",
    'newsletters' => "CREATE TABLE `newsletters` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `subject` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `body` TEXT NULL,
        `creator_id` INT NULL,
        `sender_id` INT NULL,
        `theme_id` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `sent_at` TIMESTAMP NULL DEFAULT NULL,
        `is_ab_test` TINYINT(1) DEFAULT 0,
        `ab_test_id` INT NULL,
        `variant` CHAR(1) NULL
    )",
    'newsletter_groups' => "CREATE TABLE `newsletter_groups` (
        `newsletter_id` INT NOT NULL,
        `group_id` INT NOT NULL,
        PRIMARY KEY (`newsletter_id`, `group_id`)
    )",
    'themes' => "CREATE TABLE `themes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'settings' => "CREATE TABLE `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `value` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `name` (`name`)
    )",
    'email_opens' => "CREATE TABLE `email_opens` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `newsletter_id` INT NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `user_agent` VARCHAR(255),
        `ip_address` VARCHAR(45)
    )",
    'link_clicks' => "CREATE TABLE `link_clicks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `newsletter_id` INT NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `original_url` TEXT NOT NULL,
        `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `user_agent` VARCHAR(255),
        `ip_address` VARCHAR(45)
    )",
    'email_geo_data' => "CREATE TABLE `email_geo_data` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `open_id` INT,
        `click_id` INT,
        `country` VARCHAR(100),
        `region` VARCHAR(100),
        `city` VARCHAR(100),
        `latitude` DECIMAL(10,8),
        `longitude` DECIMAL(11,8),
        `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'email_devices' => "CREATE TABLE `email_devices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `open_id` INT,
        `device_type` VARCHAR(50),
        `browser` VARCHAR(50),
        `os` VARCHAR(50)
    )",
    'personalization_tags' => "CREATE TABLE `personalization_tags` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tag_name` VARCHAR(100) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `replacement_type` ENUM('field', 'function') NOT NULL,
        `field_name` VARCHAR(100) NOT NULL,
        `example` VARCHAR(255) NOT NULL,
        UNIQUE KEY `tag_name` (`tag_name`)
    )",
    'content_blocks' => "CREATE TABLE `content_blocks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `type` ENUM('static', 'dynamic', 'conditional') NOT NULL DEFAULT 'static',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'ab_tests' => "CREATE TABLE `ab_tests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `group_id` INT NOT NULL,
        `subject_a` VARCHAR(255) NOT NULL,
        `subject_b` VARCHAR(255) NOT NULL,
        `content_a` TEXT NOT NULL,
        `content_b` TEXT NOT NULL,
        `split_percentage` INT NOT NULL DEFAULT 50,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `sent_at` TIMESTAMP NULL
    )",
    'privacy_settings' => "CREATE TABLE `privacy_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'subscriber_consent' => "CREATE TABLE `subscriber_consent` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL,
        `tracking_consent` BOOLEAN DEFAULT FALSE,
        `geo_analytics_consent` BOOLEAN DEFAULT FALSE,
        `profile_analytics_consent` BOOLEAN DEFAULT FALSE,
        `consent_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `ip_address` VARCHAR(45),
        `consent_record` TEXT,
        UNIQUE KEY (`email`)
    )",
    'subscriber_segments' => "CREATE TABLE `subscriber_segments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `criteria` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'segment_subscribers' => "CREATE TABLE `segment_subscribers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `segment_id` INT NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (`segment_id`, `email`)
    )",
    'api_keys' => "CREATE TABLE `api_keys` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `api_key` VARCHAR(64) NOT NULL,
        `api_secret` VARCHAR(128) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        `last_used` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (`api_key`)
    )",
    'api_requests' => "CREATE TABLE `api_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `api_key_id` INT NOT NULL,
        `endpoint` VARCHAR(100) NOT NULL,
        `method` VARCHAR(10) NOT NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `status_code` INT NOT NULL,
        `request_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'social_shares' => "CREATE TABLE `social_shares` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `newsletter_id` INT NOT NULL,
        `platform` VARCHAR(50) NOT NULL,
        `share_count` INT DEFAULT 0,
        `click_count` INT DEFAULT 0,
        `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'social_clicks' => "CREATE TABLE `social_clicks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `share_id` INT NOT NULL,
        `ip_address` VARCHAR(45) NULL,
        `referrer` VARCHAR(255) NULL,
        `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'automation_workflows' => "CREATE TABLE `automation_workflows` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `trigger_type` ENUM('subscription', 'date', 'tag_added', 'segment_join', 'inactivity', 'custom') NOT NULL,
        `trigger_data` JSON,
        `status` ENUM('active', 'draft', 'paused') DEFAULT 'draft',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'automation_steps' => "CREATE TABLE `automation_steps` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `workflow_id` INT NOT NULL,
        `step_type` ENUM('email', 'delay', 'condition', 'tag', 'split') NOT NULL,
        `step_data` JSON,
        `position` INT NOT NULL
    )",
    'automation_logs' => "CREATE TABLE `automation_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `workflow_id` INT NOT NULL,
        `subscriber_email` VARCHAR(255) NOT NULL,
        `step_id` INT NOT NULL,
        `status` ENUM('pending', 'completed', 'failed', 'skipped') NOT NULL,
        `processed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'features' => "CREATE TABLE IF NOT EXISTS features (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feature_name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        added_version VARCHAR(20) NOT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

// Create all missing tables
foreach ($requiredTables as $table => $createStatement) {
    $result = createTableIfNotExists($db, $table, $createStatement);
    if ($result !== null) {
        $messages[] = $result;
    }
}

// Critical column fixes - These are the known problematic columns
// Fix the newsletters table body/content inconsistency
if (tableExists($db, 'newsletters')) {
    
    // Handle the body/content confusion in newsletters table
    if (columnExists($db, 'newsletters', 'content') && !columnExists($db, 'newsletters', 'body')) {
        // Add body column that mirrors content (for backwards compatibility)
        $result = $db->query("ALTER TABLE newsletters ADD COLUMN body TEXT NULL");
        if ($result) {
            $messages[] = "Added 'body' column to newsletters table";
            // Copy content values to body
            $result = $db->query("UPDATE newsletters SET body = content WHERE body IS NULL");
            if ($result) {
                $messages[] = "Copied content values to body column";
            }
        }
    } 
    else if (columnExists($db, 'newsletters', 'body') && !columnExists($db, 'newsletters', 'content')) {
        // Add content column that mirrors body
        $result = $db->query("ALTER TABLE newsletters ADD COLUMN content TEXT NULL");
        if ($result) {
            $messages[] = "Added 'content' column to newsletters table";
            // Copy body values to content
            $result = $db->query("UPDATE newsletters SET content = body WHERE content IS NULL");
            if ($result) {
                $messages[] = "Copied body values to content column";
            }
        }
    }
    
    // Check for both sender_id and creator_id
    if (columnExists($db, 'newsletters', 'sender_id') && !columnExists($db, 'newsletters', 'creator_id')) {
        $result = $db->query("ALTER TABLE newsletters ADD COLUMN creator_id INT NULL");
        if ($result) {
            $messages[] = "Added 'creator_id' column to newsletters table";
            $result = $db->query("UPDATE newsletters SET creator_id = sender_id WHERE creator_id IS NULL");
            if ($result) {
                $messages[] = "Copied sender_id values to creator_id";
            }
        }
    }
    else if (columnExists($db, 'newsletters', 'creator_id') && !columnExists($db, 'newsletters', 'sender_id')) {
        $result = $db->query("ALTER TABLE newsletters ADD COLUMN sender_id INT NULL");
        if ($result) {
            $messages[] = "Added 'sender_id' column to newsletters table";
            $result = $db->query("UPDATE newsletters SET sender_id = creator_id WHERE sender_id IS NULL");
            if ($result) {
                $messages[] = "Copied creator_id values to sender_id";
            }
        }
    }
    
    // Make sure sent_at exists
    if (!columnExists($db, 'newsletters', 'sent_at')) {
        $result = $db->query("ALTER TABLE newsletters ADD COLUMN sent_at TIMESTAMP NULL DEFAULT NULL");
        if ($result) {
            $messages[] = "Added 'sent_at' column to newsletters table";
            // Set sent_at = created_at for historical records
            $result = $db->query("UPDATE newsletters SET sent_at = created_at WHERE sent_at IS NULL");
            if ($result) {
                $messages[] = "Set default sent_at values based on created_at";
            }
        }
    }
    
    // Make sure created_at exists
    if (!columnExists($db, 'newsletters', 'created_at')) {
        $result = $db->query("ALTER TABLE newsletters ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        if ($result) {
            $messages[] = "Added 'created_at' column to newsletters table";
        }
    }
    
    // Add AB testing columns if they don't exist
    $abColumns = [
        'is_ab_test' => 'TINYINT(1) DEFAULT 0',
        'ab_test_id' => 'INT NULL',
        'variant' => 'CHAR(1) NULL'
    ];
    
    foreach ($abColumns as $column => $definition) {
        $msg = addColumnIfNotExists($db, 'newsletters', $column, $definition);
        if ($msg !== null) $messages[] = $msg;
    }
}

// Fix group_subscriptions table
if (tableExists($db, 'group_subscriptions')) {
    // Make sure name column exists
    if (!columnExists($db, 'group_subscriptions', 'name')) {
        $result = $db->query("ALTER TABLE group_subscriptions ADD COLUMN name VARCHAR(255) NULL AFTER email");
        if ($result) {
            $messages[] = "Added 'name' column to group_subscriptions table";
        }
    }
    
    // Make sure subscribed_at column exists
    if (!columnExists($db, 'group_subscriptions', 'subscribed_at')) {
        $result = $db->query("ALTER TABLE group_subscriptions ADD COLUMN subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        if ($result) {
            $messages[] = "Added 'subscribed_at' column to group_subscriptions table";
        }
    }
}

// Fix groups table
if (tableExists($db, 'groups')) {
    // Make sure description column exists
    if (!columnExists($db, 'groups', 'description')) {
        $result = $db->query("ALTER TABLE groups ADD COLUMN description TEXT NULL AFTER name");
        if ($result) {
            $messages[] = "Added 'description' column to groups table";
        }
    }
    
    // Make sure created_at column exists
    if (!columnExists($db, 'groups', 'created_at')) {
        $result = $db->query("ALTER TABLE groups ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        if ($result) {
            $messages[] = "Added 'created_at' column to groups table";
        }
    }
}

// Add default personalization tags if none exist
$result = $db->query("SELECT COUNT(*) as count FROM personalization_tags");
if ($result && $result->fetch_assoc()['count'] == 0) {
    $defaultTags = [
        ['first_name', 'Subscriber\'s first name', 'field', 'name', 'John'],
        ['last_name', 'Subscriber\'s last name', 'field', 'name', 'Doe'],
        ['email', 'Subscriber\'s email address', 'field', 'email', 'subscriber@example.com'],
        ['subscription_date', 'When the subscriber joined', 'field', 'created_at', 'January 15, 2023'],
        ['current_date', 'Today\'s date', 'function', 'date', date('F j, Y')],
        ['unsubscribe_link', 'Link to unsubscribe from newsletter', 'function', 'unsubscribe_url', 'https://example.com/unsubscribe']
    ];
    
    $stmt = $db->prepare("INSERT INTO personalization_tags (tag_name, description, replacement_type, field_name, example) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($defaultTags as $tag) {
        $stmt->bind_param("sssss", $tag[0], $tag[1], $tag[2], $tag[3], $tag[4]);
        $stmt->execute();
    }
    
    $messages[] = "Added default personalization tags";
}

// Add default privacy settings if none exist
$result = $db->query("SELECT COUNT(*) as count FROM privacy_settings");
if ($result && $result->fetch_assoc()['count'] == 0) {
    $defaultPrivacySettings = [
        ['privacy_policy', ''],
        ['enable_tracking', '1'],
        ['enable_geo_analytics', '1'],
        ['require_explicit_consent', '1'],
        ['data_retention_period', '12'],
        ['anonymize_ip', '0'],
        ['cookie_notice', '1'],
        ['cookie_notice_text', 'We use cookies to improve your experience and analyze website traffic.'],
        ['consent_prompt_text', 'I consent to receiving newsletters and agree that my email engagement may be tracked for analytics purposes.']
    ];
    
    $stmt = $db->prepare("INSERT INTO privacy_settings (setting_key, setting_value) VALUES (?, ?)");
    
    foreach ($defaultPrivacySettings as $setting) {
        $stmt->bind_param("ss", $setting[0], $setting[1]);
        $stmt->execute();
    }
    
    $messages[] = "Added default privacy settings";
}

// Verify foreign key constraints
$keyRelationships = [
    ['newsletter_groups', 'newsletter_id', 'newsletters', 'id'],
    ['newsletter_groups', 'group_id', 'groups', 'id'],
    ['email_opens', 'newsletter_id', 'newsletters', 'id'],
    ['link_clicks', 'newsletter_id', 'newsletters', 'id'],
    ['email_geo_data', 'open_id', 'email_opens', 'id'],
    ['email_geo_data', 'click_id', 'link_clicks', 'id'],
    ['email_devices', 'open_id', 'email_opens', 'id'],
    ['group_subscriptions', 'group_id', 'groups', 'id']
];

foreach ($keyRelationships as $rel) {
    list($table, $column, $refTable, $refColumn) = $rel;
    
    if (tableExists($db, $table) && tableExists($db, $refTable) && 
        columnExists($db, $table, $column) && columnExists($db, $refTable, $refColumn)) {
        
        // Check if the constraint already exists
        $result = $db->query("SELECT CONSTRAINT_NAME
                             FROM information_schema.KEY_COLUMN_USAGE
                             WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = '$table'
                             AND COLUMN_NAME = '$column'
                             AND REFERENCED_TABLE_NAME = '$refTable'");
                             
        if ($result && $result->num_rows === 0) {
            // Constraint doesn't exist - create it
            try {
                $constraintName = "fk_{$table}_{$column}";
                $db->query("ALTER TABLE `$table` 
                           ADD CONSTRAINT `$constraintName` 
                           FOREIGN KEY (`$column`) REFERENCES `$refTable`(`$refColumn`) 
                           ON DELETE CASCADE");
                $messages[] = "Added foreign key constraint between $table.$column and $refTable.$refColumn";
            } catch (Exception $e) {
                $errors[] = "Failed to add foreign key: " . $e->getMessage();
            }
        }
    }
}

// Set up default trigger for the send_newsletter.php script
$triggerMessage = "";
if (tableExists($db, 'newsletters')) {
    // Check for both body and content fields and create trigger
    if (columnExists($db, 'newsletters', 'body') && columnExists($db, 'newsletters', 'content')) {
        // Drop existing triggers if any
        $db->query("DROP TRIGGER IF EXISTS before_newsletter_insert");
        $db->query("DROP TRIGGER IF EXISTS before_newsletter_update");
        
        // Create triggers to keep body and content synchronized
        $db->query("CREATE TRIGGER before_newsletter_insert
                   BEFORE INSERT ON newsletters
                   FOR EACH ROW
                   BEGIN
                       IF NEW.body IS NULL AND NEW.content IS NOT NULL THEN
                           SET NEW.body = NEW.content;
                       ELSEIF NEW.content IS NULL AND NEW.body IS NOT NULL THEN
                           SET NEW.content = NEW.body;
                       END IF;
                   END");
                   
        $db->query("CREATE TRIGGER before_newsletter_update
                   BEFORE UPDATE ON newsletters
                   FOR EACH ROW
                   BEGIN
                       IF NEW.body != OLD.body THEN
                           SET NEW.content = NEW.body;
                       ELSEIF NEW.content != OLD.content THEN
                           SET NEW.body = NEW.content;
                       END IF;
                   END");
        
        $triggerMessage = "Created triggers to synchronize 'body' and 'content' columns";
        $messages[] = $triggerMessage;
    }
}

// Create indexes for performance
$indexes = [
    ['group_subscriptions', 'email', 'email'],
    ['newsletters', 'created_at', 'created_at'],
    ['newsletters', 'sent_at', 'sent_at'],
    ['email_opens', 'newsletter_email', ['newsletter_id', 'email']],
    ['link_clicks', 'newsletter_email', ['newsletter_id', 'email']],
];

foreach ($indexes as $index) {
    if (count($index) === 3) {
        [$table, $indexName, $column] = $index;
        $columnStr = is_array($column) ? implode('`, `', $column) : $column;
        
        if (tableExists($db, $table)) {
            try {
                $db->query("CREATE INDEX `$indexName` ON `$table` (`$columnStr`)");
                $messages[] = "Added index $indexName to $table";
            } catch (Exception $e) {
                // Index might already exist or other error
                if (strpos($e->getMessage(), 'Duplicate key') === false) {
                    $errors[] = "Failed to create index $indexName: " . $e->getMessage();
                }
            }
        }
    }
}

// Update all sender_id to creator_id in code that uses sender_id
$sendNewsletterUpdated = false;
$sendNewsletterPath = __DIR__ . '/send_newsletter.php';
if (file_exists($sendNewsletterPath)) {
    $content = file_get_contents($sendNewsletterPath);
    if (strpos($content, 'sender_id') !== false) {
        $updatedContent = str_replace('sender_id', 'creator_id', $content);
        if (file_put_contents($sendNewsletterPath, $updatedContent) !== false) {
            $messages[] = "Updated send_newsletter.php to use creator_id instead of sender_id";
            $sendNewsletterUpdated = true;
        }
    }
}

// Loop through all tables and create any that are missing
foreach ($requiredTables as $tableName => $createSQL) {
    $checkTable = $db->query("SHOW TABLES LIKE '$tableName'");
    
    if ($checkTable->num_rows === 0) {
        // Table doesn't exist, so create it
        if ($db->query($createSQL)) {
            $messages[] = "Created missing table: $tableName";
            
            // Add default data for certain tables
            if ($tableName == 'features') {
                $insertResult = $db->query("INSERT INTO features (feature_name, description, enabled, added_version) VALUES 
                    ('ai_assistant', 'AI-powered content generation and suggestions', 1, '1.5631335'),
                    ('email_scheduler', 'Schedule newsletters to be sent automatically', 1, '1.5631335'),
                    ('analytics_dashboard', 'View detailed statistics about newsletter performance', 1, '1.5631335')
                ");
                
                if ($insertResult) {
                    $messages[] = "Added default features to the features table";
                } else {
                    $errors[] = "Failed to add default features: " . $db->error;
                }
            }
        } else {
            $errors[] = "Failed to create table $tableName: " . $db->error;
        }
    } else {
        $messages[] = "Table $tableName already exists.";
    }
}

$queries = [
    "CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        content LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        is_system BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS media_library (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50),
        uploaded_by INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($queries as $query) {
    if (!$db->query($query)) {
        $errors[] = $db->error;
    }
}

if (empty($errors)) {
    echo "All missing tables have been repaired successfully.";
} else {
    echo "Errors occurred while repairing tables: " . implode(", ", $errors);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Schema Repair | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        .results-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .message-list {
            max-height: 400px;
            overflow-y: auto;
            margin: 15px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .message-list li {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
            list-style-type: disc;
            margin-left: 20px;
        }
        .success-count {
            background: #e8f5e9;
            color: #388e3c;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .error-count {
            background: #ffebee;
            color: #d32f2f;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .key-fix {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Database Schema Repair</h1>
                </div>
            </header>
            
            <div class="results-card">
                <h2><i class="fas fa-database"></i> Schema Repair Results</h2>
                
                <?php if (count($messages) > 0): ?>
                    <div class="success-count">
                        <i class="fas fa-check-circle"></i> 
                        <?php echo count($messages); ?> fixes/updates applied
                    </div>
                    
                    <h3>Changes Made:</h3>
                    <ul class="message-list">
                        <?php foreach ($messages as $message): ?>
                            <li><?php echo htmlspecialchars($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="success-count">
                        <i class="fas fa-check-circle"></i> No changes needed - your database schema is up to date.
                    </div>
                <?php endif; ?>
                
                <?php if (count($errors) > 0): ?>
                    <div class="error-count">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <?php echo count($errors); ?> errors encountered
                    </div>
                    
                    <h3>Errors:</h3>
                    <ul class="message-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (isset($triggerMessage) && !empty($triggerMessage)): ?>
                <div class="key-fix">
                    <h4><i class="fas fa-sync-alt"></i> Auto-Synchronization Setup</h4>
                    <p>Database triggers have been created to automatically keep the <code>body</code> and <code>content</code> columns in sync.</p>
                    <p>This ensures compatibility with all versions of the code regardless of which column name is used.</p>
                </div>
                <?php endif; ?>
                
                <?php if ($sendNewsletterUpdated): ?>
                <div class="key-fix">
                    <h4><i class="fas fa-file-code"></i> Code Updates</h4>
                    <p>Updated <code>send_newsletter.php</code> to use <code>creator_id</code> instead of <code>sender_id</code> for consistency.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <a href="admin.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Return to Admin
                </a>
                
                <a href="fix_tables.php" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Run Repair Again
                </a>
            </div>
        </main>
    </div>
</body>
</html>