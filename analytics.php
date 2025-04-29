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

// Add this near the top of the file
$error = '';
try {
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
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM email_opens WHERE newsletter_id = ?");
        $stmt->bind_param("i", $newsletter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $totalOpens = $result->fetch_assoc()['total'];
        $stmt->close();
        
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
        
        // Get geo data for map visualization
        $geoQuery = $db->query("
            SELECT g.country, g.city, g.latitude, g.longitude, COUNT(*) as count
            FROM email_geo_data g
            LEFT JOIN email_opens o ON g.open_id = o.id
            LEFT JOIN link_clicks c ON g.click_id = c.id
            WHERE (o.newsletter_id = $newsletter_id OR c.newsletter_id = $newsletter_id)
            AND g.latitude IS NOT NULL AND g.longitude IS NOT NULL
            GROUP BY g.latitude, g.longitude
            HAVING COUNT(*) > 0
        ");
        
        $geoData = [];
        while ($row = $geoQuery->fetch_assoc()) {
            $geoData[] = $row;
        }
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    // Log the error
    error_log("Analytics error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        .analytics-overview {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (min-width: 992px) {
            .analytics-overview {
                grid-template-columns: repeat(4, 1fr);
            }
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
        
        #map {
            height: 400px;
            border-radius: var(--radius);
            z-index: 1;
        }
        
        .map-container {
            margin-bottom: 30px;
        }
        
        .analytics-tabs {
            display: flex;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background-color: var(--gray-light);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .tab-btn:first-child {
            border-top-left-radius: var(--radius);
            border-bottom-left-radius: var(--radius);
        }
        
        .tab-btn:last-child {
            border-top-right-radius: var(--radius);
            border-bottom-right-radius: var(--radius);
        }
        
        .tab-btn.active {
            background-color: var(--primary);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
                    
                    <?php if ($error): ?>
                        <div class="notification error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
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
                        
                        <div class="analytics-tabs">
                            <button class="tab-btn active" onclick="showTab('charts')">Charts</button>
                            <button class="tab-btn" onclick="showTab('map')">Geographic Map</button>
                            <button class="tab-btn" onclick="showTab('links')">Popular Links</button>
                        </div>
                        
                        <div id="charts-tab" class="tab-content active">
                            <div class="chart-container">
                                <h3>Opens and Clicks Comparison</h3>
                                <canvas id="performanceChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                        
                        <div id="map-tab" class="tab-content">
                            <div class="map-container">
                                <h3>Geographic Distribution</h3>
                                <div id="map"></div>
                            </div>
                        </div>
                        
                        <div id="links-tab" class="tab-content">
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
                            <?php else: ?>
                                <div class="no-data">
                                    <p>No link clicks recorded for this newsletter.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
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
                    }
                }
            }
        });
        
        // Map setup
        const map = L.map('map').setView([20, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Add map markers
        const geoData = <?php echo !empty($geoData) ? json_encode($geoData) : '[]'; ?>;
        geoData.forEach(location => {
            const marker = L.marker([location.latitude, location.longitude]).addTo(map);
            marker.bindPopup(`<b>${location.city || 'Unknown'}, ${location.country || 'Unknown'}</b><br>Interactions: ${location.count}`);
        });
        
        // Tab functionality
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId + '-tab').classList.add('active');
            
            // Activate clicked button
            document.querySelector(`.tab-btn[onclick*="${tabId}"]`).classList.add('active');
            
            // Special case for map - needs to be refreshed when shown
            if(tabId === 'map') {
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
        }
    </script>
    <?php endif; ?>
    <script src="assets/js/sidebar.js"></script>
</body>
</html>