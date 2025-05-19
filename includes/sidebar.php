<?php
// Get current page for highlighting active menu
$current_page = basename($_SERVER['PHP_SELF']);
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Function to check if a page is active
function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}

// Function to check if any page in a group is active
function isGroupActive($pages) {
    global $current_page;
    return in_array($current_page, $pages) ? 'active' : '';
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-paper-plane"></i>
            <h2>LumiNews</h2>
        </div>
    </div>
    <nav class="main-nav">
        <ul>
            <li><a href="index.php" class="nav-item <?php echo isActive('index.php'); ?>"><i class="fas fa-home"></i> Dashboard</a></li>
            
            <!-- Newsletter Management Group -->
            <li class="menu-group">
                <div class="menu-group-header <?php echo isGroupActive(['send_newsletter.php', 'manage_newsletters.php', 'create_theme.php']); ?>">
                    <i class="fas fa-envelope"></i> Newsletters
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="send_newsletter.php" class="nav-item <?php echo isActive('send_newsletter.php'); ?>"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item <?php echo isActive('manage_newsletters.php'); ?>"><i class="fas fa-list"></i> Manage Newsletters</a></li>
                    <li><a href="create_theme.php" class="nav-item <?php echo isActive('create_theme.php'); ?>"><i class="fas fa-palette"></i> Create Theme</a></li>
                </ul>
            </li>
            
            <!-- Subscriber Management Group -->
            <li class="menu-group">
                <div class="menu-group-header <?php echo isGroupActive(['manage_subscriptions.php', 'segments.php']); ?>">
                    <i class="fas fa-users"></i> Subscribers
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="manage_subscriptions.php" class="nav-item <?php echo isActive('manage_subscriptions.php'); ?>"><i class="fas fa-user-plus"></i> Manage Subscribers</a></li>
                    <li><a href="segments.php" class="nav-item <?php echo isActive('segments.php'); ?>"><i class="fas fa-tags"></i> Segments</a></li>
                    <?php if ($isAdmin): ?>
                    <li><a href="manage_groups.php" class="nav-item <?php echo isActive('manage_groups.php'); ?>"><i class="fas fa-layer-group"></i> Groups</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            
            <!-- Analytics & Testing Group -->
            <?php if ($_SESSION['role'] === 'admin'): // Only show to admin users ?>
            <li class="menu-group">
                <div class="menu-group-header <?php echo isGroupActive(['analytics.php', 'ab_testing.php']); ?>">
                    <i class="fas fa-chart-bar"></i> Analytics & Testing
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="analytics.php" class="nav-item <?php echo isActive('analytics.php'); ?>"><i class="fas fa-chart-line"></i> Analytics</a></li>
                    <li><a href="ab_testing.php" class="nav-item <?php echo isActive('ab_testing.php'); ?>"><i class="fas fa-flask"></i> A/B Testing</a></li>
                </ul>
            </li>
            <?php endif; ?>
            
            <!-- Integration Group -->
            <?php if ($_SESSION['role'] === 'admin'): // Only show to admin users ?>
            <li class="menu-group">
                <div class="menu-group-header <?php echo isGroupActive(['social_sharing.php', 'embed_docs.php', 'api_keys.php', 'api_docs.php']); ?>">
                    <i class="fas fa-plug"></i> Integrations
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="social_sharing.php" class="nav-item <?php echo isActive('social_sharing.php'); ?>"><i class="fas fa-share-alt"></i> Social Sharing</a></li>
                    <li><a href="embed_docs.php" class="nav-item <?php echo isActive('embed_docs.php'); ?>"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <li><a href="api_keys.php" class="nav-item <?php echo isActive('api_keys.php'); ?>"><i class="fas fa-key"></i> API Keys</a></li>
                    <li><a href="api_docs.php" class="nav-item <?php echo isActive('api_docs.php'); ?>"><i class="fas fa-book"></i> API Docs</a></li>
                </ul>
            </li>
            <?php endif; ?>
            
            <!-- Automation Group -->
            <?php if ($_SESSION['role'] === 'admin'): // Only show to admin users ?>
            <li class="menu-group">
                <div class="menu-group-header <?php echo isGroupActive(['automations.php', 'content_blocks.php']); ?>">
                    <i class="fas fa-robot"></i> Automation
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="automations.php" class="nav-item <?php echo isActive('automations.php'); ?>"><i class="fas fa-cogs"></i> Workflows</a></li>
                    <li><a href="content_blocks.php" class="nav-item <?php echo isActive('content_blocks.php'); ?>"><i class="fas fa-th-large"></i> Content Blocks</a></li>
                </ul>
            </li>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <!-- Admin Settings Group - Admin Only -->
            <li class="menu-group">
                <div class="menu-group-header <?php echo isGroupActive(['admin.php', 'manage_users.php', 'manage_smtp.php', 'privacy_settings.php', 'update.php', 'system_features.php']); ?>">
                    <i class="fas fa-cog"></i> Administration
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <ul class="submenu">
                    <li><a href="admin.php" class="nav-item <?php echo isActive('admin.php'); ?>"><i class="fas fa-sliders-h"></i> General Settings</a></li>
                    <li><a href="manage_users.php" class="nav-item <?php echo isActive('manage_users.php'); ?>"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item <?php echo isActive('manage_smtp.php'); ?>"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="privacy_settings.php" class="nav-item <?php echo isActive('privacy_settings.php'); ?>"><i class="fas fa-shield-alt"></i> Privacy</a></li>
                    <li><a href="update.php" class="nav-item <?php echo isActive('update.php'); ?>"><i class="fas fa-sync-alt"></i> Updates</a></li>
                    <li><a href="system_features.php" class="nav-item <?php echo isActive('system_features.php'); ?>"><i class="fas fa-toggle-on"></i> System Features</a></li>
                </ul>
            </li>
            <?php endif; ?>
            
            <!-- AI Assistant (if enabled) -->
            <?php 
            // Check if AI Assistant is enabled
            $aiEnabled = false;
            $featureResult = $db->query("SELECT enabled FROM features WHERE feature_name = 'ai_assistant'");
            if ($featureResult && $featureResult->num_rows > 0) {
                $aiEnabled = (bool)$featureResult->fetch_assoc()['enabled'];
            }

            if ($aiEnabled): 
            ?>
            <li><a href="ai_assistant.php" class="nav-item <?php echo isActive('ai_assistant.php'); ?>"><i class="fas fa-robot"></i> AI Assistant</a></li>
            <?php endif; ?>
            
            <li><a href="manage_templates.php" class="nav-item <?php echo isActive('manage_templates.php'); ?>"><i class="fas fa-envelope-open-text"></i> Email Templates</a></li>
            <li><a href="media_library.php" class="nav-item <?php echo isActive('media_library.php'); ?>"><i class="fas fa-images"></i> Media Library</a></li>
            <li><a href="social_sharing.php" class="nav-item <?php echo isActive('social_sharing.php'); ?>"><i class="fas fa-share-alt"></i> Social Analytics</a></li>
            
            <li><a href="logout.php" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <p>Version <?php echo htmlspecialchars($currentVersion ?? '1.0.0'); ?></p>
    </div>
</aside>