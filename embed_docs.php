<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

// Get site URL from settings
$settingsResult = $db->query("SELECT value FROM settings WHERE name = 'site_url'");
if ($settingsResult && $settingsResult->num_rows > 0) {
    $siteUrl = $settingsResult->fetch_assoc()['value'];
} else {
    // Fallback to auto-detected URL if setting is not found
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    $siteUrl = $protocol . $host . $path;
}

// Ensure URL doesn't end with a slash
$siteUrl = rtrim($siteUrl, '/');

// Set widget URLs
$widgetUrl = $siteUrl . '/widget.php';
// Update the scriptUrl to point to the PHP version
$scriptUrl = $siteUrl . '/assets/js/lumi-widget.js.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Embed Instructions | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        code {
            background-color: #f5f7fa;
            border-radius: 4px;
            padding: 2px 6px;
            font-family: monospace;
            color: #333;
        }
        pre {
            background-color: #f5f7fa;
            border-radius: 8px;
            padding: 15px;
            font-family: monospace;
            overflow-x: auto;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        .copy-button {
            display: block;
            margin-top: 5px;
            padding: 5px 10px;
            font-size: 14px;
            background: var(--gray-light);
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            float: right;
        }
        .copy-button:hover {
            background: #e0e0e0;
        }
        .code-container {
            position: relative;
            margin-bottom: 25px;
        }
        .embed-preview {
            margin-top: 30px;
            border: 2px dashed #ccc;
            padding: 20px;
            border-radius: 8px;
        }
        .embed-preview h3 {
            margin-bottom: 20px;
            color: var(--gray);
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
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item active"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <li><a href="logout.php" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Embeddable Newsletter Widget</h1>
                </div>
            </header>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-code"></i> Embedding Instructions</h2>
                </div>
                <div class="card-body">
                    <p>Use these code snippets to embed the LumiNewsletter subscription form on any website.</p>
                    
                    <h3>Method 1: iFrame Embed (Easiest)</h3>
                    <p>Simply copy and paste this HTML where you want the form to appear:</p>
                    <div class="code-container">
                        <button class="copy-button" onclick="copyCode('iframe-code')">Copy</button>
                        <pre id="iframe-code">&lt;iframe src="<?php echo htmlspecialchars($widgetUrl); ?>" style="width: 100%; max-width: 400px; height: 320px; border: none; overflow: hidden;" scrolling="no" frameborder="0"&gt;&lt;/iframe&gt;</pre>
                    </div>
                    
                    <h3>Method 2: JavaScript Embed (Recommended)</h3>
                    <p>This method provides better integration with your website:</p>
                    <ol>
                        <li>Add this code in the &lt;head&gt; or at the end of the &lt;body&gt; of your website:</li>
                        <div class="code-container">
                            <button class="copy-button" onclick="copyCode('js-embed-1')">Copy</button>
                            <pre id="js-embed-1">&lt;script src="<?php echo htmlspecialchars($scriptUrl); ?>"&gt;&lt;/script&gt;</pre>
                        </div>
                        
                        <li>Add this where you want the form to appear:</li>
                        <div class="code-container">
                            <button class="copy-button" onclick="copyCode('js-embed-2')">Copy</button>
                            <pre id="js-embed-2">&lt;div id="lumi-newsletter-widget"&gt;&lt;/div&gt;
&lt;script&gt;
  createLumiNewsletterWidget('lumi-newsletter-widget');
&lt;/script&gt;</pre>
                        </div>
                    </ol>
                    
                    <h3>Method 3: Direct Include with Options</h3>
                    <p>For more control, you can customize the widget dimensions:</p>
                    <div class="code-container">
                        <button class="copy-button" onclick="copyCode('js-embed-3')">Copy</button>
                        <pre id="js-embed-3">&lt;div id="lumi-newsletter-widget" data-width="100%" data-max-width="350px" data-height="350px"&gt;&lt;/div&gt;
&lt;script src="<?php echo htmlspecialchars($scriptUrl); ?>"&gt;&lt;/script&gt;
&lt;script&gt;
  createLumiNewsletterWidget('lumi-newsletter-widget');
&lt;/script&gt;</pre>
                    </div>
                    
                    <div class="embed-preview">
                        <h3>Preview of the Embedded Widget</h3>
                        <iframe src="<?php echo htmlspecialchars($widgetUrl); ?>" style="width: 100%; max-width: 400px; height: 320px; border: none; overflow: hidden;" scrolling="no" frameborder="0"></iframe>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>

    <script>
        function copyCode(elementId) {
            const codeElement = document.getElementById(elementId);
            const textArea = document.createElement('textarea');
            textArea.value = codeElement.textContent;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            // Show feedback
            const button = document.querySelector(`#${elementId} + .copy-button, #${elementId} ~ .copy-button`);
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            setTimeout(() => {
                button.textContent = originalText;
            }, 2000);
        }
    </script>
</body>
</html>