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

// Create new segment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_segment'])) {
    $name = $_POST['segment_name'] ?? '';
    $description = $_POST['segment_description'] ?? '';
    $criteria = $_POST['segment_criteria'] ?? '';
    
    if (empty($name)) {
        $message = 'Segment name is required';
        $messageType = 'error';
    } else {
        // Create new segment
        $stmt = $db->prepare("INSERT INTO subscriber_segments (name, description, criteria) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $description, $criteria);
        
        if ($stmt->execute()) {
            $message = 'Segment created successfully';
            $messageType = 'success';
        } else {
            $message = 'Error creating segment: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Delete segment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_segment'])) {
    $segment_id = $_POST['segment_id'] ?? 0;
    
    if ($segment_id > 0) {
        $stmt = $db->prepare("DELETE FROM subscriber_segments WHERE id = ?");
        $stmt->bind_param("i", $segment_id);
        
        if ($stmt->execute()) {
            $message = 'Segment deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Error deleting segment: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Add tag to subscribers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag'])) {
    $tag = $_POST['tag_name'] ?? '';
    $group_id = $_POST['group_id'] ?? 0;
    
    if (empty($tag) || $group_id <= 0) {
        $message = 'Tag name and group are required';
        $messageType = 'error';
    } else {
        // Get subscribers from the selected group
        $stmt = $db->prepare("SELECT email FROM group_subscriptions WHERE group_id = ?");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subscribers = [];
        $tagCount = 0;
        
        while ($row = $result->fetch_assoc()) {
            $email = $row['email'];
            
            // Check if tag already exists for this email
            $checkStmt = $db->prepare("SELECT id FROM subscriber_tags WHERE email = ? AND tag = ?");
            $checkStmt->bind_param("ss", $email, $tag);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                // Insert new tag
                $insertStmt = $db->prepare("INSERT INTO subscriber_tags (email, tag) VALUES (?, ?)");
                $insertStmt->bind_param("ss", $email, $tag);
                if ($insertStmt->execute()) {
                    $tagCount++;
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        }
        $stmt->close();
        
        $message = "Added tag '$tag' to $tagCount subscribers";
        $messageType = 'success';
    }
}

// Calculate engagement scores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_scores'])) {
    // Start a transaction for better performance
    $db->begin_transaction();
    
    try {
        // First, get all subscribers
        $result = $db->query("SELECT DISTINCT email FROM group_subscriptions");
        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }
        
        // For each subscriber, calculate their engagement score
        foreach ($emails as $email) {
            // Get open count
            $stmt = $db->prepare("SELECT COUNT(*) as open_count, MAX(opened_at) as last_open FROM email_opens WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $openData = $stmt->get_result()->fetch_assoc();
            $totalOpens = $openData['open_count'] ?? 0;
            $lastOpen = $openData['last_open'] ?? null;
            $stmt->close();
            
            // Get click count
            $stmt = $db->prepare("SELECT COUNT(*) as click_count, MAX(clicked_at) as last_click FROM link_clicks WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $clickData = $stmt->get_result()->fetch_assoc();
            $totalClicks = $clickData['click_count'] ?? 0;
            $lastClick = $clickData['last_click'] ?? null;
            $stmt->close();
            
            // Calculate score: opens = 1 point, clicks = 3 points
            $score = ($totalOpens * 1) + ($totalClicks * 3);
            
            // Update or insert score
            $stmt = $db->prepare("INSERT INTO subscriber_scores (email, engagement_score, last_open_date, last_click_date, total_opens, total_clicks) 
                               VALUES (?, ?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               engagement_score = VALUES(engagement_score),
                               last_open_date = VALUES(last_open_date),
                               last_click_date = VALUES(last_click_date),
                               total_opens = VALUES(total_opens),
                               total_clicks = VALUES(total_clicks),
                               last_calculated = CURRENT_TIMESTAMP");
            $stmt->bind_param("ssssii", $email, $score, $lastOpen, $lastClick, $totalOpens, $totalClicks);
            $stmt->execute();
            $stmt->close();
        }
        
        $db->commit();
        $message = 'Engagement scores calculated successfully for ' . count($emails) . ' subscribers';
        $messageType = 'success';
    } catch (Exception $e) {
        $db->rollback();
        $message = 'Error calculating engagement scores: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Run segment query and get subscriber count
function getSegmentSubscriberCount($db, $criteria) {
    if (empty($criteria)) return 0;
    
    try {
        $baseQuery = "SELECT COUNT(DISTINCT ss.email) AS total FROM group_subscriptions ss";
        
        // Add joins based on criteria
        if (strpos($criteria, 'engagement_score') !== false || 
            strpos($criteria, 'total_opens') !== false || 
            strpos($criteria, 'total_clicks') !== false) {
            $baseQuery .= " LEFT JOIN subscriber_scores sco ON ss.email = sco.email";
        }
        
        if (strpos($criteria, 'tag') !== false) {
            $baseQuery .= " LEFT JOIN subscriber_tags st ON ss.email = st.email";
        }
        
        // Add WHERE clause
        $baseQuery .= " WHERE $criteria";
        
        $result = $db->query($baseQuery);
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['total'];
        }
    } catch (Exception $e) {
        return 'Error';
    }
    
    return 0;
}

// Fetch all segments
$segments = [];
$segmentsResult = $db->query("SELECT * FROM subscriber_segments ORDER BY created_at DESC");
if ($segmentsResult) {
    while ($row = $segmentsResult->fetch_assoc()) {
        $segments[] = $row;
    }
}

// Get all groups for dropdown
$groupsResult = $db->query("SELECT id, name FROM groups ORDER BY name");
$groups = [];
while ($groupsResult && $row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}

// Get popular tags for suggestions
$tagsResult = $db->query("SELECT tag, COUNT(*) as count FROM subscriber_tags GROUP BY tag ORDER BY count DESC LIMIT 10");
$popularTags = [];
while ($tagsResult && $row = $tagsResult->fetch_assoc()) {
    $popularTags[] = $row;
}

// Get subscriber statistics for dashboard
$totalSubscribersQuery = $db->query("SELECT COUNT(DISTINCT email) as total FROM group_subscriptions");
$totalSubscribers = $totalSubscribersQuery ? $totalSubscribersQuery->fetch_assoc()['total'] : 0;

$taggedSubscribersQuery = $db->query("SELECT COUNT(DISTINCT email) as total FROM subscriber_tags");
$taggedSubscribers = $taggedSubscribersQuery ? $taggedSubscribersQuery->fetch_assoc()['total'] : 0;

$highEngagementQuery = $db->query("SELECT COUNT(*) as total FROM subscriber_scores WHERE engagement_score > 10");
$highEngagement = $highEngagementQuery ? $highEngagementQuery->fetch_assoc()['total'] : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriber Segments | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .segment-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .segment-header {
            padding: 15px;
            background-color: #f9f9f9;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .segment-name {
            font-weight: 600;
            color: var(--primary);
            margin: 0;
            font-size: 1.1rem;
        }
        
        .segment-body {
            padding: 15px;
        }
        
        .segment-description {
            color: var(--gray);
            margin-bottom: 15px;
            font-style: italic;
        }
        
        .segment-criteria {
            background-color: #f7f7f7;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin-bottom: 15px;
        }
        
        .segment-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .segment-actions {
            display: flex;
            gap: 10px;
        }
        
        .criteria-builder {
            margin-bottom: 20px;
        }
        
        .criteria-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .criteria-group {
            border: 1px solid #eee;
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 15px;
        }
        
        .criteria-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .segment-count {
            background-color: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--gray);
            font-weight: 500;
        }
        
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        
        .tag {
            background-color: #f0f0f0;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .tag:hover {
            background-color: #e0e0e0;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .tab-btn {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-weight: 500;
            color: var(--gray);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            color: var(--primary);
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
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
                    <li><a href="segments.php" class="nav-item active"><i class="fas fa-tags"></i> Segments</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="ab_testing.php" class="nav-item"><i class="fas fa-flask"></i> A/B Testing</a></li>
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
                    <h1>Subscriber Segmentation</h1>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-label">Total Subscribers</div>
                    <div class="stat-value"><?php echo number_format($totalSubscribers); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Tagged Subscribers</div>
                    <div class="stat-value"><?php echo number_format($taggedSubscribers); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">High Engagement</div>
                    <div class="stat-value"><?php echo number_format($highEngagement); ?></div>
                </div>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('tags-tab', this)">Manage Tags</button>
                <button class="tab-btn" onclick="showTab('segments-tab', this)">Subscriber Segments</button>
                <button class="tab-btn" onclick="showTab('engagement-tab', this)">Engagement Scores</button>
            </div>
            
            <div id="tags-tab" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-tags"></i> Add Tags to Subscribers</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="tag_name">Tag Name:</label>
                                <input type="text" id="tag_name" name="tag_name" required>
                                <small>Use descriptive tags like "newsletter-2023", "webinar-attendee", "product-interested", etc.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="group_id">Apply to Group:</label>
                                <select id="group_id" name="group_id" required>
                                    <option value="">-- Select Group --</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if (!empty($popularTags)): ?>
                                <div class="form-group">
                                    <label>Suggested Tags:</label>
                                    <div class="tag-cloud">
                                        <?php foreach ($popularTags as $tag): ?>
                                            <span class="tag" onclick="document.getElementById('tag_name').value='<?php echo htmlspecialchars($tag['tag']); ?>'">
                                                <?php echo htmlspecialchars($tag['tag']); ?> (<?php echo $tag['count']; ?>)
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-actions">
                                <button type="submit" name="add_tag" class="btn btn-primary">Add Tag</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div id="segments-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-filter"></i> Create Subscriber Segment</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="segment_name">Segment Name:</label>
                                <input type="text" id="segment_name" name="segment_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="segment_description">Description:</label>
                                <textarea id="segment_description" name="segment_description" rows="2"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Segment Criteria:</label>
                                <div class="criteria-builder">
                                    <div class="criteria-group">
                                        <div class="criteria-header">
                                            <strong>Filter by</strong>
                                        </div>
                                        <div class="criteria-row">
                                            <select id="field-selector" class="form-control">
                                                <option value="">-- Select Field --</option>
                                                <option value="tag">Tag</option>
                                                <option value="group">Group</option>
                                                <option value="engagement">Engagement Score</option>
                                                <option value="opens">Total Opens</option>
                                                <option value="clicks">Total Clicks</option>
                                                <option value="last_open">Last Open Date</option>
                                            </select>
                                            
                                            <select id="operator" class="form-control">
                                                <option value="=">=</option>
                                                <option value=">">></option>
                                                <option value="<"><</option>
                                                <option value="<>">≠</option>
                                                <option value="LIKE">Contains</option>
                                            </select>
                                            
                                            <input type="text" id="criteria-value" class="form-control" placeholder="Value">
                                            
                                            <button type="button" class="btn btn-sm" onclick="addCriteria()">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="criteria-preview">
                                        <label>SQL WHERE Clause:</label>
                                        <textarea id="segment_criteria" name="segment_criteria" rows="3" class="form-control"></textarea>
                                        <small>Example: ss.group_id = 1 OR (st.tag = 'newsletter-2023' AND sco.engagement_score > 5)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="create_segment" class="btn btn-primary">Create Segment</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <h3>Your Segments</h3>
                <?php if (empty($segments)): ?>
                    <p>No segments created yet. Create your first segment above.</p>
                <?php else: ?>
                    <?php foreach ($segments as $segment): ?>
                        <div class="segment-card">
                            <div class="segment-header">
                                <h3 class="segment-name">
                                    <?php echo htmlspecialchars($segment['name']); ?>
                                    <span class="segment-count">
                                        <?php echo getSegmentSubscriberCount($db, $segment['criteria']); ?> subscribers
                                    </span>
                                </h3>
                                <div class="segment-actions">
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this segment?');">
                                        <input type="hidden" name="segment_id" value="<?php echo $segment['id']; ?>">
                                        <button type="submit" name="delete_segment" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="segment-body">
                                <?php if (!empty($segment['description'])): ?>
                                    <div class="segment-description"><?php echo htmlspecialchars($segment['description']); ?></div>
                                <?php endif; ?>
                                
                                <div class="segment-criteria">
                                    <code><?php echo htmlspecialchars($segment['criteria']); ?></code>
                                </div>
                                
                                <div class="segment-meta">
                                    <span>Created: <?php echo date('M j, Y', strtotime($segment['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div id="engagement-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-line"></i> Subscriber Engagement</h2>
                    </div>
                    <div class="card-body">
                        <p>Calculate engagement scores for your subscribers based on their interaction with your newsletters.</p>
                        <p><strong>Scoring method:</strong></p>
                        <ul>
                            <li>Each email open = 1 point</li>
                            <li>Each link click = 3 points</li>
                        </ul>
                        
                        <form method="post" action="">
                            <div class="form-actions">
                                <button type="submit" name="calculate_scores" class="btn btn-primary">
                                    <i class="fas fa-calculator"></i> Calculate Engagement Scores
                                </button>
                            </div>
                        </form>
                        
                        <div class="table-responsive" style="margin-top: 20px;">
                            <h3>Top Engaged Subscribers</h3>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Score</th>
                                        <th>Opens</th>
                                        <th>Clicks</th>
                                        <th>Last Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $engagementQuery = $db->query("SELECT * FROM subscriber_scores ORDER BY engagement_score DESC LIMIT 10");
                                    if ($engagementQuery) {
                                        while ($row = $engagementQuery->fetch_assoc()) {
                                            $lastActivity = $row['last_click_date'] ? $row['last_click_date'] : $row['last_open_date'];
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                                            echo '<td>' . $row['engagement_score'] . '</td>';
                                            echo '<td>' . $row['total_opens'] . '</td>';
                                            echo '<td>' . $row['total_clicks'] . '</td>';
                                            echo '<td>' . ($lastActivity ? date('M j, Y', strtotime($lastActivity)) : 'Never') . '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script>
        function showTab(tabId, element) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show the selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Mark the clicked button as active
            element.classList.add('active');
        }
        
        function addCriteria() {
            const field = document.getElementById('field-selector').value;
            const operator = document.getElementById('operator').value;
            const value = document.getElementById('criteria-value').value;
            
            if (!field || !value) {
                alert('Please select a field and enter a value');
                return;
            }
            
            let fieldColumn = '';
            
            switch (field) {
                case 'tag':
                    fieldColumn = 'st.tag';
                    break;
                case 'group':
                    fieldColumn = 'ss.group_id';
                    break;
                case 'engagement':
                    fieldColumn = 'sco.engagement_score';
                    break;
                case 'opens':
                    fieldColumn = 'sco.total_opens';
                    break;
                case 'clicks':
                    fieldColumn = 'sco.total_clicks';
                    break;
                case 'last_open':
                    fieldColumn = 'sco.last_open_date';
                    break;
            }
            
            const currentCriteria = document.getElementById('segment_criteria').value;
            let newCriteria = '';
            
            // Format the value based on the field type
            let formattedValue = value;
            if (field === 'tag' || field === 'last_open') {
                formattedValue = "'" + value.replace(/'/g, "''") + "'";
            }
            
            if (operator === 'LIKE') {
                formattedValue = "'%" + value.replace(/'/g, "''") + "%'";
            }
            
            // Combine with existing criteria if any
            if (currentCriteria) {
                newCriteria = `${currentCriteria} AND ${fieldColumn} ${operator} ${formattedValue}`;
            } else {
                newCriteria = `${fieldColumn} ${operator} ${formattedValue}`;
            }
            
            document.getElementById('segment_criteria').value = newCriteria;
            
            // Reset the value field
            document.getElementById('criteria-value').value = '';
        }
    </script>
</body>
</html>