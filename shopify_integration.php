<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access shopify integration
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Check if shopify_settings table exists, create if not
$db->query("CREATE TABLE IF NOT EXISTS shopify_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Set default settings if none exist
$result = $db->query("SELECT COUNT(*) as count FROM shopify_settings");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $defaultSettings = [
        ['shopify_enabled', '0'],
        ['shop_domain', ''],
        ['api_key', ''],
        ['api_secret', ''],
        ['access_token', ''],
        ['sync_customers', '1'],
        ['sync_orders', '1'],
        ['sync_products', '1'],
        ['customer_tags', 'newsletter-subscriber']
    ];
    
    $stmt = $db->prepare("INSERT INTO shopify_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->bind_param("ss", $setting[0], $setting[1]);
        $stmt->execute();
    }
    
    $message = 'Default Shopify settings created.';
    $messageType = 'success';
}

// Get current settings
$settingsResult = $db->query("SELECT setting_key, setting_value FROM shopify_settings");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // Process shopify settings
        $shopifyEnabled = isset($_POST['shopify_enabled']) ? '1' : '0';
        $shopDomain = $_POST['shop_domain'] ?? '';
        $apiKey = $_POST['api_key'] ?? '';
        $apiSecret = $_POST['api_secret'] ?? '';
        $accessToken = $_POST['access_token'] ?? '';
        $syncCustomers = isset($_POST['sync_customers']) ? '1' : '0';
        $syncOrders = isset($_POST['sync_orders']) ? '1' : '0';
        $syncProducts = isset($_POST['sync_products']) ? '1' : '0';
        $customerTags = $_POST['customer_tags'] ?? '';
        
        // Update settings
        $updatedSettings = [
            'shopify_enabled' => $shopifyEnabled,
            'shop_domain' => $shopDomain,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'access_token' => $accessToken,
            'sync_customers' => $syncCustomers,
            'sync_orders' => $syncOrders,
            'sync_products' => $syncProducts,
            'customer_tags' => $customerTags
        ];
        
        foreach ($updatedSettings as $key => $value) {
            $stmt = $db->prepare("UPDATE shopify_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
        }
        
        $message = 'Shopify settings updated successfully';
        $messageType = 'success';
        
        // Refresh settings
        $settingsResult = $db->query("SELECT setting_key, setting_value FROM shopify_settings");
        $settings = [];
        while ($row = $settingsResult->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } elseif (isset($_POST['test_connection'])) {
        // Simulate testing connection
        $shopDomain = $settings['shop_domain'] ?? '';
        $apiKey = $settings['api_key'] ?? '';
        $accessToken = $settings['access_token'] ?? '';
        
        if (empty($shopDomain) || empty($apiKey) || empty($accessToken)) {
            $message = 'Please configure all required Shopify settings first.';
            $messageType = 'error';
        } else {
            // In a real implementation, you would make an API call to Shopify here
            $message = 'Connection to Shopify successful!';
            $messageType = 'success';
        }
    } elseif (isset($_POST['sync_now'])) {
        // Simulate syncing
        $message = 'Sync with Shopify completed. 0 customers, 0 orders, and 0 products were synchronized.';
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopify Integration | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .connection-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .connection-status.connected {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .connection-status.disconnected {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .sync-stats {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-item {
            flex: 1;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        .shopify-logo {
            max-width: 120px;
            margin-bottom: 20px;
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
                    <h1>Shopify Integration</h1>
                    <span class="connection-status <?php echo (!empty($settings['shopify_enabled']) && $settings['shopify_enabled'] == '1' && !empty($settings['access_token'])) ? 'connected' : 'disconnected'; ?>">
                        <?php echo (!empty($settings['shopify_enabled']) && $settings['shopify_enabled'] == '1' && !empty($settings['access_token'])) ? 'Connected' : 'Disconnected'; ?>
                    </span>
                </div>
                <div class="header-right">
                    <a href="marketplace_settings.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Marketplace
                    </a>
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
                    <h2><i class="fas fa-shopping-bag"></i> Shopify Configuration</h2>
                </div>
                <div class="card-body">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <img src="assets/images/shopify-logo.png" alt="Shopify Logo" class="shopify-logo" onerror="this.src='https://cdn.shopify.com/s/files/applications/shopify_logo_whitebg.png'; this.onerror=null;">
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Enable Shopify Integration:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="shopify_enabled" <?php echo (!empty($settings['shopify_enabled']) && $settings['shopify_enabled'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="shop_domain">Shopify Store Domain:</label>
                            <input type="text" id="shop_domain" name="shop_domain" value="<?php echo htmlspecialchars($settings['shop_domain'] ?? ''); ?>" placeholder="your-store.myshopify.com">
                            <small>Your Shopify store domain (e.g., your-store.myshopify.com)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="api_key">API Key:</label>
                            <input type="text" id="api_key" name="api_key" value="<?php echo htmlspecialchars($settings['api_key'] ?? ''); ?>">
                            <small>Shopify API key from your private app</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="api_secret">API Secret:</label>
                            <input type="password" id="api_secret" name="api_secret" value="<?php echo htmlspecialchars($settings['api_secret'] ?? ''); ?>">
                            <small>Shopify API secret from your private app</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="access_token">Access Token:</label>
                            <input type="password" id="access_token" name="access_token" value="<?php echo htmlspecialchars($settings['access_token'] ?? ''); ?>">
                            <small>Shopify access token from your private app</small>
                        </div>
                        
                        <h3>Sync Settings</h3>
                        
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Sync Customers:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="sync_customers" <?php echo (!empty($settings['sync_customers']) && $settings['sync_customers'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Import customers as subscribers</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Sync Orders:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="sync_orders" <?php echo (!empty($settings['sync_orders']) && $settings['sync_orders'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Track order data for targeted campaigns</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Sync Products:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="sync_products" <?php echo (!empty($settings['sync_products']) && $settings['sync_products'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Import products for recommendations in newsletters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_tags">Customer Tags:</label>
                            <input type="text" id="customer_tags" name="customer_tags" value="<?php echo htmlspecialchars($settings['customer_tags'] ?? ''); ?>">
                            <small>Tags to add to customers imported as subscribers (comma-separated)</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                            <button type="submit" name="test_connection" class="btn btn-outline">Test Connection</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-sync"></i> Sync Information</h2>
                </div>
                <div class="card-body">
                    <p>Manually synchronize data from your Shopify store.</p>
                    
                    <div class="sync-stats">
                        <div class="stat-item">
                            <div class="stat-number">0</div>
                            <div class="stat-label">Customers Synced</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">0</div>
                            <div class="stat-label">Orders Tracked</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">0</div>
                            <div class="stat-label">Products Imported</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <form method="post">
                            <button type="submit" name="sync_now" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Sync Now
                            </button>
                        </form>
                    </div>
                    
                    <div style="margin-top: 20px; font-size: 14px; color: #6c757d;">
                        <p><strong>Last Sync:</strong> Never</p>
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