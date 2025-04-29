<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

// Get newsletter ID from query parameters
$newsletter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get list of newsletters for dropdown
$newslettersQuery = $db->query("SELECT id, subject, sent_at FROM newsletters ORDER BY sent_at DESC");
$newsletters = [];
while ($row = $newslettersQuery->fetch_assoc()) {
    $newsletters[] = $row;
}

// Get analytics for selected newsletter
$opens = $clicks = [];
$totalOpens = $totalClicks = 0;
$uniqueOpens = $uniqueClicks = 0;

if ($newsletter_id > 0) {
    // Get total and unique opens
    $opensQuery = $db->query("SELECT COUNT(*) as total FROM email_opens WHERE newsletter_id = $newsletter_id");
    $totalOpens = $opensQuery->fetch_assoc()['total'];
    
    $uniqueOpensQuery = $db->query("SELECT COUNT(DISTINCT email) as unique_opens FROM email_opens WHERE newsletter_id = $newsletter_id");
    $uniqueOpens = $uniqueOpensQuery->fetch_assoc()['unique_opens'];
    
    // Get total and unique clicks
    $clicksQuery = $db->query("SELECT COUNT(*) as total FROM link_clicks WHERE newsletter_id = $newsletter_id");
    $totalClicks = $clicksQuery->fetch_assoc()['total'];
    
    $uniqueClicksQuery = $db->query("SELECT COUNT(DISTINCT email) as unique_clicks FROM link_clicks WHERE newsletter_id = $newsletter_id");
    $uniqueClicks = $uniqueClicksQuery->fetch_assoc()['unique_clicks'];
    
    // Get popular links
    $popularLinksQuery = $db->query("SELECT original_url, COUNT(*) as clicks 
                                     FROM link_clicks 
                                     WHERE newsletter_id = $newsletter_id 
                                     GROUP BY original_url 
                                     ORDER BY clicks DESC 
                                     LIMIT 5");
    $popularLinks = [];
    while ($row = $popularLinksQuery->fetch_assoc()) {
        $popularLinks[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Analytics | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-overview {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background-color: #ffffff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .metric-label {
            color: var(--gray);
            font-weight: 500;
        }
        
        .chart-container {
            background-color: #ffffff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .analytics-filter {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .analytics-filter label {
            margin-right: 10px;
        }
        
        .popular-links {
            background-color: #ffffff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
        }
        
        .popular-links h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .link-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .link-item:last-child {
            border-bottom: none;
        }
        
        .link-url {
            max-width: 80%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .link-clicks {
            font-weight: 600;
            color: var(--accent);
        }
        
        .no-data {
            text-align: center;
            padding: 50px 0;
            color: var(--gray);
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
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="analytics.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
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
                    <h1>Newsletter Analytics</h1>
                </div>
            </header>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Email Performance Analytics</h2>
                </div>
                <div class="card-body">
                    <form class="analytics-filter" method="get">
                        <label for="newsletter"><strong>Select Newsletter:</strong></label>
                        <select name="id" id="newsletter" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Select a Newsletter --</option>
                            <?php foreach ($newsletters as $newsletter): ?>
                                <option value="<?php echo $newsletter['id']; ?>" <?php echo ($newsletter_id == $newsletter['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($newsletter['subject']) . ' (' . date('Y-m-d', strtotime($newsletter['sent_at'])) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    
                    <?php if ($newsletter_id > 0): ?>
                        <div class="analytics-overview">
                            <div class="metric-card">
                                <div class="metric-label">Total Opens</div>
                                <div class="metric-value"><?php echo number_format($totalOpens); ?></div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Unique Opens</div>
                                <div class="metric-value"><?php echo number_format($uniqueOpens); ?></div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Total Clicks</div>
                                <div class="metric-value"><?php echo number_format($totalClicks); ?></div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Unique Clicks</div>
                                <div class="metric-value"><?php echo number_format($uniqueClicks); ?></div>
                            </div>
                        </div>
                        
                        <div class="chart-container">
                            <h3>Opens and Clicks Comparison</h3>
                            <canvas id="performanceChart" width="400" height="200"></canvas>
                        </div>
                        
                        <?php if (!empty($popularLinks)): ?>
                            <div class="popular-links">
                                <h3>Most Clicked Links</h3>
                                <?php foreach ($popularLinks as $link): ?>
                                    <div class="link-item">
                                        <div class="link-url" title="<?php echo htmlspecialchars($link['original_url']); ?>">
                                            <?php echo htmlspecialchars($link['original_url']); ?>
                                        </div>
                                        <div class="link-clicks">
                                            <?php echo $link['clicks']; ?> clicks
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-chart-bar" style="font-size: 4rem; color: var(--gray-light); margin-bottom: 20px;"></i>
                            <h3>Select a newsletter to view analytics</h3>
                            <p>You'll see detailed information about opens, clicks, and engagement.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <?php if ($newsletter_id > 0): ?>
    <script>
        // Chart setup
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Opens', 'Clicks'],
                datasets: [{
                    label: 'Total',
                    data: [<?php echo $totalOpens; ?>, <?php echo $totalClicks; ?>],
                    backgroundColor: ['rgba(66, 133, 244, 0.8)', 'rgba(52, 168, 83, 0.8)'],
                    borderColor: ['rgba(66, 133, 244, 1)', 'rgba(52, 168, 83, 1)'],
                    borderWidth: 1
                }, {
                    label: 'Unique',
                    data: [<?php echo $uniqueOpens; ?>, <?php echo $uniqueClicks; ?>],
                    backgroundColor: ['rgba(66, 133, 244, 0.4)', 'rgba(52, 168, 83, 0.4)'],
                    borderColor: ['rgba(66, 133, 244, 1)', 'rgba(52, 168, 83, 1)'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>