<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access system updates
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Start output buffering to prevent any output before headers
ob_start();

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Collect any database changes/messages here instead of echoing them
$dbUpdates = [];

// Check for API and social media tables silently
$checkAPIBefore = $db->query("SHOW TABLES LIKE 'api_keys'");
$checkSocialBefore = $db->query("SHOW TABLES LIKE 'social_shares'");

// Check for features table
$checkFeatures = $db->query("SHOW TABLES LIKE 'features'");
if ($checkFeatures->num_rows === 0) {
    // Create features table
    $db->query("CREATE TABLE IF NOT EXISTS features (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feature_name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        added_version VARCHAR(20) NOT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Add default features
    $db->query("INSERT INTO features (feature_name, description, enabled, added_version) VALUES 
        ('ai_assistant', 'AI-powered content generation and suggestions', 1, '1.5631335'),
        ('email_scheduler', 'Schedule newsletters to be sent automatically', 1, '1.5631335'),
        ('analytics_dashboard', 'View detailed statistics about newsletter performance', 1, '1.5631335')
    ");
    
    $dbUpdates[] = "Created features table with default features enabled";
}

// Check if newsletters table has created_at column
$checkCreatedAt = $db->query("SHOW COLUMNS FROM newsletters LIKE 'created_at'");
if ($checkCreatedAt->num_rows === 0) {
    // Add created_at column if it doesn't exist
    $db->query("ALTER TABLE newsletters ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $dbUpdates[] = "Added created_at column to newsletters table";
}

// Deal with social tables if they don't exist
if ($checkSocialBefore->num_rows === 0) {
    $db->query("CREATE TABLE IF NOT EXISTS social_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NOT NULL,
        platform VARCHAR(50) NOT NULL,
        share_count INT DEFAULT 0,
        click_count INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $db->query("CREATE TABLE IF NOT EXISTS social_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        share_id INT NOT NULL,
        ip_address VARCHAR(45) NULL,
        referrer VARCHAR(255) NULL,
        clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Ensure social_sharing_enabled setting exists
    $checkSocialSetting = $db->query("SELECT COUNT(*) as count FROM settings WHERE name = 'social_sharing_enabled'");
    $socialSettingRow = $checkSocialSetting->fetch_assoc();
    if ($socialSettingRow['count'] == 0) {
        $db->query("INSERT INTO settings (name, value) VALUES ('social_sharing_enabled', '1')");
    }
    
    $dbUpdates[] = "Created social media sharing tables";
}

// Clear buffer before any HTML output
ob_end_clean();

define('UPDATE_JSON_URL', 'https://lumihost.net/updates/latest_update.json');

$currentVersion = require 'version.php';
$message = '';
$messageType = '';
$updateAvailable = false;
$latestVersion = '';
$changelog = '';
$updateUrl = '';

// Start output buffering to prevent output before headers
ob_start();

// Add cache busting parameter and enable error output
$latestUpdateInfo = @file_get_contents(UPDATE_JSON_URL . '?nocache=' . time());
if ($latestUpdateInfo === false) {
    $message = 'Failed to fetch update information. Error: ' . error_get_last()['message'];
    $messageType = 'error';
} else {
    $latestUpdateInfo = json_decode($latestUpdateInfo, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $message = 'Failed to parse update JSON. Error: ' . json_last_error_msg();
        $messageType = 'error';
    } else {
        $latestVersion = $latestUpdateInfo['version'] ?? '';
        $changelog = $latestUpdateInfo['changelog'] ?? '';
        $updateUrl = $latestUpdateInfo['update_url'] ?? '';
        if ($latestVersion && version_compare($latestVersion, $currentVersion, '>')) {
            $updateAvailable = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $updateAvailable) {
    $updatePackage = @file_get_contents($updateUrl);
    if ($updatePackage === false) {
        $message = 'Failed to download the update package.';
        $messageType = 'error';
        error_log($message);
    } else {
        $tempFile = tempnam(sys_get_temp_dir(), 'update_') . '.zip';
        file_put_contents($tempFile, $updatePackage);
        
        // Create a temporary extraction directory
        $extractPath = sys_get_temp_dir() . '/lumi_update_' . time();
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $zip = new ZipArchive;
        $extractResult = $zip->open($tempFile);
        if ($extractResult === TRUE) {
            // First extract to temp directory
            $zip->extractTo($extractPath);
            $zip->close();
            
            // Check if there's a single directory in the extracted content
            $items = scandir($extractPath);
            $rootDir = null;
            foreach ($items as $item) {
                if ($item != '.' && $item != '..' && is_dir($extractPath . '/' . $item)) {
                    $rootDir = $extractPath . '/' . $item;
                    break;
                }
            }
            
            // If we found a single directory, copy its contents instead
            if ($rootDir !== null) {
                // Copy files from subfolder to current directory
                recursiveCopy($rootDir, __DIR__);
            } else {
                // No subfolder, copy all files directly
                recursiveCopy($extractPath, __DIR__);
            }
            
            // Check for database update script and run it
            $dbUpdateSuccess = true;
            if (file_exists($extractPath . '/db_updates.php')) {
                try {
                    include_once $extractPath . '/db_updates.php';
                    if (function_exists('apply_database_updates')) {
                        apply_database_updates($db, $currentVersion, $latestVersion);
                    }
                } catch (Exception $e) {
                    $dbUpdateSuccess = false;
                    $message = 'Database update error: ' . $e->getMessage();
                    $messageType = 'error';
                    error_log($message);
                }
            } else {
                // Look for SQL update files with version numbers
                $sqlUpdateFile = $extractPath . '/updates/update_' . str_replace('.', '_', $currentVersion) . '_to_' . str_replace('.', '_', $latestVersion) . '.sql';
                if (file_exists($sqlUpdateFile)) {
                    try {
                        $sqlQueries = file_get_contents($sqlUpdateFile);
                        $queries = explode(';', $sqlQueries);
                        
                        foreach ($queries as $query) {
                            $query = trim($query);
                            if (!empty($query)) {
                                if (!$db->query($query)) {
                                    throw new Exception($db->error);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $dbUpdateSuccess = false;
                        $message = 'SQL update error: ' . $e->getMessage();
                        $messageType = 'error';
                        error_log($message);
                    }
                } else {
                    // Fallback: Run database schema check
                    applyDatabaseSchemaChanges($db);
                }
            }
            
            // Clean up
            recursiveDelete($extractPath);
            
            if ($dbUpdateSuccess) {
                $message = 'Update applied successfully! LumiNewsletter has been updated to version ' . $latestVersion;
                $messageType = 'success';
                file_put_contents(__DIR__ . '/version.php', "<?php\nreturn '" . $latestVersion . "';\n");
                
                // Add this line to ensure database schema is always checked during updates
                applyDatabaseSchemaChanges($db);
            }
        } else {
            $message = 'Failed to extract the update package. ZipArchive error code: ' . $extractResult;
            $messageType = 'error';
            error_log($message);
        }
        unlink($tempFile);
    }
}
// Add this to the update process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($mysqli->connect_error) {
        $message = "Database connection failed: " . $mysqli->connect_error;
        $messageType = "error";
    } else {
        // Check and create missing tables
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
            if (!$mysqli->query($query)) {
                $message = "Error updating tables: " . $mysqli->error;
                $messageType = "error";
                break;
            }
        }

        if (!isset($message)) {
            $message = "Database tables updated successfully.";
            $messageType = "success";
        }
    }
}

// Helper function to recursively copy files
function recursiveCopy($source, $dest) {
    $dir = opendir($source);
    @mkdir($dest, 0755, true);
    
    // Files that should never be overwritten
    $protectedFiles = [
        'config.php',
        'includes/config.php',
        '.htaccess',
        'db.php',
        'includes/db.php'
    ];
    
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $sourcePath = $source . '/' . $file;
            $destPath = $dest . '/' . $file;
            
            // Skip protected files - silently
            $relativePath = str_replace(__DIR__ . '/', '', $destPath);
            if (in_array($relativePath, $protectedFiles)) {
                continue;
            }
            
            if (is_dir($sourcePath)) {
                recursiveCopy($sourcePath, $destPath);
            } else {
                // Check if destination file exists and make backup if needed
                if (file_exists($destPath) && !in_array(pathinfo($destPath, PATHINFO_EXTENSION), ['jpg', 'png', 'gif', 'svg', 'ico'])) {
                    // Create backups folder if it doesn't exist
                    if (!file_exists($dest . '/update_backups')) {
                        mkdir($dest . '/update_backups', 0755, true);
                    }
                    // Make a backup
                    copy($destPath, $dest . '/update_backups/' . basename($destPath) . '.bak');
                }
                
                // Now copy the file
                copy($sourcePath, $destPath);
            }
        }
    }
    closedir($dir);
}

// Helper function to recursively delete directory
function recursiveDelete($dir) {
    if (!file_exists($dir)) return;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? recursiveDelete($path) : unlink($path);
    }
    return rmdir($dir);
}

// New function to check database schema and apply changes
function applyDatabaseSchemaChanges($db) {
    // Standard tables that should exist in every LumiNewsletter installation
    $requiredTables = [
        // Email analytics tables
        'email_opens' => "CREATE TABLE IF NOT EXISTS email_opens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            newsletter_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            user_agent VARCHAR(255),
            ip_address VARCHAR(45),
            INDEX(newsletter_id),
            FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE
        )",
        'link_clicks' => "CREATE TABLE IF NOT EXISTS link_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            newsletter_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            original_url TEXT NOT NULL,
            clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            user_agent VARCHAR(255),
            ip_address VARCHAR(45),
            INDEX(newsletter_id),
            FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE
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
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(open_id),
            INDEX(click_id),
            FOREIGN KEY (open_id) REFERENCES email_opens(id) ON DELETE CASCADE,
            FOREIGN KEY (click_id) REFERENCES link_clicks(id) ON DELETE CASCADE
        )",
        'email_devices' => "CREATE TABLE IF NOT EXISTS email_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            open_id INT,
            device_type VARCHAR(50),
            browser VARCHAR(50),
            os VARCHAR(50),
            INDEX(open_id),
            FOREIGN KEY (open_id) REFERENCES email_opens(id) ON DELETE CASCADE
        )",
        // A/B testing tables
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
            sent_at TIMESTAMP NULL,
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
        )",
        // Automation tables
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
        'subscriber_segments' => "CREATE TABLE IF NOT EXISTS subscriber_segments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            criteria TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
        )",
        // E-commerce marketplace tables
        'marketplace_settings' => "CREATE TABLE IF NOT EXISTS marketplace_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'shopify_settings' => "CREATE TABLE IF NOT EXISTS shopify_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'woocommerce_settings' => "CREATE TABLE IF NOT EXISTS woocommerce_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'etsy_settings' => "CREATE TABLE IF NOT EXISTS etsy_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'ecommerce_customers' => "CREATE TABLE IF NOT EXISTS ecommerce_customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(50) NOT NULL,
            platform_id VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            data JSON,
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_customer (platform, platform_id)
        )",
        'ecommerce_products' => "CREATE TABLE IF NOT EXISTS ecommerce_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(50) NOT NULL,
            platform_id VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2),
            image_url VARCHAR(255),
            product_url VARCHAR(255),
            data JSON,
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product (platform, platform_id)
        )",
        'ecommerce_orders' => "CREATE TABLE IF NOT EXISTS ecommerce_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(50) NOT NULL,
            platform_id VARCHAR(100) NOT NULL,
            customer_id INT,
            order_date DATETIME,
            total_amount DECIMAL(10,2),
            status VARCHAR(50),
            data JSON,
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_order (platform, platform_id),
            FOREIGN KEY (customer_id) REFERENCES ecommerce_customers(id) ON DELETE SET NULL
        )",
        'product_recommendations' => "CREATE TABLE IF NOT EXISTS product_recommendations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subscriber_email VARCHAR(255) NOT NULL,
            product_id INT NOT NULL,
            score FLOAT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES ecommerce_products(id) ON DELETE CASCADE
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
    
    // Check if newsletters table needs the ab_test columns
    $result = $db->query("SHOW COLUMNS FROM newsletters LIKE 'is_ab_test'");
    if ($result->num_rows === 0) {
        $db->query("ALTER TABLE newsletters 
            ADD COLUMN is_ab_test TINYINT(1) DEFAULT 0,
            ADD COLUMN ab_test_id INT NULL,
            ADD COLUMN variant CHAR(1) NULL");
            
        // Add foreign key in a separate query since it might fail if the column already exists
        try {
            $db->query("ALTER TABLE newsletters 
                ADD CONSTRAINT fk_ab_test_id FOREIGN KEY (ab_test_id) 
                REFERENCES ab_tests(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            error_log("Could not add foreign key for ab_tests: " . $e->getMessage());
        }
    }
    
    // Check for each required table and create it if needed
    foreach ($requiredTables as $table => $createSql) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            // Table doesn't exist, create it
            if (!$db->query($createSql)) {
                error_log("Failed to create table $table: " . $db->error);
            }
        }
    }
    
    return true;
}

