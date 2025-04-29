<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Display all errors for debugging - remove in production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Check if privacy_settings table exists and create it if needed
$db->query("CREATE TABLE IF NOT EXISTS privacy_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Check if default privacy settings exist
$result = $db->query("SELECT COUNT(*) as count FROM privacy_settings");
$row = $result->fetch_assoc();

// Add default settings if table is empty
if ($row['count'] == 0) {
    $defaultSettings = [
        ['privacy_policy', ''],
        ['enable_tracking', '1'],
        ['enable_geo_analytics', '1'],
        ['require_explicit_consent', '1'],
        ['data_retention_period', '12'],
        ['anonymize_ip', '0'],
        ['cookie_notice', '1'],
        ['cookie_notice_text', 'We use cookies to improve your experience and analyze website traffic.'],
        ['consent_prompt_text', 'I consent to receiving newsletters and tracking.']
    ];
    
    $stmt = $db->prepare("INSERT INTO privacy_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->bind_param("ss", $setting[0], $setting[1]);
        $stmt->execute();
    }
    
    $message = 'Default privacy settings created.';
    $messageType = 'success';
}

// Check if social_sharing_enabled setting exists
$result = $db->query("SELECT COUNT(*) as count FROM settings WHERE name = 'social_sharing_enabled'");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    // Default to enabled
    $db->query("INSERT INTO settings (name, value) VALUES ('social_sharing_enabled', '1')");
}

// Get current settings
$settingsResult = $db->query("SELECT name, value FROM settings WHERE name IN ('privacy_policy', 'data_retention', 'allow_export', 'allow_deletion')");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['name']] = $row['value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update privacy policy
    if (isset($_POST['privacy_policy'])) {
        $privacyPolicy = $_POST['privacy_policy'];
        updateSetting('privacy_policy', $privacyPolicy);
    }
    
    // Update data retention policy
    if (isset($_POST['data_retention'])) {
        $dataRetention = (int)$_POST['data_retention'];
        updateSetting('data_retention', $dataRetention);
    }
    
    // Update user data controls
    $allowExport = isset($_POST['allow_export']) ? 1 : 0;
    $allowDeletion = isset($_POST['allow_deletion']) ? 1 : 0;
    
    updateSetting('allow_export', $allowExport);
    updateSetting('allow_deletion', $allowDeletion);
    
    $message = 'Privacy settings updated successfully';
    $messageType = 'success';
    
    // Refresh settings
    $settingsResult = $db->query("SELECT name, value FROM settings WHERE name IN ('privacy_policy', 'data_retention', 'allow_export', 'allow_deletion')");
    $settings = [];
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['name']] = $row['value'];
    }
}

// Helper function to update a setting
function updateSetting($name, $value) {
    global $db;
    
    // Check if setting exists
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE name = ?");
    $checkStmt->bind_param('s', $name);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();
    
    if ($count > 0) {
        $stmt = $db->prepare("UPDATE settings SET value = ? WHERE name = ?");
        $stmt->bind_param('ss', $value, $name);
    } else {
        $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (?, ?)");
        $stmt->bind_param('ss', $name, $value);
    }
    
    $stmt->execute();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Settings | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/tinymce/tinymce.min.js"></script>
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
                    <h1>Privacy Settings</h1>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-shield-alt"></i> Privacy & Data Management</h2>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="privacy_policy">Privacy Policy:</label>
                            <textarea id="privacy_policy" name="privacy_policy"><?php echo htmlspecialchars($settings['privacy_policy'] ?? ''); ?></textarea>
                            <small>This will be displayed to subscribers during signup and in the newsletter footer.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_retention">Data Retention Period (days):</label>
                            <input type="number" id="data_retention" name="data_retention" value="<?php echo (int)($settings['data_retention'] ?? 365); ?>" min="30">
                            <small>How long to keep subscriber data after they unsubscribe (in days). Minimum: 30 days.</small>
                        </div>
                        
                        <div class="form-group">
                            <h3>User Data Controls</h3>
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="allow_export" <?php echo (!empty($settings['allow_export']) && $settings['allow_export'] == '1') ? 'checked' : ''; ?>>
                                    Allow subscribers to export their data
                                </label>
                            </div>
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="allow_deletion" <?php echo (!empty($settings['allow_deletion']) && $settings['allow_deletion'] == '1') ? 'checked' : ''; ?>>
                                    Allow subscribers to request account deletion
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
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
            tinymce.init({
                selector: '#privacy_policy',
                height: 400,
                plugins: 'lists link',
                toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link',
            });
        });
    </script>
</body>
</html>