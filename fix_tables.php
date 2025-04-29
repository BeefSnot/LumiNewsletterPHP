<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';

echo "<h2>LumiNewsletter Database Tables Fix</h2>";
echo "<p>This script will check and create any missing tables required for analytics and A/B testing.</p>";

// Temporarily disable foreign key checks to avoid ordering issues
$db->query("SET FOREIGN_KEY_CHECKS = 0");

// Define tables that need to be created
$requiredTables = [
    'email_opens' => "CREATE TABLE IF NOT EXISTS email_opens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_agent VARCHAR(255),
        ip_address VARCHAR(45)
    )",
    'link_clicks' => "CREATE TABLE IF NOT EXISTS link_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        original_url TEXT NOT NULL,
        clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_agent VARCHAR(255),
        ip_address VARCHAR(45)
    )",
    'email_geo_data' => "CREATE TABLE IF NOT EXISTS email_geo_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        open_id INT,
        click_id INT,
        country VARCHAR(100),
        region VARCHAR(100),
        city VARCHAR(100),
        latitude DECIMAL(10,8),
        longitude DECIMAL(11,8),
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'email_devices' => "CREATE TABLE IF NOT EXISTS email_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        open_id INT,
        device_type VARCHAR(50),
        browser VARCHAR(50),
        os VARCHAR(50)
    )",
    'ab_tests' => "CREATE TABLE IF NOT EXISTS ab_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        group_id INT NOT NULL,
        subject_a VARCHAR(255) NOT NULL,
        subject_b VARCHAR(255) NOT NULL,
        content_a TEXT NOT NULL,
        content_b TEXT NOT NULL,
        split_percentage INT NOT NULL DEFAULT 50,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL
    )",
    'subscriber_scores' => "CREATE TABLE IF NOT EXISTS subscriber_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        engagement_score FLOAT DEFAULT 0,
        last_open_date TIMESTAMP NULL,
        last_click_date TIMESTAMP NULL, 
        total_opens INT DEFAULT 0,
        total_clicks INT DEFAULT 0,
        last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (email)
    )",
    'subscriber_tags' => "CREATE TABLE IF NOT EXISTS subscriber_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        tag VARCHAR(100) NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (email, tag)
    )",
    'subscriber_segments' => "CREATE TABLE IF NOT EXISTS subscriber_segments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        criteria TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

// Check for each required table and create it if needed
foreach ($requiredTables as $table => $createSql) {
    echo "<h3>Checking table: $table</h3>";
    
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        // Table doesn't exist, create it
        echo "Table $table does not exist. Creating...<br>";
        if ($db->query($createSql)) {
            echo "<span style='color:green'>✓ Successfully created table $table</span><br>";
        } else {
            echo "<span style='color:red'>✗ Failed to create table $table: " . $db->error . "</span><br>";
        }
    } else {
        echo "<span style='color:blue'>✓ Table $table already exists</span><br>";
    }
}

// Check if newsletters table needs the ab_test columns
$result = $db->query("SHOW COLUMNS FROM newsletters LIKE 'is_ab_test'");
if ($result->num_rows === 0) {
    echo "<h3>Adding A/B test columns to newsletters table</h3>";
    if ($db->query("ALTER TABLE newsletters 
        ADD COLUMN is_ab_test TINYINT(1) DEFAULT 0,
        ADD COLUMN ab_test_id INT NULL,
        ADD COLUMN variant CHAR(1) NULL")) {
        echo "<span style='color:green'>✓ Successfully added A/B test columns</span><br>";
    } else {
        echo "<span style='color:red'>✗ Failed to add A/B test columns: " . $db->error . "</span><br>";
    }
}

// After creating all tables, add foreign keys
$foreignKeys = [
    "ALTER TABLE email_opens ADD INDEX (newsletter_id), ADD CONSTRAINT fk_opens_newsletter FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE",
    "ALTER TABLE link_clicks ADD INDEX (newsletter_id), ADD CONSTRAINT fk_clicks_newsletter FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE",
    "ALTER TABLE email_geo_data ADD INDEX (open_id), ADD INDEX (click_id), ADD CONSTRAINT fk_geo_open FOREIGN KEY (open_id) REFERENCES email_opens(id) ON DELETE CASCADE, ADD CONSTRAINT fk_geo_click FOREIGN KEY (click_id) REFERENCES link_clicks(id) ON DELETE CASCADE",
    "ALTER TABLE email_devices ADD INDEX (open_id), ADD CONSTRAINT fk_devices_open FOREIGN KEY (open_id) REFERENCES email_opens(id) ON DELETE CASCADE",
    "ALTER TABLE newsletters ADD CONSTRAINT fk_ab_test_id FOREIGN KEY (ab_test_id) REFERENCES ab_tests(id) ON DELETE SET NULL",
    "ALTER TABLE ab_tests ADD CONSTRAINT fk_abtest_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE"
];

echo "<h3>Adding foreign keys</h3>";
foreach ($foreignKeys as $key) {
    // Attempt to add each foreign key, but don't worry if it fails (might already exist)
    try {
        $db->query($key);
        echo "<span style='color:green'>✓ Successfully added foreign key</span><br>";
    } catch (Exception $e) {
        echo "<span style='color:orange'>⚠ Could not add foreign key (may already exist): " . $e->getMessage() . "</span><br>";
    }
}

// Re-enable foreign key checks
$db->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<p><strong>Database schema check completed.</strong></p>";
echo "<p><a href='analytics.php' class='btn'>Return to Analytics</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}
h2 {
    color: #4285f4;
}
h3 {
    margin-top: 20px;
    border-top: 1px solid #eee;
    padding-top: 15px;
}
.btn {
    display: inline-block;
    background-color: #4285f4;
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    margin-top: 20px;
}
</style>