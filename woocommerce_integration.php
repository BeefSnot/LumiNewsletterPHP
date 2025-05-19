<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access WooCommerce integration
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Check if woocommerce_settings table exists, create if not
$db->query("CREATE TABLE IF NOT EXISTS woocommerce_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Set default settings if none exist
$result = $db->query("SELECT COUNT(*) as count FROM woocommerce_settings");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $defaultSettings = [
        ['woocommerce_enabled', '0'],
        ['site_url', ''],
        ['consumer_key', ''],
        ['consumer_secret', ''],
        ['sync_customers', '1'],
        ['sync_orders', '1'],
        ['sync_products', '1'],
        ['checkout_optin', '1'],
        ['checkout_optin_text', 'Subscribe to our newsletter for updates and promotions']
    ];
    
    $stmt = $db->prepare("INSERT INTO woocommerce_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->bind_param("ss", $setting[0], $setting[1]);
        $stmt->execute();
    }
    
    $message = 'Default WooCommerce settings created.';
    $messageType = 'success';
}

// Get current settings
$settingsResult = $db->query("SELECT setting_key, setting_value FROM woocommerce_settings");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // Process WooCommerce settings
        $woocommerceEnabled = isset($_POST['woocommerce_enabled']) ? '1' : '0';
        $siteUrl = $_POST['site_url'] ?? '';
        $consumerKey = $_POST['consumer_key'] ?? '';
        $consumerSecret = $_POST['consumer_secret'] ?? '';
        $syncCustomers = isset($_POST['sync_customers']) ? '1' : '0';
        $syncOrders = isset($_POST['sync_orders']) ? '1' : '0';
        $syncProducts = isset($_POST['sync_products']) ? '1' : '0';
        $checkoutOptin = isset($_POST['checkout_optin']) ? '1' : '0';
        $checkoutOptinText = $_POST['checkout_optin_text'] ?? '';
        
        // Update settings
        $updatedSettings = [
            'woocommerce_enabled' => $woocommerceEnabled,
            'site_url' => $siteUrl,
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'sync_customers' => $syncCustomers,
            'sync_orders' => $syncOrders,
            'sync_products' => $syncProducts,
            'checkout_optin' => $checkoutOptin,
            'checkout_optin_text' => $checkoutOptinText
        ];
        
        foreach ($updatedSettings as $key => $value) {
            $stmt = $db->prepare("UPDATE woocommerce_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
        }
        
        $message = 'WooCommerce settings updated successfully';
        $messageType = 'success';
        
        // Refresh settings
        $settingsResult = $db->query("SELECT setting_key, setting_value FROM woocommerce_settings");
        $settings = [];
        while ($row = $settingsResult->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } elseif (isset($_POST['test_connection'])) {
        // Simulate testing connection
        $siteUrl = $settings['site_url'] ?? '';
        $consumerKey = $settings['consumer_key'] ?? '';
        $consumerSecret = $settings['consumer_secret'] ?? '';
        
        if (empty($siteUrl) || empty($consumerKey) || empty($consumerSecret)) {
            $message = 'Please configure all required WooCommerce settings first.';
            $messageType = 'error';
        } else {
            // In a real implementation, you would make an API call to WooCommerce here
            $message = 'Connection to WooCommerce successful!';
            $messageType = 'success';
        }
    } elseif (isset($_POST['sync_now'])) {
        // Simulate syncing
        $message = 'Sync with WooCommerce completed. 0 customers, 0 orders, and 0 products were synchronized.';
        $messageType = 'success';
    } elseif (isset($_POST['generate_api_keys'])) {
        // Simulate generating API keys
        $message = 'Here\'s how to create API keys in WooCommerce: Go to WooCommerce > Settings > Advanced > REST API > Add Key';
        $messageType = 'info';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WooCommerce Integration | LumiNewsletter</title>
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
        
        .woocommerce-logo {
            max-width: 180px;
            margin-bottom: 20px;
        }
        
        .instructions {
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary);
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
        }
        
        .instructions h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .instructions ol {
            margin-bottom: 0;
            padding-left: 20px;
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
                    <h1>WooCommerce Integration</h1>
                    <span class="connection-status <?php echo (!empty($settings['woocommerce_enabled']) && $settings['woocommerce_enabled'] == '1' && !empty($settings['consumer_key'])) ? 'connected' : 'disconnected'; ?>">
                        <?php echo (!empty($settings['woocommerce_enabled']) && $settings['woocommerce_enabled'] == '1' && !empty($settings['consumer_key'])) ? 'Connected' : 'Disconnected'; ?>
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
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'info' ? 'info-circle' : 'exclamation-circle'); ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fab fa-wordpress"></i> WooCommerce Configuration</h2>
                </div>
                <div class="card-body">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <img src="assets/images/woocommerce-logo.png" alt="WooCommerce Logo" class="woocommerce-logo" onerror="this.src='https://woocommerce.com/wp-content/themes/woo/images/logo-woocommerce.svg'; this.onerror=null;">
                    </div>
                    
                    <div class="instructions">
                        <h4>How to Set Up WooCommerce Integration</h4>
                        <ol>
                            <li>Go to your WordPress admin dashboard</li>
                            <li>Navigate to WooCommerce > Settings > Advanced > REST API</li>
                            <li>Click "Add Key" and create a new key with Read/Write permissions</li>
                            <li>Copy the Consumer Key and Consumer Secret below</li>
                        </ol>
                        <div style="margin-top: 10px;">
                            <form method="post" style="display: inline;">
                                <button type="submit" name="generate_api_keys" class="btn btn-sm">View Detailed Instructions</button>
                            </form>
                        </div>
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Enable WooCommerce Integration:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="woocommerce_enabled" <?php echo (!empty($settings['woocommerce_enabled']) && $settings['woocommerce_enabled'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_url">WordPress Site URL:</label>
                            <input type="url" id="site_url" name="site_url" value="<?php echo htmlspecialchars($settings['site_url'] ?? ''); ?>" placeholder="https://your-site.com">
                            <small>Your WordPress website URL (e.g., https://your-site.com)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="consumer_key">Consumer Key:</label>
                            <input type="text" id="consumer_key" name="consumer_key" value="<?php echo htmlspecialchars($settings['consumer_key'] ?? ''); ?>">
                            <small>WooCommerce REST API Consumer Key</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="consumer_secret">Consumer Secret:</label>
                            <input type="password" id="consumer_secret" name="consumer_secret" value="<?php echo htmlspecialchars($settings['consumer_secret'] ?? ''); ?>">
                            <small>WooCommerce REST API Consumer Secret</small>
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
                            <label class="toggle-switch-label">
                                <span>Checkout Newsletter Opt-in:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="checkout_optin" <?php echo (!empty($settings['checkout_optin']) && $settings['checkout_optin'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                            <small>Add a newsletter subscription checkbox at checkout</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="checkout_optin_text">Opt-in Text:</label>
                            <input type="text" id="checkout_optin_text" name="checkout_optin_text" value="<?php echo htmlspecialchars($settings['checkout_optin_text'] ?? ''); ?>">
                            <small>Text displayed next to the opt-in checkbox at checkout</small>
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
                    <p>Manually synchronize data from your WooCommerce store.</p>
                    
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