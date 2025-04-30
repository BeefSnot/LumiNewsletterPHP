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

// Add this to the beginning of the script, right after the initial setup

// Check specifically for social tables and create them properly
echo "<h3>Checking social sharing tables</h3>";

// Create social_shares table
$checkSocialShares = $db->query("SHOW TABLES LIKE 'social_shares'");
if ($checkSocialShares->num_rows === 0) {
    echo "Creating social_shares table...<br>";
    $socialSharesResult = $db->query("CREATE TABLE IF NOT EXISTS social_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NOT NULL,
        platform VARCHAR(50) NOT NULL,
        share_count INT DEFAULT 0,
        click_count INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    if ($socialSharesResult) {
        echo "<span style='color:green'>✓ Successfully created social_shares table</span><br>";
    } else {
        echo "<span style='color:red'>✗ Failed to create social_shares table: " . $db->error . "</span><br>";
    }
}

// Create social_clicks table
$checkSocialClicks = $db->query("SHOW TABLES LIKE 'social_clicks'");
if ($checkSocialClicks->num_rows === 0) {
    echo "Creating social_clicks table...<br>";
    $socialClicksResult = $db->query("CREATE TABLE IF NOT EXISTS social_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        share_id INT NOT NULL,
        ip_address VARCHAR(45) NULL,
        referrer VARCHAR(255) NULL,
        clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    if ($socialClicksResult) {
        echo "<span style='color:green'>✓ Successfully created social_clicks table</span><br>";
    } else {
        echo "<span style='color:red'>✗ Failed to create social_clicks table: " . $db->error . "</span><br>";
    }
}

// Ensure social_sharing_enabled setting exists
$checkSocialSetting = $db->query("SELECT COUNT(*) as count FROM settings WHERE name = 'social_sharing_enabled'");
$socialSettingRow = $checkSocialSetting->fetch_assoc();
if ($socialSettingRow['count'] == 0) {
    $db->query("INSERT INTO settings (name, value) VALUES ('social_sharing_enabled', '1')");
    echo "<span style='color:green'>✓ Added social_sharing_enabled setting</span><br>";
}

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
    )",
    'automation_workflows' => "CREATE TABLE IF NOT EXISTS automation_workflows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        trigger_type ENUM('subscription', 'date', 'tag_added', 'segment_join', 'inactivity', 'custom') NOT NULL,
        trigger_data JSON,
        status ENUM('active', 'draft', 'paused') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'automation_steps' => "CREATE TABLE IF NOT EXISTS automation_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        workflow_id INT NOT NULL,
        step_type ENUM('email', 'delay', 'condition', 'tag', 'split') NOT NULL,
        step_data JSON,
        position INT NOT NULL,
        FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE
    )",
    'automation_logs' => "CREATE TABLE IF NOT EXISTS automation_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        workflow_id INT NOT NULL,
        subscriber_email VARCHAR(255) NOT NULL,
        step_id INT NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'skipped') NOT NULL,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE,
        FOREIGN KEY (step_id) REFERENCES automation_steps(id) ON DELETE CASCADE
    )",
    'content_blocks' => "CREATE TABLE IF NOT EXISTS content_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        content LONGTEXT,
        type ENUM('static', 'dynamic', 'conditional') NOT NULL DEFAULT 'static',
        conditions TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'personalization_tags' => "CREATE TABLE IF NOT EXISTS personalization_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tag_name VARCHAR(255) NOT NULL,
        replacement_type ENUM('field', 'function', 'api') NOT NULL DEFAULT 'field',
        field_name VARCHAR(255) NULL,
        function_name VARCHAR(255) NULL,
        api_endpoint TEXT NULL,
        description TEXT,
        example VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'privacy_settings' => "CREATE TABLE IF NOT EXISTS privacy_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    'subscriber_consent' => "CREATE TABLE IF NOT EXISTS subscriber_consent (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        tracking_consent BOOLEAN DEFAULT FALSE,
        geo_analytics_consent BOOLEAN DEFAULT FALSE,
        profile_analytics_consent BOOLEAN DEFAULT FALSE,
        consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        consent_record TEXT,
        UNIQUE KEY (email)
    )",
    'segment_subscribers' => "CREATE TABLE IF NOT EXISTS segment_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        segment_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (segment_id, email),
        FOREIGN KEY (segment_id) REFERENCES subscriber_segments(id) ON DELETE CASCADE
    )",
    'api_keys' => "CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        api_key VARCHAR(64) NOT NULL,
        api_secret VARCHAR(128) NOT NULL,
        name VARCHAR(100) NOT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        last_used TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (api_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    'api_requests' => "CREATE TABLE IF NOT EXISTS api_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        api_key_id INT NOT NULL,
        endpoint VARCHAR(100) NOT NULL,
        method VARCHAR(10) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        status_code INT NOT NULL,
        request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
    )",
    'social_shares' => "CREATE TABLE IF NOT EXISTS social_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NOT NULL,
        platform VARCHAR(50) NOT NULL,
        share_count INT DEFAULT 0,
        click_count INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE
    )",
    'social_clicks' => "CREATE TABLE IF NOT EXISTS social_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        share_id INT NOT NULL,
        ip_address VARCHAR(45) NULL,
        referrer VARCHAR(255) NULL,
        clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (share_id) REFERENCES social_shares(id) ON DELETE CASCADE
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

