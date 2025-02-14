<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

    // Debugging: Log the database connection details (excluding password)
    error_log("Connecting to database at $db_host with user $db_user");

    $db = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($db->connect_error) {
        die('Connection failed: ' . $db->connect_error);
    }

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
        "INSERT INTO users (username, email, password, role) VALUES ('$admin_user', '$admin_email', '$admin_pass', 'admin')"
    ];

    foreach ($queries as $query) {
        if ($db->query($query) === FALSE) {
            error_log('Error: ' . $db->error);
            die('Error: ' . $db->error);
        }
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

    echo 'Installation successful!';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Newsletter Software</title>
    <link rel="stylesheet" href="assets/css/newsletter.css">
</head>
<body>
    <main>
        <h2>Install Newsletter Software</h2>
        <form method="post">
            <label for="db_host">Database Host:</label>
            <input type="text" id="db_host" name="db_host" required>
            
            <label for="db_user">Database User:</label>
            <input type="text" id="db_user" name="db_user" required>
            
            <label for="db_pass">Database Password:</label>
            <input type="password" id="db_pass" name="db_pass" required>
            
            <label for="db_name">Database Name:</label>
            <input type="text" id="db_name" name="db_name" required>
            
            <label for="admin_user">Admin Username:</label>
            <input type="text" id="admin_user" name="admin_user" required>
            
            <label for="admin_pass">Admin Password:</label>
            <input type="password" id="admin_pass" name="admin_pass" required>
            
            <label for="admin_email">Admin Email:</label>
            <input type="email" id="admin_email" name="admin_email" required>
            
            <label for="smtp_host">SMTP Host:</label>
            <input type="text" id="smtp_host" name="smtp_host" required>
            
            <label for="smtp_user">SMTP User:</label>
            <input type="text" id="smtp_user" name="smtp_user" required>
            
            <label for="smtp_pass">SMTP Password:</label>
            <input type="password" id="smtp_pass" name="smtp_pass" required>
            
            <label for="smtp_port">SMTP Port:</label>
            <input type="text" id="smtp_port" name="smtp_port" required>
            
            <label for="smtp_secure">SMTP Secure (tls/ssl):</label>
            <input type="text" id="smtp_secure" name="smtp_secure" required>
            
            <button type="submit">Install</button>
        </form>
    </main>
</body>
</html>