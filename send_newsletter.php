<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/init.php';  // Replace vendor/autoload.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user role
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

$config = require 'includes/config.php';

$message = '';

function addTrackingToEmail($html, $newsletter_id, $recipient_email, $site_url) {
    // URL encode the email
    $encoded_email = urlencode($recipient_email);
    
    // Add tracking pixel for opens
    $tracking_pixel = "<img src='$site_url/track_open.php?nid=$newsletter_id&email=$encoded_email' width='1' height='1' alt='' style='display:none;'>";
    $html .= $tracking_pixel;
    
    // Replace links with tracking links
    $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i';
    $html = preg_replace_callback($pattern, function($matches) use ($site_url, $newsletter_id, $encoded_email) {
        $url = $matches[2];
        $encoded_url = urlencode($url);
        $tracking_url = "$site_url/track_click.php?nid=$newsletter_id&email=$encoded_email&url=$encoded_url";
        return "<a href=\"$tracking_url\"";
    }, $html);
    
    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';
    $group_id = $_POST['group'] ?? '';
    $theme_id = $_POST['theme'] ?? null;

    if (empty($group_id)) {
        $message = 'Please select a group.';
    } else {
        // Fetch recipients based on group subscription
        $recipientsResult = $db->prepare('SELECT email FROM group_subscriptions WHERE group_id = ?');
        if ($recipientsResult === false) {
            error_log('Prepare failed: ' . htmlspecialchars($db->error));
            die('Prepare failed: ' . htmlspecialchars($db->error));
        }
        $recipientsResult->bind_param('i', $group_id);
        $recipientsResult->execute();
        $recipientsResult->bind_result($email);
        $recipients = [];
        while ($recipientsResult->fetch()) {
            $recipients[] = $email;
        }
        $recipientsResult->close();

        // Fetch the theme content if a theme is selected
        if (!empty($theme_id)) {
            $themeStmt = $db->prepare('SELECT content FROM themes WHERE id = ?');
            if ($themeStmt === false) {
                error_log('Prepare failed: ' . htmlspecialchars($db->error));
                die('Prepare failed: ' . htmlspecialchars($db->error));
            }
            $themeStmt->bind_param('i', $theme_id);
            $themeStmt->execute();
            $themeStmt->bind_result($themeContent);
            $themeStmt->fetch();
            $themeStmt->close();
        }

        // Insert the newsletter into the database
        $stmt = $db->prepare('INSERT INTO newsletters (subject, body, sender_id, theme_id) VALUES (?, ?, ?, ?)');
        if ($stmt === false) {
            error_log('Prepare failed: ' . htmlspecialchars($db->error));
            die('Prepare failed: ' . htmlspecialchars($db->error));
        }

        // Ensure theme_id is explicitly handled as NULL if empty
        $themeIdParam = ($theme_id !== '' && $theme_id !== null) ? $theme_id : null;

        $stmt->bind_param('ssii', $subject, $body, $_SESSION['user_id'], $themeIdParam);
        if ($stmt->execute() === false) {
            error_log('Execute failed: ' . htmlspecialchars($stmt->error));
            die('Execute failed: ' . htmlspecialchars($stmt->error));
        }
        $newsletter_id = $stmt->insert_id;
        $stmt->close();

        // Insert the newsletter-group relationship
        $stmt = $db->prepare('INSERT INTO newsletter_groups (newsletter_id, group_id) VALUES (?, ?)');
        if ($stmt === false) {
            error_log('Prepare failed: ' . htmlspecialchars($db->error));
            die('Prepare failed: ' . htmlspecialchars($db->error));
        }
        $stmt->bind_param('ii', $newsletter_id, $group_id);
        if ($stmt->execute() === false) {
            error_log('Execute failed: ' . htmlspecialchars($stmt->error));
            die('Execute failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();

        // Get site URL from settings for tracking links
        $settingsResult = $db->query("SELECT value FROM settings WHERE name = 'site_url'");
        if ($settingsResult && $settingsResult->num_rows > 0) {
            $site_url = $settingsResult->fetch_assoc()['value'];
        } else {
            // Fallback to auto-detected URL
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $path = dirname($_SERVER['PHP_SELF']);
            $site_url = $protocol . $host . rtrim($path, '/');
        }

        // Send the newsletter using PHPMailer
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            $mail->SMTPSecure = $config['smtp_secure'];
            $mail->Port = $config['smtp_port'];

            $mail->setFrom($config['smtp_user'], 'Newsletter');
            $mail->Subject = $subject;
            $mail->isHTML(true);

            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient); // Recipients are already email addresses
                
                // Add tracking to the newsletter content
                $trackableContent = addTrackingToEmail($body, $newsletter_id, $recipient, $site_url);
                
                $mail->Body = $trackableContent; // Use the trackable version
                if (!$mail->send()) {
                    $message .= 'Mailer Error (' . htmlspecialchars($recipient) . ') ' . $mail->ErrorInfo . '<br>';
                    error_log('Mailer Error (' . htmlspecialchars($recipient) . ') ' . $mail->ErrorInfo);
                }
                $mail->clearAddresses(); // Clear recipients after each send
            }

            if (empty($message)) {
                $message = 'Newsletter sent successfully';
            }
        } catch (Exception $e) {
            $message = 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
            error_log('Mailer Error: ' . $mail->ErrorInfo);
        }
    }
}

