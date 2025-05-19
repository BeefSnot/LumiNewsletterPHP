<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access marketplace settings
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Check if marketplace_settings table exists, create if not
$db->query("CREATE TABLE IF NOT EXISTS marketplace_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Set default settings if none exist
$result = $db->query("SELECT COUNT(*) as count FROM marketplace_settings");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $defaultSettings = [
        ['marketplace_enabled', '1'],
        ['auto_sync_subscribers', '1'],
        ['sync_interval', 'daily'],
        ['product_recommendations', '1'],
        ['abandoned_cart_recovery', '0'],
        ['order_follow_up', '1']
    ];
    
    $stmt = $db->prepare("INSERT INTO marketplace_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->bind_param("ss", $setting[0], $setting[1]);
        $stmt->execute();
    }
    
    $message = 'Default marketplace settings created.';
    $messageType = 'success';
}

// Get current settings
$settingsResult = $db->query("SELECT setting_key, setting_value FROM marketplace_settings");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process marketplace global settings
    $marketplaceEnabled = isset($_POST['marketplace_enabled']) ? '1' : '0';
    $autoSyncSubscribers = isset($_POST['auto_sync_subscribers']) ? '1' : '0';
    $syncInterval = $_POST['sync_interval'] ?? 'daily';
    $productRecommendations = isset($_POST['product_recommendations']) ? '1' : '0';
    $abandonedCartRecovery = isset($_POST['abandoned_cart_recovery']) ? '1' : '0';
    $orderFollowUp = isset($_POST['order_follow_up']) ? '1' : '0';
    
    // Update settings
    $updatedSettings = [
        'marketplace_enabled' => $marketplaceEnabled,
        'auto_sync_subscribers' => $autoSyncSubscribers,
        'sync_interval' => $syncInterval,
        'product_recommendations' => $productRecommendations,
        'abandoned_cart_recovery' => $abandonedCartRecovery,
        'order_follow_up' => $orderFollowUp
    ];
    
    foreach ($updatedSettings as $key => $value) {
        $db->query("UPDATE marketplace_settings SET setting_value = '$value' WHERE setting_key = '$key'");
    }
    
    $message = 'Marketplace settings updated successfully';
    $messageType = 'success';
    
    // Refresh settings
    $settingsResult = $db->query("SELECT setting_key, setting_value FROM marketplace_settings");
    $settings = [];
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Settings | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
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
        <?php include 'includes/sidebar.php'; ?>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Marketplace Settings</h1>
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
                    <h2><i class="fas fa-shopping-cart"></i> E-commerce Integration Settings</h2>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Enable Marketplace Integrations:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="marketplace_enabled" <?php echo (!empty($settings['marketplace_enabled']) && $settings['marketplace_enabled'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Enable or disable all marketplace integrations</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Auto-Sync Subscribers:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="auto_sync_subscribers" <?php echo (!empty($settings['auto_sync_subscribers']) && $settings['auto_sync_subscribers'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Automatically sync customers from e-commerce platforms as subscribers</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sync_interval">Sync Interval:</label>
                            <select id="sync_interval" name="sync_interval">
                                <option value="hourly" <?php echo ($settings['sync_interval'] ?? '') === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                <option value="daily" <?php echo ($settings['sync_interval'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo ($settings['sync_interval'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            </select>
                            <small>How often to sync data from e-commerce platforms</small>
                        </div>
                        
                        <h3>Newsletter Features</h3>
                        
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Product Recommendations:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="product_recommendations" <?php echo (!empty($settings['product_recommendations']) && $settings['product_recommendations'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Add personalized product recommendations to newsletters</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Abandoned Cart Recovery:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="abandoned_cart_recovery" <?php echo (!empty($settings['abandoned_cart_recovery']) && $settings['abandoned_cart_recovery'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Send automated abandoned cart recovery emails</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Order Follow-Up:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="order_follow_up" <?php echo (!empty($settings['order_follow_up']) && $settings['order_follow_up'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Send automated order follow-up emails</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card-grid">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-shopping-bag"></i> Shopify</h2>
                    </div>
                    <div class="card-body">
                        <p>Connect your Shopify store to sync customers and send targeted newsletters.</p>
                        <a href="shopify_integration.php" class="btn btn-primary">Configure Shopify</a>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fab fa-wordpress"></i> WooCommerce</h2>
                    </div>
                    <div class="card-body">
                        <p>Integrate with WooCommerce to sync your WordPress store customers.</p>
                        <a href="woocommerce_integration.php" class="btn btn-primary">Configure WooCommerce</a>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-store"></i> Etsy</h2>
                    </div>
                    <div class="card-body">
                        <p>Connect your Etsy shop to sync customers and promote your handmade products.</p>
                        <a href="etsy_integration.php" class="btn btn-primary">Configure Etsy</a>
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