<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user role
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

// Fetch current settings
$settingsResult = $db->query("SELECT name, value FROM settings");
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['name']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['title'] ?? 'Page Title | LumiNewsletter'); ?></title>
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
                    <h1><?php echo htmlspecialchars($settings['title'] ?? 'Page Title'); ?></h1>
                </div>
            </header>
            
            <div class="card">
                <div class="card-header">
                    <h2>Welcome to the Newsletter Dashboard</h2>
                </div>
                <div class="card-body">
                    <p>Use the navigation to manage your newsletters and themes.</p>
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