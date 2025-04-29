<?php
require_once 'includes/db.php';

// Get privacy policy text
$privacyPolicy = '';
$result = $db->query("SELECT setting_value FROM privacy_settings WHERE setting_key = 'privacy_policy'");
if ($result && $result->num_rows > 0) {
    $privacyPolicy = $result->fetch_assoc()['setting_value'];
}

// Get site title
$siteTitle = 'LumiNewsletter';
$result = $db->query("SELECT value FROM settings WHERE name = 'title'");
if ($result && $result->num_rows > 0) {
    $siteTitle = $result->fetch_assoc()['value'];
}

// Show a default privacy policy if none is set
if (empty($privacyPolicy)) {
    $privacyPolicy = "<h2>Privacy Policy</h2>
    <p>This privacy policy describes how we collect, use, and process your personal information when you use our newsletter service.</p>
    
    <h3>Information We Collect</h3>
    <p>We collect information you provide when subscribing to our newsletter, such as your email address and name.</p>
    
    <h3>How We Use Your Information</h3>
    <p>We use your information to send you newsletters you've requested and to improve our service.</p>
    
    <h3>Email Tracking</h3>
    <p>Our newsletters may use tracking technologies to collect information about whether you open emails and click links. This helps us improve our content and understand subscriber interests.</p>
    
    <h3>Your Rights</h3>
    <p>You can unsubscribe at any time using the link at the bottom of our emails. You can also request access to your data or request that we delete your data.</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | <?php echo htmlspecialchars($siteTitle); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #2a5885;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        h2, h3 {
            color: #2a5885;
            margin-top: 30px;
        }
        p {
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 50px;
            border-top: 1px solid #eee;
            padding-top: 20px;
            font-size: 0.9em;
            color: #777;
        }
    </style>
</head>
<body>
    <h1>Privacy Policy</h1>
    
    <div class="privacy-content">
        <?php echo $privacyPolicy; ?>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteTitle); ?></p>
    </div>
</body>
</html>