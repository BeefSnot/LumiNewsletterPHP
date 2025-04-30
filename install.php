<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error_message = '';
$info_message = '';
$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$total_steps = 4;

// Create directories if they don't exist
if (!file_exists('includes')) {
    mkdir('includes', 0755, true);
}

if (!file_exists('includes/phpmailer')) {
    mkdir('includes/phpmailer', 0755, true);
}

if (!file_exists('includes/phpmailer/src')) {
    mkdir('includes/phpmailer/src', 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    set_time_limit(30); // Set a reasonable timeout

    $db_host = $_POST['db_host'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $db_name = $_POST['db_name'];
    $admin_user = $_POST['admin_user'];
    $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_BCRYPT);
    $admin_email = $_POST['admin_email'];
    $smtp_host = $_POST['smtp_host'];
    $smtp_user = $_POST['smtp_user'];
    $smtp_pass = $_POST['smtp_pass'];
    $smtp_port = $_POST['smtp_port'];
    $smtp_secure = $_POST['smtp_secure'];

    try {
        // Test database connection with better error reporting
        try {
            // More compatible connection syntax
            $db = new mysqli($db_host, $db_user, $db_pass);
            // Set timeout manually if needed
            $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        } catch (Exception $e) {
            throw new Exception('Connection failed: ' . $e->getMessage() . ' - Please verify your database credentials.');
        }
        
        if ($db->connect_error) {
            throw new Exception('Connection failed: ' . $db->connect_error . ' - Error code: ' . $db->connect_errno);
        }
        
        // Create database if it doesn't exist
        $db->query("CREATE DATABASE IF NOT EXISTS `$db_name`");
        $db->select_db($db_name);

        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                email VARCHAR(100) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS themes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                content TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS `groups` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS newsletters (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                send_date TIMESTAMP NULL,
                sent_at TIMESTAMP NULL,
                scheduled_date TIMESTAMP NULL,
                status ENUM('draft', 'scheduled', 'sent') DEFAULT 'draft',
                creator_id INT,
                group_id INT,
                theme_id INT NULL,
                is_ab_test TINYINT(1) DEFAULT 0,
                ab_test_id INT NULL,
                variant CHAR(1) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (creator_id) REFERENCES users(id),
                FOREIGN KEY (group_id) REFERENCES `groups`(id),
                FOREIGN KEY (theme_id) REFERENCES themes(id)
            )",
            "CREATE TABLE IF NOT EXISTS group_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NULL,
                last_name VARCHAR(100) NULL,
                group_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES `groups`(id)
            )",
            "CREATE TABLE IF NOT EXISTS newsletter_groups (
                newsletter_id INT NOT NULL,
                group_id INT NOT NULL,
                PRIMARY KEY (newsletter_id, group_id),
                FOREIGN KEY (newsletter_id) REFERENCES newsletters(id),
                FOREIGN KEY (group_id) REFERENCES `groups`(id)
            )",
            "CREATE TABLE IF NOT EXISTS updates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS settings (
                name VARCHAR(50) PRIMARY KEY,
                value TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            // Analytics tables
            "CREATE TABLE IF NOT EXISTS email_opens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                newsletter_id INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_agent VARCHAR(255),
                ip_address VARCHAR(45),
                FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS link_clicks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                newsletter_id INT NOT NULL,
                email VARCHAR(255) NOT NULL, 
                original_url TEXT NOT NULL,
                clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_agent VARCHAR(255),
                ip_address VARCHAR(45),
                FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS email_geo_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                open_id INT,
                click_id INT,
                country VARCHAR(100),
                region VARCHAR(100),
                city VARCHAR(100),
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (open_id) REFERENCES email_opens(id) ON DELETE CASCADE,
                FOREIGN KEY (click_id) REFERENCES link_clicks(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS email_devices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                open_id INT,
                device_type VARCHAR(50),
                browser VARCHAR(50),
                os VARCHAR(50),
                FOREIGN KEY (open_id) REFERENCES email_opens(id) ON DELETE CASCADE
            )",
            // A/B Testing tables
            "CREATE TABLE IF NOT EXISTS ab_tests (
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
                FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
            )",
            // Subscriber management tables
            "CREATE TABLE IF NOT EXISTS subscriber_scores (
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
            "CREATE TABLE IF NOT EXISTS subscriber_tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                tag VARCHAR(100) NOT NULL,
                added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (email, tag)
            )",
            "CREATE TABLE IF NOT EXISTS subscriber_segments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                criteria TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            // Add foreign key for ab_tests in newsletters table
            "ALTER TABLE newsletters ADD CONSTRAINT fk_ab_test_id FOREIGN KEY (ab_test_id) REFERENCES ab_tests(id) ON DELETE SET NULL",
            // Default data
            "INSERT INTO users (username, email, password, role) VALUES ('$admin_user', '$admin_email', '$admin_pass', 'admin')",
            "INSERT INTO settings (name, value) VALUES ('title', 'Newsletter Dashboard'), ('background', 'assets/images/background.png')",
            // Automation tables
            "CREATE TABLE IF NOT EXISTS automation_workflows (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                trigger_type ENUM('subscription', 'date', 'tag_added', 'segment_join', 'inactivity', 'custom') NOT NULL,
                trigger_data JSON,
                status ENUM('active', 'draft', 'paused') DEFAULT 'draft',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS automation_steps (
                id INT AUTO_INCREMENT PRIMARY KEY,
                workflow_id INT NOT NULL,
                step_type ENUM('email', 'delay', 'condition', 'tag', 'split') NOT NULL,
                step_data JSON,
                position INT NOT NULL,
                FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS automation_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                workflow_id INT NOT NULL,
                subscriber_email VARCHAR(255) NOT NULL,
                step_id INT NOT NULL,
                status ENUM('pending', 'completed', 'failed', 'skipped') NOT NULL,
                processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE,
                FOREIGN KEY (step_id) REFERENCES automation_steps(id) ON DELETE CASCADE
            )",
            // Content blocks
            "CREATE TABLE IF NOT EXISTS content_blocks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                content LONGTEXT,
                type ENUM('static', 'dynamic', 'conditional') NOT NULL DEFAULT 'static',
                conditions TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            // Personalization tags
            "CREATE TABLE IF NOT EXISTS personalization_tags (
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
            // Privacy settings table
            "CREATE TABLE IF NOT EXISTS privacy_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            // Subscriber consent table
            "CREATE TABLE IF NOT EXISTS subscriber_consent (
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
            // API keys table
            "CREATE TABLE IF NOT EXISTS api_keys (
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
            // API requests table
            "CREATE TABLE IF NOT EXISTS api_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                endpoint VARCHAR(100) NOT NULL,
                method VARCHAR(10) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                status_code INT NOT NULL,
                request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
            )",
            // Social shares table
            "CREATE TABLE IF NOT EXISTS social_shares (
                id INT AUTO_INCREMENT PRIMARY KEY,
                newsletter_id INT NOT NULL,
                platform VARCHAR(50) NOT NULL,
                share_count INT DEFAULT 0,
                click_count INT DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE
            )",
            // Social clicks table
            "CREATE TABLE IF NOT EXISTS social_clicks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                share_id INT NOT NULL,
                ip_address VARCHAR(45) NULL,
                referrer VARCHAR(255) NULL,
                clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (share_id) REFERENCES social_shares(id) ON DELETE CASCADE
            )",
            // Add social sharing setting in settings table initialization
            "INSERT INTO settings (name, value) VALUES ('social_sharing_enabled', '1')"
        ];

        foreach ($queries as $query) {
            if ($db->query($query) === FALSE) {
                throw new Exception('Error executing query: ' . $db->error);
            }
        }
        
        // Create default "Thanks For Subscribing" theme
        $defaultTheme = file_exists('thanks_for_subscribing.html') ? 
            file_get_contents('thanks_for_subscribing.html') : 
            '<h1>Thanks For Subscribing!</h1><p>We\'re glad to have you on board.</p>';
            
        if ($defaultTheme) {
            $stmt = $db->prepare("INSERT INTO themes (name, content) VALUES ('Thanks For Subscribing', ?)");
            $stmt->bind_param("s", $defaultTheme);
            $stmt->execute();
        }

        // Create config.php file
        $configContent = "<?php\nreturn [\n";
        $configContent .= "    'db_host' => '$db_host',\n";
        $configContent .= "    'db_user' => '$db_user',\n";
        $configContent .= "    'db_pass' => '$db_pass',\n";
        $configContent .= "    'db_name' => '$db_name',\n";
        $configContent .= "    'smtp_host' => '$smtp_host',\n";
        $configContent .= "    'smtp_user' => '$smtp_user',\n";
        $configContent .= "    'smtp_pass' => '$smtp_pass',\n";
        $configContent .= "    'smtp_port' => '$smtp_port',\n";
        $configContent .= "    'smtp_secure' => '$smtp_secure'\n";
        $configContent .= "];\n";

        if (file_put_contents('includes/config.php', $configContent) === false) {
            throw new Exception('Failed to write config.php file.');
        }
        
        // Create db.php file
        $dbContent = "<?php\n";
        $dbContent .= "\$config = require 'config.php';\n\n";
        $dbContent .= "\$db = new mysqli(\$config['db_host'], \$config['db_user'], \$config['db_pass'], \$config['db_name']);\n\n";
        $dbContent .= "if (\$db->connect_error) {\n";
        $dbContent .= "    die('Connection failed: ' . \$db->connect_error);\n";
        $dbContent .= "}\n";

        if (file_put_contents('includes/db.php', $dbContent) === false) {
            throw new Exception('Failed to write db.php file.');
        }
        
        // Create init.php file
        $initContent = "<?php\n";
        $initContent .= "require_once __DIR__ . '/phpmailer/src/PHPMailer.php';\n";
        $initContent .= "require_once __DIR__ . '/phpmailer/src/SMTP.php';\n";
        $initContent .= "require_once __DIR__ . '/phpmailer/src/Exception.php';\n\n";
        $initContent .= "use PHPMailer\\PHPMailer\\PHPMailer;\n";
        $initContent .= "use PHPMailer\\PHPMailer\\SMTP;\n";
        $initContent .= "use PHPMailer\\PHPMailer\\Exception;\n";

        if (file_put_contents('includes/init.php', $initContent) === false) {
            throw new Exception('Failed to write init.php file.');
        }
        
        // Create auth.php file
        if (!file_exists('includes/auth.php')) {
            $authContent = "<?php\n";
            $authContent .= "function isLoggedIn() {\n";
            $authContent .= "    return isset(\$_SESSION['user_id']);\n";
            $authContent .= "}\n";
            file_put_contents('includes/auth.php', $authContent);
        }
        
        // Create footer.php file
        if (!file_exists('includes/footer.php')) {
            $footerContent = "<footer class=\"app-footer\">\n";
            $footerContent .= "    <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>\n";
            $footerContent .= "</footer>";
            file_put_contents('includes/footer.php', $footerContent);
        }
        
        // Create version.php file
        $versionContent = "<?php\nreturn '1.0.0';\n";
        file_put_contents('version.php', $versionContent);

        // Insert default privacy settings
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

        // Installation complete! Show success message
        $info_message = "<strong>Installation successful!</strong> Your LumiNewsletter system has been installed successfully.";
        $current_step = 5; // Success step
        
    } catch (Exception $e) {
        $error_message = 'Installation failed: ' . $e->getMessage();
        // Reset any partial success indication
        $info_message = '';
        $current_step = 4; // Stay on the current step to allow corrections
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install LumiNewsletter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4285f4;
            --primary-light: #6fa8dc;
            --primary-dark: #1a73e8;
            --accent: #34a853;
            --accent-hover: #2d9348;
            --gray-dark: #333;
            --gray: #757575;
            --gray-light: #f5f7fa;
            --warning: #fbbc05;
            --error: #ea4335;
            --white: #ffffff;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            --radius: 8px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--gray-dark);
            background-color: var(--gray-light);
            line-height: 1.6;
        }
        
        .install-container {
            max-width: 850px;
            margin: 2rem auto;
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .install-header {
            background-color: var(--primary);
            padding: 2rem;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .logo i {
            font-size: 3rem;
            margin-right: 1rem;
        }
        
        .logo h1 {
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .install-steps {
            display: flex;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 1rem 0;
            margin-top: 1rem;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 1.5rem;
            position: relative;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 1rem;
            right: -10px;
            width: 20px;
            height: 2px;
            background-color: rgba(255, 255, 255, 0.4);
        }
        
        .step-number {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        .step.active .step-number {
            background-color: white;
            color: var(--primary);
        }
        
        .step-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .step.active .step-label {
            opacity: 1;
            font-weight: 500;
        }
        
        .install-body {
            padding: 2rem;
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h2 {
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-weight: 600;
        }
        
        p {
            margin-bottom: 1.5rem;
            color: var (--gray);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(66, 133, 244, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn:hover {
            background: var(--primary-dark);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary-light);
            color: white;
        }
        
        .form-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background-color: var(--gray-light);
            border-radius: var(--radius);
        }
        
        .requirement i {
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .requirement.success i {
            color: var(--accent);
        }
        
        .requirement.error i {
            color: var(--error);
        }
        
        .requirement-text {
            flex: 1;
        }
        
        .notification {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
        }
        
        .notification i {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }
        
        .notification.success {
            background-color: rgba(52, 168, 83, 0.1);
            color: var (--accent);
            border-left: 4px solid var(--accent);
        }
        
        .notification.error {
            background-color: rgba(234, 67, 53, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
        }
        
        .success-message {
            text-align: center;
            padding: 2rem;
        }
        
        .success-message i {
            font-size: 5rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }
        
        .success-message h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        
        .success-message .btn {
            margin-top: 1.5rem;
        }
        
        .install-footer {
            text-align: center;
            padding: 1.5rem;
            background-color: var(--gray-light);
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .progress-bar {
            height: 5px;
            background-color: var(--gray-light);
            border-radius: var (--radius);
            overflow: hidden;
            margin: 0 2rem 1rem 2rem;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var (--primary);
            width: <?php echo ($current_step / $total_steps) * 100; ?>%;
            transition: width 0.5s;
        }
        
        .thumbnail-container {
            margin-top: 1rem;
            text-align: center;
        }
        
        .thumbnail {
            max-width: 100%;
            height: auto;
            border-radius: var (--radius);
            box-shadow: var(--shadow);
        }
        
        .password-field {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <div class="logo">
                <i class="fas fa-paper-plane"></i>
                <h1>LumiNewsletter</h1>
            </div>
            <p>Professional Email Marketing Solution</p>
            
            <div class="install-steps">
                <div class="step <?php echo $current_step == 1 ? 'active' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-label">Requirements</div>
                </div>
                <div class="step <?php echo $current_step == 2 ? 'active' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">Database</div>
                </div>
                <div class="step <?php echo $current_step == 3 ? 'active' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-label">Admin</div>
                </div>
                <div class="step <?php echo $current_step == 4 ? 'active' : ''; ?>">
                    <div class="step-number">4</div>
                    <div class="step-label">SMTP</div>
                </div>
            </div>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        
        <?php if ($error_message): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($info_message && $current_step < 5): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i>
                <?php echo $info_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="install-body">
            <!-- Step 1: Requirements -->
            <div class="step-content <?php echo $current_step == 1 ? 'active' : ''; ?>" id="step1">
                <div class="section-title">
                    <i class="fas fa-clipboard-check"></i>
                    <h2>System Requirements</h2>
                </div>
                
                <p>Before we begin the installation process, let's make sure your server meets all requirements.</p>
                
                <?php
                $php_version = PHP_VERSION;
                $php_ok = version_compare($php_version, '7.4.0', '>=');
                
                $mysqli_ok = extension_loaded('mysqli');
                $pdo_ok = extension_loaded('pdo');
                $json_ok = extension_loaded('json');
                $mbstring_ok = extension_loaded('mbstring');
                $openssl_ok = extension_loaded('openssl');
                
                $writeable_dirs = [
                    'includes' => is_writable('includes') || !file_exists('includes'),
                    'assets' => is_writable('assets') || !file_exists('assets'),
                ];
                
                $all_requirements_met = $php_ok && $mysqli_ok && $json_ok && $mbstring_ok && 
                                        $openssl_ok && !in_array(false, $writeable_dirs);
                ?>
                
                <div class="requirement <?php echo $php_ok ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $php_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="requirement-text">
                        <strong>PHP Version:</strong> <?php echo $php_version; ?> 
                        <?php echo $php_ok ? '(✓)' : '(✗ PHP 7.4 or higher required)'; ?>
                    </div>
                </div>
                
                <div class="requirement <?php echo $mysqli_ok ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $mysqli_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="requirement-text">
                        <strong>MySQLi Extension:</strong> 
                        <?php echo $mysqli_ok ? 'Installed (✓)' : 'Not installed (✗)'; ?>
                    </div>
                </div>
                
                <div class="requirement <?php echo $json_ok ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $json_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="requirement-text">
                        <strong>JSON Extension:</strong> 
                        <?php echo $json_ok ? 'Installed (✓)' : 'Not installed (✗)'; ?>
                    </div>
                </div>
                
                <div class="requirement <?php echo $mbstring_ok ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $mbstring_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="requirement-text">
                        <strong>Mbstring Extension:</strong> 
                        <?php echo $mbstring_ok ? 'Installed (✓)' : 'Not installed (✗)'; ?>
                    </div>
                </div>
                
                <div class="requirement <?php echo $openssl_ok ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $openssl_ok ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="requirement-text">
                        <strong>OpenSSL Extension:</strong> 
                        <?php echo $openssl_ok ? 'Installed (✓)' : 'Not installed (✗)'; ?>
                    </div>
                </div>
                
                <h3 style="margin-top: 1.5rem;">Directory Permissions</h3>
                
                <?php foreach($writeable_dirs as $dir => $writable): ?>
                <div class="requirement <?php echo $writable ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $writable ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="requirement-text">
                        <strong><?php echo $dir; ?>/</strong>
                        <?php echo $writable ? 'Writable (✓)' : 'Not writable (✗)'; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="form-buttons">
                    <div></div> <!-- Empty div for spacing -->
                    <a href="?step=2" class="btn <?php echo !$all_requirements_met ? 'disabled' : ''; ?>" <?php echo !$all_requirements_met ? 'onclick="return false;"' : ''; ?>>
                        Next Step <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Step 2: Database Configuration -->
            <div class="step-content <?php echo $current_step == 2 ? 'active' : ''; ?>" id="step2">
                <div class="section-title">
                    <i class="fas fa-database"></i>
                    <h2>Database Configuration</h2>
                </div>
                
                <p>Please enter your database connection details. If you're not sure what these are, contact your host.</p>
                
                <form method="post" action="?step=3" id="db-form">
                    <div class="form-group">
                        <label for="db_host">Database Host:</label>
                        <select id="db_host" name="db_host" required>
                            <option value="localhost">localhost (socket connection)</option>
                            <option value="127.0.0.1" selected>127.0.0.1 (TCP/IP connection)</option>
                            <option value="%">% (wildcard - for certain hosting configurations)</option>
                        </select>
                        <small class="form-text text-muted">Choose "%" if you're using DirectAdmin or another control panel that configures database users with wildcard hosts.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Database Name:</label>
                        <input type="text" id="db_name" name="db_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">Database User:</label>
                        <input type="text" id="db_user" name="db_user" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Database Password:</label>
                        <div class="password-field">
                            <input type="password" id="db_pass" name="db_pass" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('db_pass')"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="btn btn-outline test-connection">
                            <i class="fas fa-database"></i> Test Connection
                        </button>
                        <span id="connection-result" style="margin-left: 15px;"></span>
                    </div>
                    
                    <div class="form-buttons">
                        <a href="?step=1" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Previous
                        </a>
                        <button type="submit" class="btn">Next <i class="fas fa-arrow-right"></i></button>
                    </div>
                </form>
            </div>
            
            <!-- Step 3: Admin Account -->
            <div class="step-content <?php echo $current_step == 3 ? 'active' : ''; ?>" id="step3">
                <div class="section-title">
                    <i class="fas fa-user-shield"></i>
                    <h2>Admin Account</h2>
                </div>
                
                <p>Create an administrator account to manage your newsletter system.</p>
                
                <form method="post" action="?step=4" id="admin-form">
                    <!-- Include database fields as hidden inputs -->
                    <input type="hidden" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? ''); ?>">
                    <input type="hidden" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>">
                    <input type="hidden" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                    
                    <div class="form-group">
                        <label for="admin_user">Username:</label>
                        <input type="text" id="admin_user" name="admin_user" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Email Address:</label>
                        <input type="email" id="admin_email" name="admin_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_pass">Password:</label>
                        <div class="password-field">
                            <input type="password" id="admin_pass" name="admin_pass" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('admin_pass')"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_pass_confirm">Confirm Password:</label>
                        <div class="password-field">
                            <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('admin_pass_confirm')"></i>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <a href="?step=2" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Previous
                        </a>
                        <button type="submit" class="btn">Next <i class="fas fa-arrow-right"></i></button>
                    </div>
                </form>
            </div>
            
            <!-- Step 4: SMTP Configuration -->
            <div class="step-content <?php echo $current_step == 4 ? 'active' : ''; ?>" id="step4">
                <div class="section-title">
                    <i class="fas fa-envelope"></i>
                    <h2>SMTP Configuration</h2>
                </div>
                
                <p>Configure the email server settings for sending newsletters. You can get these details from your email provider.</p>
                
                <form method="post" action="?step=5" id="smtp-form">
                    <!-- Include all previous fields as hidden inputs -->
                    <input type="hidden" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? ''); ?>">
                    <input type="hidden" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>">
                    <input type="hidden" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                    <input type="hidden" name="admin_user" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? ''); ?>">
                    <input type="hidden" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>">
                    <input type="hidden" name="admin_pass" value="<?php echo htmlspecialchars($_POST['admin_pass'] ?? ''); ?>">
                    
                    <div class="form-group">
                        <label for="smtp_host">SMTP Host:</label>
                        <input type="text" id="smtp_host" name="smtp_host" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_port">SMTP Port:</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="587" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_secure">Security Type:</label>
                        <select id="smtp_secure" name="smtp_secure">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="">None</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_user">SMTP Username:</label>
                        <input type="text" id="smtp_user" name="smtp_user" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_pass">SMTP Password:</label>
                        <div class="password-field">
                            <input type="password" id="smtp_pass" name="smtp_pass" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('smtp_pass')"></i>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <a href="?step=3" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Previous
                        </a>
                        <button type="submit" name="install" class="btn">Install <i class="fas fa-check"></i></button>
                    </div>
                </form>
            </div>
            
            <!-- Step 5: Success -->
            <div class="step-content <?php echo $current_step == 5 ? 'active' : ''; ?>" id="step5">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>Installation Complete!</h2>
                    <p>Congratulations! LumiNewsletter has been installed successfully.</p>
                    <p>You can now log in to your admin dashboard using your credentials.</p>
                    <a href="login.php" class="btn">Go to Login <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
        
        <div class="install-footer">
            <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
        </div>
    </div>
    
    <script>
        // Form validation
        document.getElementById('db-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // Simple validation example - could be expanded
            const required = ['db_host', 'db_name', 'db_user'];
            let valid = true;
            
            required.forEach(field => {
                if (!document.getElementById(field).value.trim()) {
                    valid = false;
                }
            });
            
            if (valid) this.submit();
            else alert('Please fill in all required fields');
        });
        
        document.getElementById('admin-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const pass = document.getElementById('admin_pass').value;
            const confirm = document.getElementById('admin_pass_confirm').value;
            
            if (pass !== confirm) {
                alert('Passwords do not match');
                return;
            }
            
            // Password strength check
            if (pass.length < 8) {
                alert('Password must be at least 8 characters long');
                return;
            }
            
            this.submit();
        });
        
        // Toggle password visibility
        function togglePassword(id) {
            const input = document.getElementById(id);
            const toggle = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }

        // Test database connection
        document.querySelector('.test-connection')?.addEventListener('click', function() {
            const host = document.getElementById('db_host').value;
            const user = document.getElementById('db_user').value;
            const password = document.getElementById('db_pass').value;
            const resultSpan = document.getElementById('connection-result');
            
            resultSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing connection...';
            
            // Create a form and submit it to a special endpoint
            const formData = new FormData();
            formData.append('action', 'test_db_connection');
            formData.append('db_host', host);
            formData.append('db_user', user);
            formData.append('db_pass', password);
            
            fetch('test_db.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultSpan.innerHTML = '<span style="color: var(--accent);"><i class="fas fa-check-circle"></i> Connection successful!</span>';
                } else {
                    resultSpan.innerHTML = '<span style="color: var(--error);"><i class="fas fa-times-circle"></i> ' + data.message + '</span>';
                }
            })
            .catch(error => {
                resultSpan.innerHTML = '<span style="color: var(--error);"><i class="fas fa-times-circle"></i> Test failed: Network error</span>';
            });
        });
    </script>
</body>
</html>