// Add these statements to handle upgrades from previous versions

// Check if automation_workflows table exists
$checkTable = $db->query("SHOW TABLES LIKE 'automation_workflows'");
if ($checkTable->num_rows === 0) {
    // Table doesn't exist, create it
    $db->query("CREATE TABLE automation_workflows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        trigger_type ENUM('subscription', 'date', 'tag_added', 'segment_join', 'inactivity', 'custom') NOT NULL,
        trigger_data JSON,
        status ENUM('active', 'draft', 'paused') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Created automation_workflows table.<br>";
}

// Check if automation_steps table exists
$checkTable = $db->query("SHOW TABLES LIKE 'automation_steps'");
if ($checkTable->num_rows === 0) {
    // Table doesn't exist, create it
    $db->query("CREATE TABLE automation_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        workflow_id INT NOT NULL,
        step_type ENUM('email', 'delay', 'condition', 'tag', 'split') NOT NULL,
        step_data JSON,
        position INT NOT NULL,
        FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE
    )");
    echo "Created automation_steps table.<br>";
}

// Check if automation_logs table exists
$checkTable = $db->query("SHOW TABLES LIKE 'automation_logs'");
if ($checkTable->num_rows === 0) {
    // Table doesn't exist, create it
    $db->query("CREATE TABLE automation_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        workflow_id INT NOT NULL,
        subscriber_email VARCHAR(255) NOT NULL,
        step_id INT NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'skipped') NOT NULL,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE,
        FOREIGN KEY (step_id) REFERENCES automation_steps(id) ON DELETE CASCADE
    )");
    echo "Created automation_logs table.<br>";
}

