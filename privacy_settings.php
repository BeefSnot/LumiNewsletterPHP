<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Get current privacy settings
$defaultSettings = [
    'privacy_policy' => '',
    'enable_tracking' => '1',
    'enable_geo_analytics' => '1',
    'require_explicit_consent' => '1',
    'data_retention_period' => '12',  // months
    'anonymize_ip' => '0',
    'cookie_notice' => '1',
    'cookie_notice_text' => 'We use cookies to improve your experience and analyze website traffic. By clicking "Accept", you agree to our website\'s cookie use as described in our Privacy Policy.',
    'consent_prompt_text' => 'Please confirm that you would like to receive our newsletter and consent to our tracking of email engagement for analytics purposes.'
];

// Load current settings from DB
$privacySettings = [];
$result = $db->query("SELECT setting_key, setting_value FROM privacy_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $privacySettings[$row['setting_key']] = $row['setting_value'];
    }
}

// Merge defaults with saved settings
$settings = array_merge($defaultSettings, $privacySettings);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process privacy policy settings form
    if (isset($_POST['save_settings'])) {
        $updatedSettings = [
            'privacy_policy' => $_POST['privacy_policy'] ?? '',
            'enable_tracking' => isset($_POST['enable_tracking']) ? '1' : '0',
            'enable_geo_analytics' => isset($_POST['enable_geo_analytics']) ? '1' : '0',
            'require_explicit_consent' => isset($_POST['require_explicit_consent']) ? '1' : '0',
            'data_retention_period' => $_POST['data_retention_period'] ?? '12',
            'anonymize_ip' => isset($_POST['anonymize_ip']) ? '1' : '0',
            'cookie_notice' => isset($_POST['cookie_notice']) ? '1' : '0',
            'cookie_notice_text' => $_POST['cookie_notice_text'] ?? $defaultSettings['cookie_notice_text'],
            'consent_prompt_text' => $_POST['consent_prompt_text'] ?? $defaultSettings['consent_prompt_text']
        ];
        
        $success = true;
        foreach ($updatedSettings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO privacy_settings (setting_key, setting_value) 
                                VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            if (!$stmt->execute()) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            $message = 'Privacy settings updated successfully';
            $messageType = 'success';
            $settings = $updatedSettings; // Update current settings
        } else {
            $message = 'Error saving settings: ' . $db->error;
            $messageType = 'error';
        }
    }
    
    // Process data removal request
    if (isset($_POST['purge_data'])) {
        $daysOld = (int)$_POST['days_old'];
        $dataType = $_POST['data_type'];
        
        $success = false;
        
        switch ($dataType) {
            case 'open_data':
                $stmt = $db->prepare("DELETE FROM email_opens WHERE opened_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->bind_param("i", $daysOld);
                $success = $stmt->execute();
                break;
            
            case 'click_data':
                $stmt = $db->prepare("DELETE FROM link_clicks WHERE clicked_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->bind_param("i", $daysOld);
                $success = $stmt->execute();
                break;
                
            case 'geo_data':
                $stmt = $db->prepare("DELETE FROM email_geo_data WHERE recorded_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->bind_param("i", $daysOld);
                $success = $stmt->execute();
                break;
                
            case 'device_data':
                $stmt = $db->prepare("DELETE FROM email_devices WHERE id IN (
                    SELECT d.id FROM email_devices d
                    JOIN email_opens o ON d.open_id = o.id
                    WHERE o.opened_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                )");
                $stmt->bind_param("i", $daysOld);
                $success = $stmt->execute();
                break;
                
            case 'all_tracking':
                $stmt = $db->prepare("DELETE FROM email_opens WHERE opened_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->bind_param("i", $daysOld);
                $success = $stmt->execute();
                
                $stmt = $db->prepare("DELETE FROM link_clicks WHERE clicked_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->bind_param("i", $daysOld);
                $success = $stmt->execute();
                break;
        }
        
        if ($success) {
            $message = 'Data purged successfully';
            $messageType = 'success';
        } else {
            $message = 'Error purging data: ' . $db->error;
            $messageType = 'error';
        }
    }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/tinymce/tinymce/js/tinymce/tinymce.min.js"></script>
    <style>
        .privacy-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .privacy-option {
            margin-bottom: 20px;
            border: 1px solid var(--gray-light);
            padding: 15px;
            border-radius: var(--radius);
        }
        
        .privacy-option h3 {
            margin-top: 0;
            display: flex;
            align-items: center;
        }
        
        .privacy-option h3 i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .privacy-notice {
            background-color: rgba(66, 133, 244, 0.08);
            border-left: 3px solid var(--primary);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 var(--radius) var(--radius) 0;
        }
        
        .purge-form {
            display: flex;
            align-items: flex-end;
            gap: 15px;
            margin-top: 15px;
        }
        
        .purge-form label {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <button class="mobile-nav-toggle" id="mobileNavToggle">
        <i class="fas fa-bars" id="menuIcon"></i>
    </button>
    
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
                    <li><a href="admin.php" class="nav-item"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="ab_testing.php" class="nav-item"><i class="fas fa-flask"></i> A/B Testing</a></li>
                    <li><a href="segments.php" class="nav-item"><i class="fas fa-tags"></i> Segments</a></li>
                    <li><a href="automations.php" class="nav-item"><i class="fas fa-robot"></i> Automations</a></li>
                    <li><a href="privacy_settings.php" class="nav-item active"><i class="fas fa-shield-alt"></i> Privacy</a></li>
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
                    <h2><i class="fas fa-shield-alt"></i> Privacy & Data Settings</h2>
                </div>
                <div class="card-body">
                    <div class="privacy-notice">
                        <p><strong>Note:</strong> Proper data privacy practices are crucial for compliance with laws like GDPR and CCPA. These settings help you configure how LumiNewsletter handles subscriber data and tracking.</p>
                    </div>
                    
                    <div class="privacy-tabs">
                        <button class="tab-btn active" onclick="showTab('general')">General Settings</button>
                        <button class="tab-btn" onclick="showTab('policy')">Privacy Policy</button>
                        <button class="tab-btn" onclick="showTab('data')">Data Management</button>
                    </div>
                    
                    <div id="general-tab" class="tab-content active">
                        <form method="post" action="">
                            <div class="privacy-option">
                                <h3><i class="fas fa-chart-line"></i> Email Tracking</h3>
                                <div class="form-group">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="enable_tracking" <?php echo $settings['enable_tracking'] === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Enable email open tracking
                                    </label>
                                    <p class="form-help">When enabled, LumiNewsletter will track when subscribers open emails and click links.</p>
                                </div>
                                
                                <div class="form-group">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="enable_geo_analytics" <?php echo $settings['enable_geo_analytics'] === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Enable geographical analytics
                                    </label>
                                    <p class="form-help">When enabled, LumiNewsletter will collect and analyze geographical data when subscribers open emails.</p>
                                </div>
                                
                                <div class="form-group">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="anonymize_ip" <?php echo $settings['anonymize_ip'] === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Anonymize IP addresses
                                    </label>
                                    <p class="form-help">When enabled, IP addresses will be anonymized by removing the last octet (e.g., 192.168.1.xxx).</p>
                                </div>
                            </div>
                            
                            <div class="privacy-option">
                                <h3><i class="fas fa-user-check"></i> Consent Management</h3>
                                <div class="form-group">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="require_explicit_consent" <?php echo $settings['require_explicit_consent'] === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Require explicit consent for tracking
                                    </label>
                                    <p class="form-help">When enabled, subscribers must explicitly consent to tracking before data is collected.</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="consent_prompt_text">Consent prompt text:</label>
                                    <textarea id="consent_prompt_text" name="consent_prompt_text" rows="3"><?php echo htmlspecialchars($settings['consent_prompt_text']); ?></textarea>
                                    <p class="form-help">This text will be shown on subscription forms when asking for tracking consent.</p>
                                </div>
                                
                                <div class="form-group">
                                    <label class="checkbox-container">
                                        <input type="checkbox" name="cookie_notice" <?php echo $settings['cookie_notice'] === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        Show cookie consent notice
                                    </label>
                                    <p class="form-help">When enabled, a cookie consent notice will be shown to visitors.</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cookie_notice_text">Cookie notice text:</label>
                                    <textarea id="cookie_notice_text" name="cookie_notice_text" rows="3"><?php echo htmlspecialchars($settings['cookie_notice_text']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="privacy-option">
                                <h3><i class="fas fa-clock"></i> Data Retention</h3>
                                <div class="form-group">
                                    <label for="data_retention_period">Data retention period (months):</label>
                                    <input type="number" id="data_retention_period" name="data_retention_period" value="<?php echo htmlspecialchars($settings['data_retention_period']); ?>" min="1" max="60">
                                    <p class="form-help">Analytics data older than this will be automatically deleted.</p>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="policy-tab" class="tab-content">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="privacy_policy">Privacy Policy:</label>
                                <textarea id="privacy_policy" name="privacy_policy"><?php echo htmlspecialchars($settings['privacy_policy']); ?></textarea>
                                <p class="form-help">This privacy policy will be displayed on your subscription pages and in the footer of emails.</p>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="save_settings" class="btn btn-primary">Save Privacy Policy</button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="data-tab" class="tab-content">
                        <div class="privacy-option">
                            <h3><i class="fas fa-trash-alt"></i> Data Purge</h3>
                            <p>Use this section to manually delete old tracking and analytics data.</p>
                            
                            <form method="post" action="" class="purge-form">
                                <div class="form-group">
                                    <label for="data_type">Data type:</label>
                                    <select id="data_type" name="data_type" required>
                                        <option value="open_data">Email Opens</option>
                                        <option value="click_data">Link Clicks</option>
                                        <option value="geo_data">Geographical Data</option>
                                        <option value="device_data">Device Data</option>
                                        <option value="all_tracking">All Tracking Data</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="days_old">Delete data older than (days):</label>
                                    <input type="number" id="days_old" name="days_old" value="90" min="1" max="3650" required>
                                </div>
                                
                                <button type="submit" name="purge_data" class="btn btn-danger" onclick="return confirm('Are you sure you want to purge this data? This action cannot be undone.')">
                                    <i class="fas fa-trash-alt"></i> Purge Data
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Initialize TinyMCE for privacy policy editor
        tinymce.init({
            selector: '#privacy_policy',
            height: 400,
            menubar: false,
            plugins: 'lists link code table',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link | code'
        });
        
        // Tab switching functionality
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Activate the selected tab
            document.getElementById(tabId + '-tab').classList.add('active');
            
            // Highlight the clicked button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                if (btn.textContent.toLowerCase().includes(tabId)) {
                    btn.classList.add('active');
                }
            });
        }
        
        // Mobile menu functionality
        document.getElementById('mobileNavToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('backdrop').classList.toggle('active');
            
            const menuIcon = document.getElementById('menuIcon');
            if (menuIcon.classList.contains('fa-bars')) {
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times');
            } else {
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });
        
        document.getElementById('backdrop').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('backdrop').classList.remove('active');
            document.getElementById('menuIcon').classList.remove('fa-times');
            document.getElementById('menuIcon').classList.add('fa-bars');
        });
    </script>
</body>
</html>