// Check for and add theme columns to ab_tests table

// Add this after checking for other columns
$result = $db->query("SHOW COLUMNS FROM ab_tests LIKE 'theme_a_id'");
if ($result->num_rows === 0) {
    echo "<h3>Adding theme columns to ab_tests table</h3>";
    if ($db->query("ALTER TABLE ab_tests 
        ADD COLUMN theme_a_id INT NULL,
        ADD COLUMN theme_b_id INT NULL")) {
        echo "<span style='color:green'>✓ Successfully added theme columns</span><br>";
    } else {
        echo "<span style='color:red'>✗ Failed to add theme columns: " . $db->error . "</span><br>";
    }
}

// Check if newsletters table has created_at column
$checkCreatedAt = $db->query("SHOW COLUMNS FROM newsletters LIKE 'created_at'");
if ($checkCreatedAt->num_rows === 0) {
    // Add created_at column if it doesn't exist
    $db->query("ALTER TABLE newsletters ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "<span style='color:green'>✓ Added created_at column to newsletters table</span><br>";
}

// Add this check after your other table checks:

// Check if group_subscriptions table has created_at column
$checkSubCreatedAt = $db->query("SHOW COLUMNS FROM group_subscriptions LIKE 'created_at'");
if ($checkSubCreatedAt->num_rows === 0) {
    // Add created_at column if it doesn't exist
    $db->query("ALTER TABLE group_subscriptions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "<span style='color:green'>✓ Added created_at column to group_subscriptions table</span><br>";
}

// Check if group_subscriptions table has first_name and last_name columns
$checkFirstName = $db->query("SHOW COLUMNS FROM group_subscriptions LIKE 'first_name'");
if ($checkFirstName->num_rows === 0) {
    // Add first_name column if it doesn't exist
    $db->query("ALTER TABLE group_subscriptions ADD COLUMN first_name VARCHAR(100) NULL AFTER email");
    echo "<span style='color:green'>✓ Added first_name column to group_subscriptions table</span><br>";
}

$checkLastName = $db->query("SHOW COLUMNS FROM group_subscriptions LIKE 'last_name'");
if ($checkLastName->num_rows === 0) {
    // Add last_name column if it doesn't exist
    $db->query("ALTER TABLE group_subscriptions ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name");
    echo "<span style='color:green'>✓ Added last_name column to group_subscriptions table</span><br>";
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Database Tables | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-status {
            margin-bottom: 20px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            background: white;
        }
        .table-status h3 {
            padding: 15px;
            margin: 0;
            background-color: var(--primary-light);
            color: var(--primary);
            font-size: 1rem;
            display: flex;
            align-items: center;
        }
        .table-status h3 i {
            margin-right: 10px;
        }
        .status-content {
            padding: 15px;
        }
        .status-item {
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        .status-item i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        .status-success {
            background-color: rgba(52, 168, 83, 0.1);
            color: #34a853;
        }
        .status-warning {
            background-color: rgba(251, 188, 4, 0.1);
            color: #fbbc04;
        }
        .status-error {
            background-color: rgba(234, 67, 53, 0.1);
            color: #ea4335;
        }
        .status-info {
            background-color: rgba(66, 133, 244, 0.1);
            color: #4285f4;
        }
        .result-summary {
            margin-top: 30px;
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .result-summary h2 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        .action-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-paper-plane"></i>
                    <h2>LumiNews</h2>
                </div>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="admin.php" class="nav-item"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="ab_testing.php" class="nav-item"><i class="fas fa-flask"></i> A/B Testing</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="segments.php" class="nav-item"><i class="fas fa-tags"></i> Segments</a></li>
                    <li><a href="privacy_settings.php" class="nav-item"><i class="fas fa-shield-alt"></i> Privacy</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Fix Database Tables</h1>
                </div>
            </header>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-database"></i> Database Tables Repair Tool</h2>
                </div>
                <div class="card-body">
                    <p>This tool checks and creates any missing tables required for LumiNewsletter to function properly.</p>
                    
                    <div class="result-summary">
                        <h2><i class="fas fa-check-circle"></i> Database Schema Check Completed</h2>
                        <p>All required tables have been checked and repaired if needed.</p>
                        
                        <div class="action-buttons">
                            <a href="analytics.php" class="btn"><i class="fas fa-chart-bar"></i> Analytics</a>
                            <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>