// Add this after the automation_logs check

// Check if privacy_settings table exists
$checkTable = $db->query("SHOW TABLES LIKE 'privacy_settings'");
if ($checkTable->num_rows === 0) {
    // Table doesn't exist, create it
    $db->query("CREATE TABLE privacy_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Created privacy_settings table.<br>";
}

// Check if subscriber_consent table exists
$checkTable = $db->query("SHOW TABLES LIKE 'subscriber_consent'");
if ($checkTable->num_rows === 0) {
    // Table doesn't exist, create it
    $db->query("CREATE TABLE subscriber_consent (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        tracking_consent BOOLEAN DEFAULT FALSE,
        geo_analytics_consent BOOLEAN DEFAULT FALSE,
        profile_analytics_consent BOOLEAN DEFAULT FALSE,
        consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        consent_record TEXT,
        UNIQUE KEY (email)
    )");
    echo "Created subscriber_consent table.<br>";
}

// Check if there are any personalization tags
$result = $db->query("SELECT COUNT(*) as count FROM personalization_tags");
$row = $result->fetch_assoc();

// Add default personalization tags if none exist
if ($row['count'] == 0) {
    $defaultTags = [
        ['first_name', 'Subscriber\'s first name', 'field', 'name', 'John'],
        ['last_name', 'Subscriber\'s last name', 'field', 'name', 'Doe'],
        ['email', 'Subscriber\'s email address', 'field', 'email', 'subscriber@example.com'],
        ['subscription_date', 'When the subscriber joined', 'field', 'created_at', 'January 15, 2023'],
        ['current_date', 'Today\'s date', 'function', 'date', date('F j, Y')],
        ['unsubscribe_link', 'Link to unsubscribe from the newsletter', 'function', 'unsubscribe_url', 'https://example.com/unsubscribe?email=subscriber@example.com']
    ];
    
    $stmt = $db->prepare("INSERT INTO personalization_tags (tag_name, description, replacement_type, field_name, example) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($defaultTags as $tag) {
        $stmt->bind_param("sssss", $tag[0], $tag[1], $tag[2], $tag[3], $tag[4]);
        $stmt->execute();
    }
    
    echo "Added default personalization tags.<br>";
}

// Add this after the personalization tags section

// Check if there are any privacy settings
$result = $db->query("SELECT COUNT(*) as count FROM privacy_settings");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Add default privacy settings if none exist
    if ($row['count'] == 0) {
        $defaultPrivacySettings = [
            ['privacy_policy', ''],
            ['enable_tracking', '1'],
            ['enable_geo_analytics', '1'],
            ['require_explicit_consent', '1'],
            ['data_retention_period', '12'],
            ['anonymize_ip', '0'],
            ['cookie_notice', '1'],
            ['cookie_notice_text', 'We use cookies to improve your experience and analyze website traffic. By clicking "Accept", you agree to our website\'s cookie use as described in our Privacy Policy.'],
            ['consent_prompt_text', 'I consent to receiving newsletters and agree that my email engagement may be tracked for analytics purposes.']
        ];
        
        $stmt = $db->prepare("INSERT INTO privacy_settings (setting_key, setting_value) VALUES (?, ?)");
        
        foreach ($defaultPrivacySettings as $setting) {
            $stmt->bind_param("ss", $setting[0], $setting[1]);
            $stmt->execute();
        }
        
        echo "Added default privacy settings.<br>";
    }
}

