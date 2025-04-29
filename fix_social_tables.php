<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin can run this
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Create tables with proper foreign key checks
$db->query("SET FOREIGN_KEY_CHECKS = 0");

// Create social_shares table
$db->query("CREATE TABLE IF NOT EXISTS social_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    newsletter_id INT NOT NULL,
    platform VARCHAR(50) NOT NULL,
    share_count INT DEFAULT 0,
    click_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Create social_clicks table
$db->query("CREATE TABLE IF NOT EXISTS social_clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    share_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    referrer VARCHAR(255) NULL,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add foreign keys only if the referenced tables exist
$result = $db->query("SHOW TABLES LIKE 'newsletters'");
if ($result->num_rows > 0) {
    // First drop any existing foreign keys to prevent errors
    $db->query("ALTER TABLE social_shares DROP FOREIGN KEY IF EXISTS fk_social_newsletter");
    
    // Add the foreign key
    $db->query("ALTER TABLE social_shares 
                ADD CONSTRAINT fk_social_newsletter 
                FOREIGN KEY (newsletter_id) 
                REFERENCES newsletters(id) 
                ON DELETE CASCADE");
}

$result = $db->query("SHOW TABLES LIKE 'social_shares'");
if ($result->num_rows > 0) {
    // First drop any existing foreign keys to prevent errors
    $db->query("ALTER TABLE social_clicks DROP FOREIGN KEY IF EXISTS fk_clicks_share");
    
    // Add the foreign key
    $db->query("ALTER TABLE social_clicks 
                ADD CONSTRAINT fk_clicks_share 
                FOREIGN KEY (share_id) 
                REFERENCES social_shares(id) 
                ON DELETE CASCADE");
}

$db->query("SET FOREIGN_KEY_CHECKS = 1");

// Make sure social sharing setting exists
$result = $db->query("SELECT COUNT(*) as count FROM settings WHERE name = 'social_sharing_enabled'");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $db->query("INSERT INTO settings (name, value) VALUES ('social_sharing_enabled', '1')");
}

echo "<p>Social sharing tables have been created and configured successfully!</p>";
echo "<p><a href='admin.php'>Return to admin panel</a></p>";
?>