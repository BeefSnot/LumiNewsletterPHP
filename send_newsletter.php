<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/init.php';  // Replace vendor/autoload.php
require_once 'includes/functions.php';  // MOVE THIS LINE HERE

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

// Load content blocks
$contentBlocksResult = $db->query("SELECT id, name, type FROM content_blocks ORDER BY name ASC");
$contentBlocks = [];
while ($block = $contentBlocksResult->fetch_assoc()) {
    $contentBlocks[] = $block;
}

// Load personalization tags
$tagsResult = $db->query("SELECT tag_name, description FROM personalization_tags ORDER BY tag_name ASC");
$personalizationTags = [];
while ($tag = $tagsResult->fetch_assoc()) {
    $personalizationTags[] = $tag;
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
        $stmt = $db->prepare('INSERT INTO newsletters (subject, content, sender_id, theme_id) VALUES (?, ?, ?, ?)');
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

        // Loop through recipients and send emails
        foreach ($recipients as $email) {
            $mail = new PHPMailer(true);
            
            try {
                // Configure SMTP settings
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_user'];
                $mail->Password = $config['smtp_pass'];
                $mail->SMTPSecure = $config['smtp_secure'];
                $mail->Port = $config['smtp_port'];
                
                // Recipients and content
                $mail->setFrom($config['smtp_user'], 'Newsletter');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                
                // Get subscriber data for personalization
                $stmt = $db->prepare("SELECT * FROM group_subscriptions WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $subscriber = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                // Process personalization tags
                $personalizedBody = $body;
                if ($subscriber) {
                    $personalizedBody = processPersonalization($body, $subscriber, $db);
                    
                    require_once 'includes/social_sharing.php';
                    require_once 'includes/social_widget.php';

                    // Make sure social tables exist first
                    $checkSocial = $db->query("SHOW TABLES LIKE 'social_shares'");
                    if ($checkSocial->num_rows === 0) {
                        // Create required tables silently
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
                    }

                    // Add social buttons with complete inline styles (email-friendly)
                    $socialButtons = '<style>
                    /* Social Sharing Buttons */
                    .social-sharing {margin-top:30px;padding-top:20px;border-top:1px solid #eee;text-align:center}
                    .social-sharing h4 {font-size:16px;margin-bottom:10px;color:#333}
                    .social-buttons {display:flex;justify-content:center;flex-wrap:wrap;margin-top:10px}
                    .social-btn {display:inline-block !important;padding:8px 15px !important;border-radius:4px !important;color:white !important;text-decoration:none !important;font-weight:500 !important;margin:0 5px !important;font-family:Arial,sans-serif !important}
                    .facebook {background-color:#3b5998 !important}
                    .twitter {background-color:#1da1f2 !important}
                    .linkedin {background-color:#0077b5 !important}
                    .email {background-color:#777777 !important}
                    </style>';

                    // Add "View in browser" link to ensure tracking works
                    $viewInBrowser = "<div style='text-align:center;margin-bottom:15px;font-size:12px;color:#888'>Can't see this email properly? <a href='{$site_url}/newsletter_view.php?id={$newsletter_id}'>View it in your browser</a></div>";

                    // Get the social buttons HTML
                    $socialButtons .= $viewInBrowser . getSocialShareButtons($newsletter_id, $subject, $db, [
                        'facebook' => true,
                        'twitter' => true,
                        'linkedin' => true,
                        'email' => true,
                        'size' => 'large',
                        'style' => 'default'
                    ]);

                    // Insert before closing body tag
                    $pattern = '/<\/\s*body\s*>/i';
                    if (preg_match($pattern, $personalizedBody)) {
                        $personalizedBody = preg_replace($pattern, $socialButtons . '</body>', $personalizedBody);
                    } else {
                        // If no closing body tag, append to the end
                        $personalizedBody .= $socialButtons;
                    }
                    
                    $mail->Body = $personalizedBody;
                }
                
                $mail->send();
                $success = true;
            } catch (Exception $e) {
                error_log('Error sending to ' . $email . ': ' . $mail->ErrorInfo);
                $failCount++;
            }
        }

        // After successfully sending the newsletter, update the sent_at field
        $updateSentTime = $db->prepare("UPDATE newsletters SET sent_at = NOW() WHERE id = ?");
        $updateSentTime->bind_param('i', $newsletter_id);
        $updateSentTime->execute();
        $updateSentTime->close();
    }
}

// Fetch available groups
$groupsResult = $db->query("SELECT id, name FROM `groups`");
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
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/tinymce/tinymce/js/tinymce/tinymce.min.js"></script>
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
    <style>
        .content-blocks-panel {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: var(--radius);
            margin-bottom: 15px;
        }
        
        .content-blocks-header {
            padding: 10px 15px;
            background: #f5f7fa;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
        }
        
        .content-blocks-list {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .content-block-item {
            padding: 8px 10px;
            border-left: 3px solid var(--primary);
            margin-bottom: 8px;
            background: #f9f9f9;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .content-block-item:hover {
            background: #eff3f9;
        }
        
        .content-block-name {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .block-type-badge {
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 10px;
            background: var(--gray-light);
            margin-left: 5px;
        }
        
        .block-type-badge.static { background: #e1f5fe; color: #0288d1; }
        .block-type-badge.dynamic { background: #f3e5f5; color: #7b1fa2; }
        .block-type-badge.conditional { background: #e8f5e9; color: #388e3c; }
        
        .personalization-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .tag-item {
            padding: 4px 8px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.2s;
            white-space: nowrap;
        }
        
        .tag-item:hover {
            background: #e0e0e0;
        }
    </style>
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
                            <label>Personalization Tags:</label>
                            <div class="personalization-tags">
                                <?php if (empty($personalizationTags)): ?>
                                    <span class="tag-item" onclick="insertPersonalizationTag('{{first_name}}')">{{first_name}}</span>
                                    <span class="tag-item" onclick="insertPersonalizationTag('{{last_name}}')">{{last_name}}</span>
                                    <span class="tag-item" onclick="insertPersonalizationTag('{{email}}')">{{email}}</span>
                                    <span class="tag-item" onclick="insertPersonalizationTag('{{subscription_date}}')">{{subscription_date}}</span>
                                    <span class="tag-item" onclick="insertPersonalizationTag('{{unsubscribe_link}}')">{{unsubscribe_link}}</span>
                                    <span class="tag-item" onclick="insertPersonalizationTag('{{current_date}}')">{{current_date}}</span>
                                <?php else: ?>
                                    <?php foreach ($personalizationTags as $tag): ?>
                                        <span class="tag-item" onclick="insertPersonalizationTag('{{<?php echo $tag['tag_name']; ?>}}')" title="<?php echo htmlspecialchars($tag['description']); ?>">
                                            {{<?php echo $tag['tag_name']; ?>}}
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($contentBlocks)): ?>
                        <div class="content-blocks-panel">
                            <div class="content-blocks-header">
                                <i class="fas fa-puzzle-piece"></i> Content Blocks
                            </div>
                            <div class="content-blocks-list">
                                <?php foreach ($contentBlocks as $block): ?>
                                    <div class="content-block-item" onclick="insertContentBlock(<?php echo $block['id']; ?>)">
                                        <span class="content-block-name"><?php echo htmlspecialchars($block['name']); ?></span>
                                        <span class="block-type-badge <?php echo $block['type']; ?>">
                                            <?php echo ucfirst($block['type']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

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
    
    <script src="assets/js/sidebar.js"></script>
    
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

        function insertPersonalizationTag(tag) {
            tinymce.activeEditor.execCommand('mceInsertContent', false, tag);
        }

        function insertContentBlock(blockId) {
            // AJAX request to get content block HTML
            fetch('get_content_block.php?id=' + blockId)
                .then(response => response.text())
                .then(blockHtml => {
                    if (blockHtml) {
                        tinymce.activeEditor.execCommand('mceInsertContent', false, blockHtml);
                    } else {
                        alert('Error loading content block');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading content block');
                });
        }
    </script>
</body>
</html>