// Add this code after checking other tables
echo "Checking for API and social media tables... ";
$checkApiBefore = $db->query("SHOW TABLES LIKE 'api_keys'");
$checkSocialBefore = $db->query("SHOW TABLES LIKE 'social_shares'");

if ($checkApiBefore->num_rows === 0) {
    $db->query($requiredTables['api_keys']);
    $db->query($requiredTables['api_requests']);
    echo "Created API tables. ";
}

if ($checkSocialBefore->num_rows === 0) {
    $db->query($requiredTables['social_shares']);
    $db->query($requiredTables['social_clicks']);
    echo "Created social media tables. ";
}
echo "<br>";

// Store messages for later display
$updateMessages = [];

if ($checkAPIBefore->num_rows === 0) {
    // Create API tables - use the same definitions from requiredTables later in the file
    // $db->query($requiredTables['api_keys']);
    // $db->query($requiredTables['api_requests']);
    $updateMessages[] = "Created API tables.";
}

if ($checkSocialBefore->num_rows === 0) {
    // Create social tables - use the same definitions from requiredTables later in the file
    // $db->query($requiredTables['social_shares']);
    // $db->query($requiredTables['social_clicks']);
    $updateMessages[] = "Created social media tables.";
}

// Add these checks after your existing database checks

// Check for AI Assistant tables and create them if they don't exist
$checkAISettings = $db->query("SHOW TABLES LIKE 'ai_settings'");
if ($checkAISettings->num_rows === 0) {
    // Create AI settings table
    $db->query("CREATE TABLE IF NOT EXISTS ai_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        api_provider VARCHAR(50) NOT NULL DEFAULT 'openai',
        api_key VARCHAR(255) NOT NULL DEFAULT '',
        model VARCHAR(100) DEFAULT 'gpt-3.5-turbo',
        max_tokens INT DEFAULT 500,
        temperature FLOAT DEFAULT 0.7,
        enabled TINYINT(1) DEFAULT 1,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default settings
    $db->query("INSERT INTO ai_settings (api_provider, api_key, model, enabled) 
                VALUES ('openai', '', 'gpt-3.5-turbo', 1)");
    
    $dbUpdates[] = "Created AI settings table with default configuration";
}

$checkContentSuggestions = $db->query("SHOW TABLES LIKE 'content_suggestions'");
if ($checkContentSuggestions->num_rows === 0) {
    // Create content suggestions table
    $db->query("CREATE TABLE IF NOT EXISTS content_suggestions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NULL,
        type VARCHAR(50) NOT NULL,
        original_content TEXT NULL,
        suggested_content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        applied TINYINT(1) DEFAULT 0,
        FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE SET NULL
    )");
    
    $dbUpdates[] = "Created content suggestions table for AI assistant";
}

$checkSubjectSuggestions = $db->query("SHOW TABLES LIKE 'subject_suggestions'");
if ($checkSubjectSuggestions->num_rows === 0) {
    // Create subject suggestions table
    $db->query("CREATE TABLE IF NOT EXISTS subject_suggestions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NULL,
        original_subject VARCHAR(255) NULL,
        suggested_subject VARCHAR(255) NOT NULL,
        reason TEXT NULL,
        predicted_open_rate FLOAT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        applied TINYINT(1) DEFAULT 0,
        FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE SET NULL
    )");
    
    $dbUpdates[] = "Created subject suggestions table for AI assistant";
}

$checkContentAnalysis = $db->query("SHOW TABLES LIKE 'content_analysis'");
if ($checkContentAnalysis->num_rows === 0) {
    // Create content analysis table
    $db->query("CREATE TABLE IF NOT EXISTS content_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NULL,
        readability_score FLOAT NULL,
        sentiment_score FLOAT NULL,
        spam_score FLOAT NULL,
        word_count INT NULL,
        read_time INT NULL,
        analysis_json JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE SET NULL
    )");
    
    $dbUpdates[] = "Created content analysis table for AI assistant";
}

// Check and add the 'features' table if it doesn't exist
$checkFeatures = $db->query("SHOW TABLES LIKE 'features'");
if ($checkFeatures->num_rows === 0) {
    // Create features table for toggling system features
    $db->query("CREATE TABLE IF NOT EXISTS features (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feature_name VARCHAR(50) NOT NULL UNIQUE,
        enabled TINYINT(1) DEFAULT 1,
        added_version VARCHAR(20) NULL,
        description TEXT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Add AI Assistant feature entry
    $db->query("INSERT INTO features (feature_name, enabled, added_version, description) 
                VALUES ('ai_assistant', 1, '$latestVersion', 'AI-powered content suggestions, subject line optimization, and content analysis')");
    
    $dbUpdates[] = "Created features management table";
}

// Add this to your database update queries:

$checkAISettings = $db->query("SHOW TABLES LIKE 'ai_settings'");
if ($checkAISettings->num_rows === 0) {
    $db->query("CREATE TABLE IF NOT EXISTS `ai_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `api_provider` VARCHAR(50) NOT NULL DEFAULT 'openai',
        `api_key` VARCHAR(255) NOT NULL,
        `model` VARCHAR(50) NOT NULL DEFAULT 'gpt-3.5-turbo',
        `max_tokens` INT NOT NULL DEFAULT 500,
        `temperature` FLOAT NOT NULL DEFAULT 0.7,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default AI settings
    $db->query("INSERT INTO `ai_settings` 
        (api_provider, api_key, model, max_tokens, temperature) 
        VALUES ('openai', '', 'gpt-3.5-turbo', 500, 0.7)");
    
    $dbUpdates[] = "Created ai_settings table with default values.";
}

// Clear any output so far to prevent it showing before HTML
ob_end_clean();

$messages = [];
$errors = [];

function addColumnIfNotExists($db, $table, $column, $definition) {
    try {
        // Check if column exists
        $columnExists = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->num_rows > 0;
        
        if (!$columnExists) {
            $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Add sent_at column to newsletters table
if (addColumnIfNotExists($db, 'newsletters', 'sent_at', 'TIMESTAMP NULL DEFAULT NULL')) {
    $messages[] = "Added sent_at column to newsletters table";
    
    // Update existing newsletters with sent_at based on created_at
    $db->query("UPDATE newsletters SET sent_at = created_at WHERE sent_at IS NULL");
    $messages[] = "Updated existing newsletters with sent_at timestamps";
} else {
    $messages[] = "The sent_at column already exists or couldn't be added to newsletters table";
}

// Add this after the database connection and necessary includes:

// Check if groups table exists
$checkTable = $db->query("SHOW TABLES LIKE 'groups'");
if ($checkTable->num_rows > 0) {
    // Check if description column exists
    $checkColumn = $db->query("SHOW COLUMNS FROM `groups` LIKE 'description'");
    if ($checkColumn->num_rows === 0) {
        // Add description column if it doesn't exist
        $db->query("ALTER TABLE `groups` ADD COLUMN `description` TEXT NULL AFTER `name`");
        echo "Added description column to groups table.<br>";
    }
    
    // Check if created_at column exists
    $checkColumn = $db->query("SHOW COLUMNS FROM `groups` LIKE 'created_at'");
    if ($checkColumn->num_rows === 0) {
        // Add created_at column if it doesn't exist
        $db->query("ALTER TABLE `groups` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added created_at column to groups table.<br>";
    }
    
    // Check if group_subscriptions table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'group_subscriptions'");
    if ($checkTable->num_rows > 0) {
        // Check if name column exists in group_subscriptions
        $checkColumn = $db->query("SHOW COLUMNS FROM `group_subscriptions` LIKE 'name'");
        if ($checkColumn->num_rows === 0) {
            // Add name column if it doesn't exist
            $db->query("ALTER TABLE `group_subscriptions` ADD COLUMN `name` VARCHAR(255) NULL AFTER `email`");
            $messages[] = "Added name column to group_subscriptions table";
        }
        
        // Check if subscribed_at column exists
        $checkColumn = $db->query("SHOW COLUMNS FROM `group_subscriptions` LIKE 'subscribed_at'");
        if ($checkColumn->num_rows === 0) {
            // Add subscribed_at column if it doesn't exist
            $db->query("ALTER TABLE `group_subscriptions` ADD COLUMN `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            $messages[] = "Added subscribed_at column to group_subscriptions table";
        }
    }
}

$checkEmailTemplates = $db->query("SHOW TABLES LIKE 'email_templates'");
if ($checkEmailTemplates->num_rows === 0) {
    $db->query("CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        content LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        is_system BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $dbUpdates[] = "Created email_templates table.";
}

$checkMediaLibrary = $db->query("SHOW TABLES LIKE 'media_library'");
if ($checkMediaLibrary->num_rows === 0) {
    $db->query("CREATE TABLE IF NOT EXISTS media_library (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50),
        uploaded_by INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        filename VARCHAR(255) NOT NULL,
        filepath VARCHAR(255) NOT NULL,
        filetype VARCHAR(50),
        filesize INT NOT NULL,
        dimensions VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $dbUpdates[] = "Created media_library table.";
} else {
    // Check for missing columns and add them
    $missingColumns = [
        'filename' => 'VARCHAR(255) NOT NULL',
        'filepath' => 'VARCHAR(255) NOT NULL',
        'filetype' => 'VARCHAR(50)',
        'filesize' => 'INT NOT NULL',
        'dimensions' => 'VARCHAR(20) NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];
    
    foreach ($missingColumns as $column => $definition) {
        $checkColumn = $db->query("SHOW COLUMNS FROM media_library LIKE '$column'");
        if ($checkColumn->num_rows === 0) {
            $db->query("ALTER TABLE media_library ADD COLUMN $column $definition");
            $dbUpdates[] = "Added '$column' column to media_library table.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Updates | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Rest of the head content -->
</head>
<body>
    <!-- Mobile navigation toggle button -->
    <button class="mobile-nav-toggle" id="mobileNavToggle">
        <i class="fas fa-bars" id="menuIcon"></i>
    </button>
    
    <!-- Backdrop for mobile menu -->
    <div class="backdrop" id="backdrop"></div>
    
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="content">
            <!-- Rest of the main content -->
            <header class="top-header">
                <div class="header-left">
                    <h1>Update Software</h1>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="notification info" style="background-color: rgba(66, 133, 244, 0.1); border-left: 4px solid var(--primary); color: var(--primary);">
                <i class="fas fa-database"></i>
                <div>
                    <strong>Database Setup:</strong> If you experience missing tables or database errors, use our table repair tool.
                    <a href="fix_tables.php" class="btn btn-sm" style="margin-left: 10px; background-color: var(--primary); color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; display: inline-block; margin-top: 5px;">
                        <i class="fas fa-wrench"></i> Run Fix Tables
                    </a>
                </div>
            </div>

            <div class="notification info" style="background-color: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; color: #856404; margin-top: 15px;">
                <i class="fas fa-users"></i>
                <div>
                    <strong>Subscriber Groups:</strong> If you're experiencing issues with subscriber groups or the 'description' column, use our group repair tool.
                    <a href="fix_groups_table.php" class="btn btn-sm" style="margin-left: 10px; background-color: #ffc107; color: #212529; text-decoration: none; padding: 5px 10px; border-radius: 4px; display: inline-block; margin-top: 5px;">
                        <i class="fas fa-user-cog"></i> Run Group Fix
                    </a>
                </div>
            </div>

            <!-- Add the new notification for fixing subscriptions -->
            <div class="notification info" style="background-color: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745; color: #155724; margin-top: 15px;">
                <i class="fas fa-envelope"></i>
                <div>
                    <strong>Subscriber Data:</strong> If you're having issues with the 'name' field for subscribers or other subscription data, use our subscription repair tool.
                    <a href="fix_subscriptions.php" class="btn btn-sm" style="margin-left: 10px; background-color: #28a745; color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; display: inline-block; margin-top: 5px;">
                        <i class="fas fa-envelope-open-text"></i> Fix Subscriptions
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-sync-alt"></i> Software Update</h2>
                </div>
                <div class="card-body">
                    <div class="version-info">
                        <i class="fas fa-code-branch"></i>
                        <span>Current version: <strong><?php echo htmlspecialchars($currentVersion); ?></strong></span>
                    </div>
                    
                    <?php if ($updateAvailable): ?>
                        <div class="update-available">
                            <h3><i class="fas fa-download"></i> Update Available!</h3>
                            <p>A new version of LumiNewsletter (v<?php echo htmlspecialchars($latestVersion); ?>) is ready to install.</p>
                        </div>
                        
                        <div class="update-changelog">
                            <h4><i class="fas fa-list"></i> What's New</h4>
                            <?php 
                            $changelogLines = explode("\n", $changelog);
                            if (count($changelogLines) > 1): 
                            ?>
                                <ul>
                                    <?php foreach ($changelogLines as $line): ?>
                                        <?php $line = trim($line); ?>
                                        <?php if (!empty($line)): ?>
                                            <li><?php echo htmlspecialchars(ltrim($line, '- ')); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p><?php echo htmlspecialchars($changelog); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <form method="post">
                            <button type="submit" class="update-btn">
                                <i class="fas fa-download"></i> Download & Install Update
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="up-to-date">
                            <i class="fas fa-check-circle"></i>
                            <span>Your LumiNewsletter is up to date! No updates are available at this time.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px;">
                <h1>Database Schema Update</h1>
                
                <?php if (!empty($messages)): ?>
                    <div class="notification success">
                        <h3>Update Results:</h3>
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
                
                <p><a href="admin.php" class="btn btn-primary">Return to Admin Panel</a></p>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script src="assets/js/sidebar.js"></script>
    <script>
// Ensure the sidebar is properly initialized after update
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the sidebar menu
    const menuHeaders = document.querySelectorAll('.menu-group-header');
    menuHeaders.forEach(header => {
        header.addEventListener('click', function() {
            this.classList.toggle('active');
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains('submenu')) {
                submenu.classList.toggle('show');
            }
        });
    });
});

// Add safety timeout to redirect in case of issues
setTimeout(function() {
    // If user is still on update page after 30 seconds, provide manual navigation
    const mainContent = document.querySelector('.content');
    if (mainContent) {
        const warningDiv = document.createElement('div');
        warningDiv.className = 'notification warning';
        warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> If you\'re stuck on this page, <a href="admin.php" style="color: inherit; text-decoration: underline; font-weight: bold;">click here to return to Admin</a>';
        mainContent.appendChild(warningDiv);
    }
}, 30000);
</script>
</body>
</html>