<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access system features management
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Process feature toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_feature'])) {
    $feature_id = (int)$_POST['feature_id'];
    $new_status = $_POST['new_status'] === 'enable' ? 1 : 0;
    
    $stmt = $db->prepare("UPDATE features SET enabled = ? WHERE id = ?");
    $stmt->bind_param('ii', $new_status, $feature_id);
    
    if ($stmt->execute()) {
        $message = 'Feature status updated successfully';
        $messageType = 'success';
    } else {
        $message = 'Error updating feature status: ' . $db->error;
        $messageType = 'error';
    }
}

// Fetch all features
$features = [];
$result = $db->query("SELECT * FROM features ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $features[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Features | LumiNewsletter</title>
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
                    <h1>System Features</h1>
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
                    <h2><i class="fas fa-toggle-on"></i> Manage System Features</h2>
                </div>
                <div class="card-body">
                    <p>Enable or disable system features based on your needs. Some features may require additional configuration after enabling.</p>
                    
                    <?php if (empty($features)): ?>
                        <div class="notification info">
                            <i class="fas fa-info-circle"></i>
                            No system features found. Features will appear here after system updates.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Feature</th>
                                        <th>Description</th>
                                        <th>Added in</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($features as $feature): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo ucwords(str_replace('_', ' ', $feature['feature_name'])); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($feature['description']); ?></td>
                                            <td>v<?php echo htmlspecialchars($feature['added_version']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $feature['enabled'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $feature['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="post" style="display: inline-block;">
                                                    <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $feature['enabled'] ? 'disable' : 'enable'; ?>">
                                                    <button type="submit" name="toggle_feature" class="btn btn-sm <?php echo $feature['enabled'] ? 'btn-danger' : 'btn-success'; ?>">
                                                        <i class="fas fa-<?php echo $feature['enabled'] ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                                        <?php echo $feature['enabled'] ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </form>
                                                
                                                <?php if ($feature['feature_name'] === 'ai_assistant' && $feature['enabled']): ?>
                                                    <a href="ai_assistant.php" class="btn btn-sm btn-outline">
                                                        <i class="fas fa-cog"></i> Configure
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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