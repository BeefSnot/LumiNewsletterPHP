<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error_message = '';
$info_message = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        // Test database connection
        $db = new mysqli($db_host, $db_user, $db_pass);
        if ($db->connect_error) {
            throw new Exception('Connection failed: ' . $db->connect_error);
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
            "CREATE TABLE IF NOT EXISTS groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS newsletters (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                sender_id INT NOT NULL,
                theme_id INT,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id),
                FOREIGN KEY (theme_id) REFERENCES themes(id)
            )",
            "CREATE TABLE IF NOT EXISTS group_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100) NOT NULL,
                group_id INT NOT NULL,
                UNIQUE KEY email_group (email, group_id),
                FOREIGN KEY (group_id) REFERENCES groups(id)
            )",
            "CREATE TABLE IF NOT EXISTS newsletter_groups (
                newsletter_id INT NOT NULL,
                group_id INT NOT NULL,
                PRIMARY KEY (newsletter_id, group_id),
                FOREIGN KEY (newsletter_id) REFERENCES newsletters(id),
                FOREIGN KEY (group_id) REFERENCES groups(id)
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
            "INSERT INTO users (username, email, password, role) VALUES ('$admin_user', '$admin_email', '$admin_pass', 'admin')",
            "INSERT INTO settings (name, value) VALUES ('title', 'Newsletter Dashboard'), ('background', 'assets/images/background.png')"
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

        // Create db.php file
        $dbContent = "<?php\n";
        $dbContent .= "\$db = new mysqli('$db_host', '$db_user', '$db_pass', '$db_name');\n";
        $dbContent .= "if (\$db->connect_error) {\n";
        $dbContent .= "    die('Connection failed: ' . \$db->connect_error);\n";
        $dbContent .= "}\n";

        if (file_put_contents('includes/db.php', $dbContent) === false) {
            throw new Exception('Failed to write db.php file.');
        }

        // Write database and SMTP configuration to config.php
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
        
        // Create init.php file that replaces Composer's autoloader
        $initContent = "<?php\n";
        $initContent .= "// Initialize required dependencies\n";
        $initContent .= "require_once __DIR__ . '/phpmailer/src/PHPMailer.php';\n";
        $initContent .= "require_once __DIR__ . '/phpmailer/src/SMTP.php';\n";
        $initContent .= "require_once __DIR__ . '/phpmailer/src/Exception.php';\n\n";
        $initContent .= "// Set up namespace aliases for cleaner code\n";
        $initContent .= "use PHPMailer\\PHPMailer\\PHPMailer;\n";
        $initContent .= "use PHPMailer\\PHPMailer\\SMTP;\n";
        $initContent .= "use PHPMailer\\PHPMailer\\Exception;\n";

        if (file_put_contents('includes/init.php', $initContent) === false) {
            throw new Exception('Failed to write init.php file.');
        }
        
        // Create a simple auth.php file if it doesn't exist
        if (!file_exists('includes/auth.php')) {
            $authContent = "<?php\n";
            $authContent .= "function isLoggedIn() {\n";
            $authContent .= "    return isset(\$_SESSION['user_id']);\n";
            $authContent .= "}\n";
            file_put_contents('includes/auth.php', $authContent);
        }
        
        // Create a simple footer.php if it doesn't exist
        if (!file_exists('includes/footer.php')) {
            $footerContent = "<footer>\n";
            $footerContent .= "    <p style=\"text-align: center;\">Powered by LumiNewsletter</p>\n";
            $footerContent .= "</footer>";
            file_put_contents('includes/footer.php', $footerContent);
        }

        echo '<div style="text-align:center;padding:20px;background:#dff0d8;color:#3c763d;margin:20px;">';
        echo '<h3>Installation successful!</h3>';
        echo '<p>Your newsletter system has been installed successfully.</p>';
        echo '<p><a href="login.php" style="color:#3c763d;font-weight:bold;">Click here to login</a></p>';
        echo '</div>';
        exit();
        
    } catch (Exception $e) {
        $error_message = 'Installation failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Newsletter Software</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        form { display: grid; gap: 10px; }
        fieldset { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
        legend { font-weight: bold; padding: 0 10px; }
        label { font-weight: bold; display: block; margin-top: 10px; }
        input { padding: 8px; width: 100%; box-sizing: border-box; }
        button { padding: 12px; background: #4CAF50; color: white; border: none; cursor: pointer; margin-top: 20px; }
        .error { background: #f2dede; border: 1px solid #ebccd1; color: #a94442; padding: 15px; margin-bottom: 20px; }
        .info { background: #d9edf7; border: 1px solid #bce8f1; color: #31708f; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <main>
        <h2>Install Newsletter Software</h2>
        
        <?php if ($error_message): ?>
            <div class="error">
                <strong>Error:</strong> <?php echo $error_message; ?>
                <p>Common reasons for database connection failures:</p>
                <ul>
                    <li>Incorrect database username or password</li>
                    <li>Database user doesn't have sufficient privileges</li>
                    <li>Database hostname is incorrect (some hosting providers use a specific hostname)</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($info_message): ?>
            <div class="info"><?php echo $info_message; ?></div>
        <?php endif; ?>
        
        <p>This will install your newsletter system. Please provide your database and administrator details below.</p>
        
        <form method="post">
            <fieldset>
                <legend>Database Configuration</legend>
                <label for="db_host">Database Host:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                
                <label for="db_user">Database User:</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" required>
                
                <label for="db_pass">Database Password:</label>
                <input type="password" id="db_pass" name="db_pass">
                
                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" required>
            </fieldset>
            
            <fieldset>
                <legend>Admin Account</legend>
                <label for="admin_user">Admin Username:</label>
                <input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? ''); ?>" required>
                
                <label for="admin_pass">Admin Password:</label>
                <input type="password" id="admin_pass" name="admin_pass" required>
                
                <label for="admin_email">Admin Email:</label>
                <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
            </fieldset>
            
            <fieldset>
                <legend>SMTP Configuration</legend>
                <label for="smtp_host">SMTP Host:</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($_POST['smtp_host'] ?? ''); ?>" required>
                
                <label for="smtp_user">SMTP User:</label>
                <input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($_POST['smtp_user'] ?? ''); ?>" required>
                
                <label for="smtp_pass">SMTP Password:</label>
                <input type="password" id="smtp_pass" name="smtp_pass" required>
                
                <label for="smtp_port">SMTP Port:</label>
                <input type="text" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($_POST['smtp_port'] ?? '587'); ?>" required>
                
                <label for="smtp_secure">SMTP Secure (tls/ssl):</label>
                <input type="text" id="smtp_secure" name="smtp_secure" value="<?php echo htmlspecialchars($_POST['smtp_secure'] ?? 'tls'); ?>" required>
            </fieldset>
            
            <button type="submit">Install</button>
        </form>
    </main>
    <footer>
        <p style="text-align: center;">Powered by LumiNewsletter</p>
    </footer>
</body>
</html>