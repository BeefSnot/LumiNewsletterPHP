<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/init.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$status = '';

// Load current SMTP settings
$configFile = 'includes/config.php';
$config = require $configFile;

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // Update SMTP settings
        $config['smtp_host'] = $_POST['smtp_host'];
        $config['smtp_user'] = $_POST['smtp_user'];
        $config['smtp_port'] = (int)$_POST['smtp_port'];
        $config['smtp_secure'] = $_POST['smtp_secure'];
        
        // Only update password if one was provided
        if (!empty($_POST['smtp_pass'])) {
            $config['smtp_pass'] = $_POST['smtp_pass'];
        }
        
        // Write the updated config back to file
        $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($configFile, $configContent)) {
            $message = "SMTP settings saved successfully";
            $status = "success";
        } else {
            $message = "Failed to save SMTP settings. Check file permissions.";
            $status = "error";
        }
    } elseif (isset($_POST['test_connection'])) {
        // Test the SMTP connection
        $testEmail = $_POST['test_email'];
        
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->SMTPDebug = SMTP::DEBUG_OFF; // Disable debug output
            $mail->isSMTP();
            $mail->Host = $_POST['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $_POST['smtp_user'];
            $mail->Password = !empty($_POST['smtp_pass']) ? $_POST['smtp_pass'] : $config['smtp_pass'];
            $mail->SMTPSecure = $_POST['smtp_secure'];
            $mail->Port = (int)$_POST['smtp_port'];

            // Recipients
            $mail->setFrom($_POST['smtp_user'], 'SMTP Test');
            $mail->addAddress($testEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test from LumiNewsletter';
            $mail->Body = 'This is a test email to confirm SMTP settings are working correctly.';

            $mail->send();
            $message = "Test email sent successfully to $testEmail";
            $status = "success";
        } catch (Exception $e) {
            $message = "Test email failed: " . $mail->ErrorInfo;
            $status = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Settings | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Mobile navigation toggle button -->
    <button class="mobile-nav-toggle" id="mobileNavToggle">
        <i class="fas fa-bars" id="menuIcon"></i>
    </button>
    
    <!-- Backdrop for mobile menu -->
    <div class="backdrop" id="backdrop"></div>
    
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
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
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item active"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <li><a href="logout.php" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>LumiNewsletter Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>SMTP Settings</h1>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="notification <?php echo $status; ?>">
                    <i class="fas fa-<?php echo $status == 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i> 
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-cog"></i> Email Server Configuration</h2>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host:</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($config['smtp_host'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_user">SMTP Username:</label>
                            <input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($config['smtp_user'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_pass">SMTP Password (leave blank to keep current):</label>
                            <input type="password" id="smtp_pass" name="smtp_pass" placeholder="Leave blank to keep current">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_port">SMTP Port:</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($config['smtp_port'] ?? 587); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_secure">SMTP Security:</label>
                            <select id="smtp_secure" name="smtp_secure" required>
                                <option value="tls" <?php echo ($config['smtp_secure'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($config['smtp_secure'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="" <?php echo ($config['smtp_secure'] ?? '') === '' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="save_settings" class="btn btn-primary">Save SMTP Settings</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-paper-plane"></i> Test Email Configuration</h2>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="test_email">Test Email Address:</label>
                            <input type="email" id="test_email" name="test_email" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="test_connection" class="btn btn-primary">Send Test Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <!-- Add mobile menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileNavToggle = document.getElementById('mobileNavToggle');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('backdrop');
            const menuIcon = document.getElementById('menuIcon');
            
            function toggleMenu() {
                sidebar.classList.toggle('active');
                backdrop.classList.toggle('active');
                
                if (sidebar.classList.contains('active')) {
                    menuIcon.classList.remove('fa-bars');
                    menuIcon.classList.add('fa-times');
                } else {
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            }
            
            mobileNavToggle.addEventListener('click', toggleMenu);
            backdrop.addEventListener('click', toggleMenu);
            
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 991 && sidebar.classList.contains('active')) {
                        toggleMenu();
                    }
                });
            });
        });
    </script>
</body>
</html>