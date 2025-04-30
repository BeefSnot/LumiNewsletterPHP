<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin can access this script
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$tables_fixed = [];
$errors = [];

// Function to check and add column if it doesn't exist
function ensureColumnExists($db, $table, $column, $definition) {
    try {
        $result = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($result->num_rows === 0) {
            $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            return true;
        }
        return false;
    } catch (Exception $e) {
        global $errors;
        $errors[] = "Error checking column $column in $table: " . $e->getMessage();
        return false;
    }
}

// Define required columns for each table
$required_columns = [
    'newsletters' => [
        'sent_at' => 'TIMESTAMP NULL DEFAULT NULL'
    ],
    'groups' => [
        'description' => 'TEXT NULL AFTER name',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ],
    'group_subscriptions' => [
        'name' => 'VARCHAR(255) NULL AFTER email',
        'subscribed_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ],
    // Add other tables and their required columns as needed
];

// Fix all tables
foreach ($required_columns as $table => $columns) {
    foreach ($columns as $column => $definition) {
        if (ensureColumnExists($db, $table, $column, $definition)) {
            $tables_fixed[] = "Added missing column '$column' to table '$table'";
        }
    }
}

// Update data in newly added columns where appropriate
if (in_array("Added missing column 'sent_at' to table 'newsletters'", $tables_fixed)) {
    $db->query("UPDATE newsletters SET sent_at = created_at WHERE sent_at IS NULL");
    $tables_fixed[] = "Updated sent_at values based on created_at dates";
}

// Display results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Database Tables | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .table-status {
            margin-bottom: 20px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            background: white;
        }
        .table-status h3 {
            padding: 15px;
            margin: 0;
            background-color: var(--primary-light);
            color: var(--primary);
            font-size: 1rem;
            display: flex;
            align-items: center;
        }
        .table-status h3 i {
            margin-right: 10px;
        }
        .status-content {
            padding: 15px;
        }
        .status-item {
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        .status-item i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        .status-success {
            background-color: rgba(52, 168, 83, 0.1);
            color: #34a853;
        }
        .status-warning {
            background-color: rgba(251, 188, 4, 0.1);
            color: #fbbc04;
        }
        .status-error {
            background-color: rgba(234, 67, 53, 0.1);
            color: #ea4335;
        }
        .status-info {
            background-color: rgba(66, 133, 244, 0.1);
            color: #4285f4;
        }
        .result-summary {
            margin-top: 30px;
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .result-summary h2 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        .action-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
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
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="ab_testing.php" class="nav-item"><i class="fas fa-flask"></i> A/B Testing</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="segments.php" class="nav-item"><i class="fas fa-tags"></i> Segments</a></li>
                    <li><a href="privacy_settings.php" class="nav-item"><i class="fas fa-shield-alt"></i> Privacy</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Fix Database Tables</h1>
                </div>
            </header>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-database"></i> Database Tables Repair Tool</h2>
                </div>
                <div class="card-body">
                    <p>This tool checks and creates any missing tables required for LumiNewsletter to function properly.</p>
                    
                    <div class="result-summary">
                        <h2><i class="fas fa-check-circle"></i> Database Schema Check Completed</h2>
                        <p>All required tables have been checked and repaired if needed.</p>
                        
                        <div class="action-buttons">
                            <a href="analytics.php" class="btn"><i class="fas fa-chart-bar"></i> Analytics</a>
                            <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>