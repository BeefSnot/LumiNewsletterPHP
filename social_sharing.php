<?php
// Add at the top of the file - REMOVE AFTER DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/social_sharing.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Check if social tables exist
$checkSocial = $db->query("SHOW TABLES LIKE 'social_shares'");
if ($checkSocial->num_rows === 0) {
    // Create required tables
    $db->query("CREATE TABLE IF NOT EXISTS social_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        newsletter_id INT NOT NULL,
        platform VARCHAR(50) NOT NULL,
        share_count INT DEFAULT 0,
        click_count INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $db->query("CREATE TABLE IF NOT EXISTS social_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        share_id INT NOT NULL,
        ip_address VARCHAR(45) NULL,
        referrer VARCHAR(255) NULL,
        clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $message = "Social sharing tables have been created";
    $messageType = "success";
}

// Check if social sharing is enabled
$result = $db->query("SELECT value FROM settings WHERE name = 'social_sharing_enabled'");
$sharingEnabled = true; // Default to true if setting doesn't exist

if ($result && $result->num_rows > 0) {
    $sharingEnabled = $result->fetch_assoc()['value'] == '1';
}

// Get overall social stats
$overallStats = $db->query("
    SELECT 
        platform, 
        COUNT(*) as total_shares,
        SUM(share_count) as share_count,
        SUM(click_count) as click_count 
    FROM social_shares
    GROUP BY platform
    ORDER BY share_count DESC
");

$platforms = [];
$shareData = [];
$clickData = [];
$colors = [
    'facebook' => '#3b5998',
    'twitter' => '#1da1f2',
    'linkedin' => '#0077b5',
    'email' => '#777777',
    'other' => '#999999'
];

while ($overallStats && $stat = $overallStats->fetch_assoc()) {
    $platforms[] = ucfirst($stat['platform']);
    $shareData[] = (int)$stat['share_count'];
    $clickData[] = (int)$stat['click_count'];
}

// Get most shared newsletters
$topNewsletters = $db->query("
    SELECT 
        n.id,
        n.subject,
        DATE_FORMAT(n.created_at, '%Y-%m-%d') as date,
        SUM(ss.share_count) as total_shares,
        SUM(ss.click_count) as total_clicks
    FROM newsletters n
    JOIN social_shares ss ON n.id = ss.newsletter_id
    GROUP BY n.id
    ORDER BY total_shares DESC
    LIMIT 10
");

// Get recent social activity
$recentActivity = $db->query("
    SELECT 
        n.id,
        n.subject,
        ss.platform,
        DATE_FORMAT(sc.clicked_at, '%Y-%m-%d %H:%i') as clicked_at,
        sc.referrer
    FROM social_clicks sc
    JOIN social_shares ss ON sc.share_id = ss.id
    JOIN newsletters n ON ss.newsletter_id = n.id
    ORDER BY sc.clicked_at DESC
    LIMIT 20
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Analytics | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .social-platform-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .table-container {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-platform {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }
        
        .facebook { background-color: #3b5998; }
        .twitter { background-color: #1da1f2; }
        .linkedin { background-color: #0077b5; }
        .email { background-color: #777; }
        .other { background-color: #999; }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .activity-meta {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .sharing-options {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .sharing-options h3 {
            margin-top: 0;
            color: var(--primary);
        }
        
        .sharing-toggle {
            margin: 15px 0;
            display: flex;
            align-items: center;
        }
        
        .sharing-toggle-label {
            margin-left: 10px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--primary);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .social-preview {
            padding: 15px;
            margin-top: 20px;
            background: #f5f7fa;
            border-radius: var(--radius);
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
                    <h1>Social Media Analytics</h1>
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
                    <h2><i class="fas fa-share-alt"></i> Social Sharing Overview</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($platforms)): ?>
                        <div class="notification info">
                            <i class="fas fa-info-circle"></i>
                            No social sharing data yet. When subscribers share your newsletters, analytics will appear here.
                        </div>
                    <?php else: ?>
                        <div class="chart-container">
                            <canvas id="socialSharesChart"></canvas>
                        </div>
                        
                        <h3>Platform Breakdown</h3>
                        <div class="stats-grid">
                            <?php
                            $totalShares = array_sum($shareData);
                            $totalClicks = array_sum($clickData);
                            
                            // Overall stats card
                            echo '<div class="stat-card">';
                            echo '<i class="fas fa-share-alt social-platform-icon" style="color:var(--primary)"></i>';
                            echo '<div class="stat-value">' . number_format($totalShares) . '</div>';
                            echo '<div class="stat-label">Total Shares</div>';
                            echo '</div>';
                            
                            echo '<div class="stat-card">';
                            echo '<i class="fas fa-mouse-pointer social-platform-icon" style="color:var(--accent)"></i>';
                            echo '<div class="stat-value">' . number_format($totalClicks) . '</div>';
                            echo '<div class="stat-label">Total Clicks</div>';
                            echo '</div>';
                            
                            // Stats per platform
                            $overallStats->data_seek(0);
                            while ($stat = $overallStats->fetch_assoc()) {
                                $platform = $stat['platform'];
                                $platformName = ucfirst($platform);
                                $shareCount = (int)$stat['share_count'];
                                $clickCount = (int)$stat['click_count'];
                                $icon = $platform === 'email' ? 'fa-envelope' : 'fa-' . $platform;
                                
                                echo '<div class="stat-card">';
                                echo '<i class="fab ' . $icon . ' social-platform-icon" style="color:' . ($colors[$platform] ?? '#999') . '"></i>';
                                echo '<div class="stat-value">' . number_format($shareCount) . '</div>';
                                echo '<div class="stat-label">' . $platformName . ' Shares</div>';
                                echo '<div class="stat-value">' . number_format($clickCount) . '</div>';
                                echo '<div class="stat-label">' . $platformName . ' Clicks</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3>Most Shared Newsletters</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Newsletter</th>
                                    <th>Date</th>
                                    <th>Shares</th>
                                    <th>Clicks</th>
                                    <th>CTR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($topNewsletters && $topNewsletters->num_rows > 0): ?>
                                    <?php while ($newsletter = $topNewsletters->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <a href="newsletter_view.php?id=<?php echo $newsletter['id']; ?>" target="_blank">
                                                <?php echo htmlspecialchars($newsletter['subject']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $newsletter['date']; ?></td>
                                        <td><?php echo number_format($newsletter['total_shares']); ?></td>
                                        <td><?php echo number_format($newsletter['total_clicks']); ?></td>
                                        <td>
                                            <?php
                                            $ctr = $newsletter['total_shares'] > 0 ? 
                                                    round(($newsletter['total_clicks'] / $newsletter['total_shares']) * 100, 1) : 0;
                                            echo $ctr . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No newsletter sharing data available yet</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <h3>Recent Social Activity</h3>
                    <div class="table-container">
                        <?php if ($recentActivity && $recentActivity->num_rows > 0): ?>
                            <?php while ($activity = $recentActivity->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="activity-platform <?php echo $activity['platform']; ?>">
                                        <i class="fab fa-<?php echo $activity['platform'] == 'email' ? 'envelope' : $activity['platform']; ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-title">
                                            <a href="newsletter_view.php?id=<?php echo $activity['id']; ?>" target="_blank">
                                                <?php echo htmlspecialchars($activity['subject']); ?>
                                            </a>
                                        </div>
                                        <div class="activity-meta">
                                            <span><?php echo htmlspecialchars(ucfirst($activity['platform'])); ?> click at <?php echo $activity['clicked_at']; ?></span>
                                            <?php if ($activity['referrer']): ?>
                                                <span> from <?php echo parse_url($activity['referrer'], PHP_URL_HOST); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center">No recent social activity recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Social Sharing Settings Section -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-cogs"></i> Social Sharing Settings</h2>
                </div>
                <div class="card-body">
                    <div class="sharing-options">
                        <h3>Social Media Platforms</h3>
                        <p>Select which social media platforms to include in newsletter sharing options:</p>
                        
                        <div class="sharing-toggle">
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="sharing-toggle-label">Facebook</span>
                        </div>
                        
                        <div class="sharing-toggle">
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="sharing-toggle-label">Twitter</span>
                        </div>
                        
                        <div class="sharing-toggle">
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="sharing-toggle-label">LinkedIn</span>
                        </div>
                        
                        <div class="sharing-toggle">
                            <label class="toggle-switch">
                                <input type="checkbox" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="sharing-toggle-label">Email</span>
                        </div>
                        
                        <h3>Social Share Example</h3>
                        <div class="social-preview">
                            <div class="social-sharing">
                                <h4>Share this newsletter:</h4>
                                <div class="social-buttons">
                                    <a href="#" class="social-btn facebook">
                                        <i class="fab fa-facebook-f"></i> Facebook
                                    </a>
                                    <a href="#" class="social-btn twitter">
                                        <i class="fab fa-twitter"></i> Twitter
                                    </a>
                                    <a href="#" class="social-btn linkedin">
                                        <i class="fab fa-linkedin-in"></i> LinkedIn
                                    </a>
                                    <a href="#" class="social-btn email">
                                        <i class="fas fa-envelope"></i> Email
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script src="assets/js/sidebar.js"></script>
    
    <script>
        // Create the chart
        <?php if (!empty($platforms)): ?>
        const ctx = document.getElementById('socialSharesChart').getContext('2d');
        const socialSharesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($platforms); ?>,
                datasets: [
                    {
                        label: 'Shares',
                        data: <?php echo json_encode($shareData); ?>,
                        backgroundColor: Object.values(<?php echo json_encode($colors); ?>).map(color => color + '99'),
                        borderColor: Object.values(<?php echo json_encode($colors); ?>),
                        borderWidth: 1
                    },
                    {
                        label: 'Clicks',
                        data: <?php echo json_encode($clickData); ?>,
                        backgroundColor: Object.values(<?php echo json_encode($colors); ?>).map(color => color + '55'),
                        borderColor: Object.values(<?php echo json_encode($colors); ?>),
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Mobile menu toggle
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