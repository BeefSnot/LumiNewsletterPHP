<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Only admins can manage all keys, regular users can only manage their own
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$userId = $_SESSION['user_id'];

// Generate a new API key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])) {
    $keyName = $_POST['key_name'] ?? '';
    
    if (empty($keyName)) {
        $message = 'Please provide a name for your API key';
        $messageType = 'error';
    } else {
        // Generate random api key and secret
        $apiKey = bin2hex(random_bytes(16));
        $apiSecret = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare("INSERT INTO api_keys (user_id, api_key, api_secret, name, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("isss", $userId, $apiKey, $apiSecret, $keyName);
        
        if ($stmt->execute()) {
            $message = 'API key generated successfully';
            $messageType = 'success';
        } else {
            $message = 'Error generating API key: ' . $db->error;
            $messageType = 'error';
        }
    }
}

// Revoke an API key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_key'])) {
    $keyId = (int)$_POST['key_id'];
    
    $query = "UPDATE api_keys SET status = 'inactive' WHERE id = ?";
    if (!$isAdmin) {
        $query .= " AND user_id = ?";
    }
    
    $stmt = $db->prepare($query);
    
    if ($isAdmin) {
        $stmt->bind_param("i", $keyId);
    } else {
        $stmt->bind_param("ii", $keyId, $userId);
    }
    
    if ($stmt->execute()) {
        $message = 'API key revoked successfully';
        $messageType = 'success';
    } else {
        $message = 'Error revoking API key: ' . $db->error;
        $messageType = 'error';
    }
}

// Get all keys for admin, or just user's keys for non-admin
$query = "SELECT ak.*, u.username FROM api_keys ak JOIN users u ON ak.user_id = u.id";
if (!$isAdmin) {
    $query .= " WHERE ak.user_id = ?";
}
$query .= " ORDER BY ak.created_at DESC";

$stmt = $db->prepare($query);
if (!$isAdmin) {
    $stmt->bind_param("i", $userId);
}
$stmt->execute();
$result = $stmt->get_result();
$apiKeys = [];

while ($row = $result->fetch_assoc()) {
    $apiKeys[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .api-key-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .api-key-header {
            padding: 15px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .api-key-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            color: var(--primary);
        }
        
        .api-key-body {
            padding: 15px;
        }
        
        .api-key-details {
            margin-bottom: 15px;
        }
        
        .api-key-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .api-key-label {
            font-weight: 500;
            width: 120px;
            color: var(--gray);
        }
        
        .api-key-value {
            flex: 1;
        }
        
        .api-key-code {
            background: var(--gray-light);
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            overflow-x: auto;
        }
        
        .api-key-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(52, 168, 83, 0.1);
            color: #34a853;
        }
        
        .status-inactive {
            background: rgba(234, 67, 53, 0.1);
            color: #ea4335;
        }
        
        .api-key-footer {
            padding: 15px;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
        }
        
        .api-key-meta {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary);
            margin-left: 10px;
        }
        
        .copy-btn:hover {
            color: var(--accent);
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
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="api_keys.php" class="nav-item active"><i class="fas fa-key"></i> API Keys</a></li>
                    <li><a href="social_sharing.php" class="nav-item"><i class="fas fa-share-alt"></i> Social Sharing</a></li>
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
                    <h1>API Keys</h1>
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
                    <h2><i class="fas fa-key"></i> API Key Management</h2>
                </div>
                <div class="card-body">
                    <p>
                        Manage your API keys for programmatic access to your LumiNewsletter data.
                        <a href="api_docs.php" class="btn btn-sm" style="margin-left: 10px;">View API Documentation</a>
                    </p>
                    
                    <form method="post" action="" class="form">
                        <div class="form-group">
                            <label for="key_name">API Key Name:</label>
                            <input type="text" id="key_name" name="key_name" required placeholder="e.g., Website Integration">
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="generate_key" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Generate New API Key
                            </button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <h3>Your API Keys</h3>
                    
                    <?php if (empty($apiKeys)): ?>
                        <p>No API keys found. Generate your first key above.</p>
                    <?php else: ?>
                        <?php foreach ($apiKeys as $key): ?>
                            <div class="api-key-card">
                                <div class="api-key-header">
                                    <h4 class="api-key-name"><?php echo htmlspecialchars($key['name']); ?></h4>
                                    <span class="api-key-status status-<?php echo $key['status']; ?>">
                                        <?php echo ucfirst($key['status']); ?>
                                    </span>
                                </div>
                                <div class="api-key-body">
                                    <div class="api-key-details">
                                        <div class="api-key-row">
                                            <div class="api-key-label">API Key:</div>
                                            <div class="api-key-value">
                                                <div class="api-key-code">
                                                    <?php echo htmlspecialchars($key['api_key']); ?>
                                                    <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($key['api_key']); ?>')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="api-key-row">
                                            <div class="api-key-label">API Secret:</div>
                                            <div class="api-key-value">
                                                <div class="api-key-code">
                                                    <?php echo substr($key['api_secret'], 0, 8) . '•••••••••••••••••••'; ?>
                                                    <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($key['api_secret']); ?>')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($isAdmin): ?>
                                        <div class="api-key-row">
                                            <div class="api-key-label">Owner:</div>
                                            <div class="api-key-value"><?php echo htmlspecialchars($key['username']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="api-key-row">
                                            <div class="api-key-label">Created:</div>
                                            <div class="api-key-value"><?php echo date('M j, Y', strtotime($key['created_at'])); ?></div>
                                        </div>
                                        
                                        <?php if ($key['last_used']): ?>
                                        <div class="api-key-row">
                                            <div class="api-key-label">Last Used:</div>
                                            <div class="api-key-value"><?php echo date('M j, Y H:i', strtotime($key['last_used'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($key['status'] === 'active'): ?>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to revoke this API key? This action cannot be undone.');">
                                            <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                            <button type="submit" name="revoke_key" class="btn btn-danger">
                                                <i class="fas fa-ban"></i> Revoke Key
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function copyToClipboard(text) {
            const el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            
            // Show a temporary tooltip
            alert('Copied to clipboard!');
        }
    </script>
</body>
</html>