// Fetch available groups
$groupsResult = $db->query("SELECT id, name FROM groups");
$groups = [];
while ($row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}

// Fetch available themes
$themesResult = $db->query("SELECT id, name, content FROM themes");
$themes = [];
while ($row = $themesResult->fetch_assoc()) {
    $themes[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Newsletter | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <script src="https://cdn.tiny.cloud/1/8sjavbgsmciibkna0zhc3wcngf5se0nri4vanzzapds2ylul/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#body', // Ensure the selector matches the textarea ID
            plugins: 'print preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap hr pagebreak nonbreaking anchor toc insertdatetime advlist lists wordcount imagetools textpattern noneditable help charmap quickbars emoticons',
            toolbar: 'undo redo | bold italic underline strikethrough | fontselect fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  preview save print',
            height: 500,
            menubar: 'file edit view insert format tools table help',
            content_css: 'assets/css/newsletter-style.css', // Changed from style.css
            relative_urls: false,
            remove_script_host: false,
            convert_urls: true,
            setup: function (editor) {
                editor.on('change', function () {
                    tinymce.triggerSave();
                });
            }
        });

        function loadThemeContent(themeId) {
            // Clear editor if "No Theme" is selected
            if (!themeId) {
                tinymce.get('body').setContent('');
                return;
            }
            
            const themes = <?php echo json_encode($themes); ?>;
            const selectedTheme = themes.find(theme => theme.id == themeId);
            if (selectedTheme) {
                // This will replace any existing content with just the theme
                tinymce.get('body').setContent(selectedTheme.content);
            }
        }

        // Custom validation handler
        function validateAndSubmit(event) {
            event.preventDefault();
            const bodyContent = tinymce.get('body').getContent();
            if (!bodyContent.trim()) {
                alert('Please enter newsletter content.');
                return;
            }

            // Ensure theme_id is explicitly handled as NULL if empty
            const themeSelect = document.getElementById('theme');
            if (themeSelect.value === '') {
                themeSelect.value = null;
            }

            event.target.submit();
        }
    </script>
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
                    <?php if ($isAdmin): ?>
                    <li><a href="admin.php" class="nav-item"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <?php endif; ?>
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item active"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <?php if ($isAdmin): ?>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <?php endif; ?>
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
                    <h1>Send Newsletter</h1>
                </div>
            </header>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-paper-plane"></i> Send a New Newsletter</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="notification <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                            <i class="fas fa-<?php echo strpos($message, 'successfully') !== false ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" onsubmit="validateAndSubmit(event);">
                        <div class="form-group">
                            <label for="subject">Subject:</label>
                            <input type="text" id="subject" name="subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="theme">Theme:</label>
                            <select id="theme" name="theme" onchange="loadThemeContent(this.value)">
                                <option value="">No Theme</option>
                                <?php foreach ($themes as $theme): ?>
                                    <option value="<?php echo $theme['id']; ?>"><?php echo htmlspecialchars($theme['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="group">Recipient Group:</label>
                            <select id="group" name="group" required>
                                <option value="">-- Select Group --</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="body">Newsletter Content:</label>
                            <textarea id="body" name="body"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Newsletter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>

    <!-- Mobile menu JavaScript -->
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