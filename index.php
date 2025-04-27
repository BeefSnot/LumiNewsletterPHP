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
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-paper-plane"></i>
                    <h2>LumiNews</h2>
                </div>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="nav-item active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <?php if ($isAdmin): ?>
                    <li><a href="admin.php" class="nav-item"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <?php endif; ?>
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <?php if ($isAdmin): ?>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <?php endif; ?>
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
    
    <!-- Add mobile menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileNavToggle = document.getElementById('mobileNavToggle');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('backdrop');
            const menuIcon = document.getElementById('menuIcon');
            
            function toggleMenu() {
                sidebar.classList.toggle('active');
                backdrop.classList.toggle('active');
                
                if (sidebar.classList.contains('active')) {
                    menuIcon.classList.remove('fa-bars');
                    menuIcon.classList.add('fa-times');
                } else {
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            }
            
            mobileNavToggle.addEventListener('click', toggleMenu);
            backdrop.addEventListener('click', toggleMenu);
            
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 991 && sidebar.classList.contains('active')) {
                        toggleMenu();
                    }
                });
            });
        });
    </script>
</body>
</html>