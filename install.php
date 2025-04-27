<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

    // Test database connection
    $db = new mysqli($db_host, $db_user, $db_pass);
    if ($db->connect_error) {
        die('Connection failed: ' . $db->connect_error);
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
        "INSERT INTO users (username, email, password, role) VALUES ('$admin_user', '$admin_email', '$admin_pass', 'admin')",
        "INSERT INTO settings (name, value) VALUES ('title', 'Newsletter Dashboard'), ('background', '../images/forest.png')"
    ];

    foreach ($queries as $query) {
        if ($db->query($query) === FALSE) {
            error_log('Error: ' . $db->error);
            die('Error: ' . $db->error);
        }
    }
    
    // Create default "Thanks For Subscribing" theme
    $defaultTheme = file_get_contents('thanks_for_subscribing.html');
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
        die('Failed to write db.php file.');
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
        die('Failed to write config file.');
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
        die('Failed to write init.php file.');
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Newsletter Software</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Add basic styles in case CSS file isn't available yet */
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        form { display: grid; gap: 10px; }
        label { font-weight: bold; }
        input { padding: 8px; }
        button { padding: 10px; background: #4CAF50; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <main>
        <h2>Install Newsletter Software</h2>
        <form method="post">
            <fieldset>
                <legend>Database Configuration</legend>
                <label for="db_host">Database Host:</label>
                <input type="text" id="db_host" name="db_host" value="localhost" required>
                
                <label for="db_user">Database User:</label>
                <input type="text" id="db_user" name="db_user" required>
                
                <label for="db_pass">Database Password:</label>
                <input type="password" id="db_pass" name="db_pass">
                
                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" required>
            </fieldset>
            
            <fieldset>
                <legend>Admin Account</legend>
                <label for="admin_user">Admin Username:</label>
                <input type="text" id="admin_user" name="admin_user" required>
                
                <label for="admin_pass">Admin Password:</label>
                <input type="password" id="admin_pass" name="admin_pass" required>
                
                <label for="admin_email">Admin Email:</label>
                <input type="email" id="admin_email" name="admin_email" required>
            </fieldset>
            
            <fieldset>
                <legend>SMTP Configuration</legend>
                <label for="smtp_host">SMTP Host:</label>
                <input type="text" id="smtp_host" name="smtp_host" required>
                
                <label for="smtp_user">SMTP User:</label>
                <input type="text" id="smtp_user" name="smtp_user" required>
                
                <label for="smtp_pass">SMTP Password:</label>
                <input type="password" id="smtp_pass" name="smtp_pass" required>
                
                <label for="smtp_port">SMTP Port:</label>
                <input type="text" id="smtp_port" name="smtp_port" value="587" required>
                
                <label for="smtp_secure">SMTP Secure (tls/ssl):</label>
                <input type="text" id="smtp_secure" name="smtp_secure" value="tls" required>
            </fieldset>
            
            <button type="submit">Install</button>
        </form>
    </main>
    <footer>
        <p style="text-align: center;">Powered by LumiNewsletter</p>
    </footer>
</body>
</html>