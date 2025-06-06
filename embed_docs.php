<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}


// Get privacy settings
$requireExplicitConsent = false;
$result = $db->query("SELECT setting_value FROM privacy_settings WHERE setting_key = 'require_explicit_consent'");
if ($result && $result->num_rows > 0) {
    $requireExplicitConsent = ($result->fetch_assoc()['setting_value'] === '1');
}

$consentText = 'I consent to receiving newsletters and agree that my email engagement may be tracked for analytics purposes.';
$result = $db->query("SELECT setting_value FROM privacy_settings WHERE setting_key = 'consent_prompt_text'");
if ($result && $result->num_rows > 0) {
    $consentText = $result->fetch_assoc()['setting_value'];
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
    <title>Embed Widget | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Other head content remains the same -->
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

                    <h3>Method 4: Embed Code with Privacy Checkboxes</h3>
                    <p>Use this code snippet to embed the LumiNewsletter subscription form with privacy checkboxes:</p>
                    <div class="code-container">
                        <button class="copy-button" onclick="copyCode('embed-code')">Copy</button>
                        <pre id="embed-code"><?php
$embedCode = <<<HTML
<div class="lumi-subscribe-form" style="max-width: 400px; margin: 20px auto; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); background-color: #fff;">
    <h3 style="margin-top: 0; color: #2a5885;">Subscribe to Our Newsletter</h3>
    <form action="$siteUrl/subscribe.php?group=$defaultGroupId" method="post" style="margin: 0;">
        <div style="margin-bottom: 15px;">
            <label for="email" style="display: block; margin-bottom: 5px; font-weight: bold;">Email Address: *</label>
            <input type="email" id="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="name" style="display: block; margin-bottom: 5px; font-weight: bold;">Name:</label>
            <input type="text" id="name" name="name" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
        </div>
HTML;

// Add privacy checkbox if required
if ($requireExplicitConsent) {
    $embedCode .= <<<HTML
        <div style="margin-bottom: 15px;">
            <label style="display: flex; align-items: flex-start;">
                <input type="checkbox" name="tracking_consent" style="margin-right: 10px; margin-top: 3px;">
                <span style="font-size: 14px; color: #555;">$consentText</span>
            </label>
        </div>
HTML;
}

$embedCode .= <<<HTML
        <div>
            <button type="submit" style="background-color: #2a5885; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">Subscribe</button>
        </div>
        <div style="margin-top: 10px; font-size: 12px; color: #777; text-align: center;">
            By subscribing, you agree to our <a href="$siteUrl/privacy_policy.php" target="_blank" style="color: #2a5885; text-decoration: none;">Privacy Policy</a>
        </div>
    </form>
</div>
HTML;

echo htmlspecialchars($embedCode);
?></pre>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script src="assets/js/sidebar.js"></script>
</body>
</html>