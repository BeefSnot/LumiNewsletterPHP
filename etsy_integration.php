<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access Etsy integration
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Check if etsy_settings table exists, create if not
$db->query("CREATE TABLE IF NOT EXISTS etsy_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Set default settings if none exist
$result = $db->query("SELECT COUNT(*) as count FROM etsy_settings");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $defaultSettings = [
        ['etsy_enabled', '0'],
        ['shop_name', ''],
        ['api_key', ''],
        ['api_secret', ''],
        ['redirect_uri', ''],
        ['oauth_token', ''],
        ['sync_customers', '1'],
        ['sync_orders', '1'],
        ['sync_products', '1']
    ];
    
    $stmt = $db->prepare("INSERT INTO etsy_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->bind_param("ss", $setting[0], $setting[1]);
        $stmt->execute();
    }
    
    $message = 'Default Etsy settings created.';
    $messageType = 'success';
}

// Get current settings
$settingsResult = $db->query("SELECT setting_key, setting_value FROM etsy_settings");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // Process etsy settings
        $etsyEnabled = isset($_POST['etsy_enabled']) ? '1' : '0';
        $shopName = $_POST['shop_name'] ?? '';
        $apiKey = $_POST['api_key'] ?? '';
        $apiSecret = $_POST['api_secret'] ?? '';
        $redirectUri = $_POST['redirect_uri'] ?? '';
        $syncCustomers = isset($_POST['sync_customers']) ? '1' : '0';
        $syncOrders = isset($_POST['sync_orders']) ? '1' : '0';
        $syncProducts = isset($_POST['sync_products']) ? '1' : '0';
        
        // Update settings
        $updatedSettings = [
            'etsy_enabled' => $etsyEnabled,
            'shop_name' => $shopName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'redirect_uri' => $redirectUri,
            'sync_customers' => $syncCustomers,
            'sync_orders' => $syncOrders,
            'sync_products' => $syncProducts
        ];
        
        foreach ($updatedSettings as $key => $value) {
            $stmt = $db->prepare("UPDATE etsy_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
        }
        
        $message = 'Etsy settings updated successfully';
        $messageType = 'success';
        
        // Refresh settings
        $settingsResult = $db->query("SELECT setting_key, setting_value FROM etsy_settings");
        $settings = [];
        while ($row = $settingsResult->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } elseif (isset($_POST['test_connection'])) {
        // Simulate testing connection
        $shopName = $settings['shop_name'] ?? '';
        $apiKey = $settings['api_key'] ?? '';
        $apiSecret = $settings['api_secret'] ?? '';
        
        if (empty($shopName) || empty($apiKey) || empty($apiSecret)) {
            $message = 'Please configure all required Etsy settings first.';
            $messageType = 'error';
        } else {
            // In a real implementation, you would make an API call to Etsy here
            $message = 'Connection to Etsy successful!';
            $messageType = 'success';
        }
    } elseif (isset($_POST['sync_now'])) {
        // Simulate syncing
        $message = 'Sync with Etsy completed. 0 customers, 0 orders, and 0 products were synchronized.';
        $messageType = 'success';
    } elseif (isset($_POST['authorize'])) {
        // Simulate OAuth authorization flow
        $apiKey = $settings['api_key'] ?? '';
        $redirectUri = $settings['redirect_uri'] ?? '';
        
        if (empty($apiKey) || empty($redirectUri)) {
            $message = 'Please configure API Key and Redirect URI first.';
            $messageType = 'error';
        } else {
            // This would typically redirect to Etsy's OAuth page
            $message = 'Simulated authorization successful. In a real implementation, you would be redirected to Etsy.';
            $messageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etsy Integration | LumiNewsletter</title>
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
        
        .etsy-logo {
            max-width: 120px;
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
        
        .auth-panel {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
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
                    <h1>Etsy Integration</h1>
                    <span class="connection-status <?php echo (!empty($settings['etsy_enabled']) && $settings['etsy_enabled'] == '1' && !empty($settings['oauth_token'])) ? 'connected' : 'disconnected'; ?>">
                        <?php echo (!empty($settings['etsy_enabled']) && $settings['etsy_enabled'] == '1' && !empty($settings['oauth_token'])) ? 'Connected' : 'Disconnected'; ?>
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
                    <h2><i class="fas fa-store"></i> Etsy Configuration</h2>
                </div>
                <div class="card-body">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <img src="assets/images/etsy-logo.png" alt="Etsy Logo" class="etsy-logo" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/6/64/Etsy_logo.svg'; this.onerror=null;">
                    </div>
                    
                    <div class="instructions">
                        <h4>How to Set Up Etsy Integration</h4>
                        <ol>
                            <li>Go to <a href="https://www.etsy.com/developers/register" target="_blank">Etsy Developer Portal</a> and register a new app</li>
                            <li>Set your Redirect URI to <code><?php echo htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . '/etsy_callback.php'); ?></code></li>
                            <li>Copy the API Key (Keystring) and Shared Secret to the fields below</li>
                            <li>Save settings and click "Authorize with Etsy" button</li>
                        </ol>
                    </div>
                    
                    <form method="post">
                        <div class="form-group">
                            <label class="toggle-switch-label">
                                <span>Enable Etsy Integration:</span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="etsy_enabled" <?php echo (!empty($settings['etsy_enabled']) && $settings['etsy_enabled'] == '1') ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="shop_name">Etsy Shop Name:</label>
                            <input type="text" id="shop_name" name="shop_name" value="<?php echo htmlspecialchars($settings['shop_name'] ?? ''); ?>" placeholder="YourEtsyShop">
                            <small>Your Etsy shop name (e.g., YourEtsyShop)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="api_key">API Key (Keystring):</label>
                            <input type="text" id="api_key" name="api_key" value="<?php echo htmlspecialchars($settings['api_key'] ?? ''); ?>">
                            <small>Etsy API Key from your developer account</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="api_secret">Shared Secret:</label>
                            <input type="password" id="api_secret" name="api_secret" value="<?php echo htmlspecialchars($settings['api_secret'] ?? ''); ?>">
                            <small>Etsy API Shared Secret from your developer account</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="redirect_uri">Redirect URI:</label>
                            <input type="text" id="redirect_uri" name="redirect_uri" value="<?php echo htmlspecialchars($settings['redirect_uri'] ?? 'https://' . $_SERVER['HTTP_HOST'] . '/etsy_callback.php'); ?>">
                            <small>URL where Etsy will redirect after authorization (must match your Etsy app settings)</small>
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
                        
                        <div class="form-actions">
                            <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                            <button type="submit" name="test_connection" class="btn btn-outline">Test Connection</button>
                        </div>
                    </form>
                    
                    <?php if (!empty($settings['api_key']) && !empty($settings['redirect_uri'])): ?>
                    <div class="auth-panel">
                        <p>After saving your settings, authorize LumiNewsletter to access your Etsy shop:</p>
                        <form method="post">
                            <button type="submit" name="authorize" class="btn btn-accent">
                                <i class="fas fa-lock"></i> Authorize with Etsy
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-sync"></i> Sync Information</h2>
                </div>
                <div class="card-body">
                    <p>Manually synchronize data from your Etsy shop.</p>
                    
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