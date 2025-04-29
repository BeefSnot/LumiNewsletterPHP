<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only allow admin access
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Get available themes
$themesResult = $db->query("SELECT id, name FROM themes ORDER BY name ASC");
$themes = [];
while ($themesResult && $row = $themesResult->fetch_assoc()) {
    $themes[] = $row;
}

// Create A/B test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
    $test_name = $_POST['test_name'] ?? '';
    $group_id = $_POST['group_id'] ?? 0;
    $subject_a = $_POST['subject_a'] ?? '';
    $subject_b = $_POST['subject_b'] ?? '';
    $content_a = $_POST['content_a'] ?? '';
    $content_b = $_POST['content_b'] ?? '';
    $split_percentage = $_POST['split_percentage'] ?? 50;
    
    $theme_a = isset($_POST['theme_a']) ? (int)$_POST['theme_a'] : null;
    $theme_b = isset($_POST['theme_b']) ? (int)$_POST['theme_b'] : null;

    // Simple validation
    if (empty($test_name) || empty($group_id) || empty($subject_a) || empty($subject_b) || empty($content_a) || empty($content_b)) {
        $message = 'All fields are required';
        $messageType = 'error';
    } else {
        // Insert A/B test into database
        $stmt = $db->prepare("INSERT INTO ab_tests (name, group_id, subject_a, subject_b, content_a, content_b, theme_a_id, theme_b_id, split_percentage, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sississii", $test_name, $group_id, $subject_a, $subject_b, $content_a, $content_b, $theme_a, $theme_b, $split_percentage);
        
        if ($stmt->execute()) {
            $message = 'A/B test created successfully';
            $messageType = 'success';
        } else {
            $message = 'Error creating A/B test: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Send A/B test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_id = $_POST['test_id'] ?? 0;
    
    // Get the A/B test details
    $stmt = $db->prepare("SELECT * FROM ab_tests WHERE id = ?");
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $test = $result->fetch_assoc();
    $stmt->close();
    
    if (!$test) {
        $message = 'Test not found';
        $messageType = 'error';
    } else {
        // Get subscribers from the group
        $stmt = $db->prepare("SELECT email FROM group_subscriptions WHERE group_id = ?");
        $stmt->bind_param("i", $test['group_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $subscribers = [];
        while ($row = $result->fetch_assoc()) {
            $subscribers[] = $row['email'];
        }
        $stmt->close();
        
        if (empty($subscribers)) {
            $message = 'No subscribers found in selected group';
            $messageType = 'error';
        } else {
            // Divide subscribers based on split percentage
            $total = count($subscribers);
            $test_a_count = floor($total * ($test['split_percentage'] / 100));
            $group_a = array_slice($subscribers, 0, $test_a_count);
            $group_b = array_slice($subscribers, $test_a_count);
            
            // Create newsletter A
            $stmt = $db->prepare("INSERT INTO newsletters (subject, body, sender_id, is_ab_test, ab_test_id, variant) 
                                VALUES (?, ?, ?, 1, ?, 'A')");
            $stmt->bind_param("ssii", $test['subject_a'], $test['content_a'], $_SESSION['user_id'], $test_id);
            $stmt->execute();
            $newsletter_a_id = $stmt->insert_id;
            $stmt->close();
            
            // Create newsletter B
            $stmt = $db->prepare("INSERT INTO newsletters (subject, body, sender_id, is_ab_test, ab_test_id, variant) 
                                VALUES (?, ?, ?, 1, ?, 'B')");
            $stmt->bind_param("ssii", $test['subject_b'], $test['content_b'], $_SESSION['user_id'], $test_id);
            $stmt->execute();
            $newsletter_b_id = $stmt->insert_id;
            $stmt->close();
            
            // Send the newsletters using existing functions
            $config = require 'includes/config.php';

            // Get site URL for tracking
            $settingsResult = $db->query("SELECT value FROM settings WHERE name = 'site_url'");
            if ($settingsResult && $settingsResult->num_rows > 0) {
                $site_url = $settingsResult->fetch_assoc()['value'];
            } else {
                // Fallback to auto-detected URL
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'];
                $path = dirname($_SERVER['PHP_SELF']);
                $site_url = $protocol . $host . rtrim($path, '/');
            }
            
            // Load PHPMailer
            require_once 'includes/init.php';
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_user'];
                $mail->Password = $config['smtp_pass'];
                $mail->SMTPSecure = $config['smtp_secure'];
                $mail->Port = $config['smtp_port'];

                $mail->setFrom($config['smtp_user'], 'Newsletter');
                $mail->isHTML(true);

                // Send version A
                $mail->Subject = $test['subject_a'];
                foreach ($group_a as $recipient) {
                    $mail->addAddress($recipient);
                    
                    // Add tracking
                    $trackableContent = addTrackingToEmail($test['content_a'], $newsletter_a_id, $recipient, $site_url);
                    
                    $mail->Body = $trackableContent;
                    $mail->send();
                    $mail->clearAddresses();
                }
                
                // Send version B
                $mail->Subject = $test['subject_b'];
                foreach ($group_b as $recipient) {
                    $mail->addAddress($recipient);
                    
                    // Add tracking
                    $trackableContent = addTrackingToEmail($test['content_b'], $newsletter_b_id, $recipient, $site_url);
                    
                    $mail->Body = $trackableContent;
                    $mail->send();
                    $mail->clearAddresses();
                }
                
                // Mark test as sent
                $stmt = $db->prepare("UPDATE ab_tests SET sent_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $test_id);
                $stmt->execute();
                $stmt->close();
                
                $message = 'A/B test sent successfully to ' . count($subscribers) . ' recipients';
                $messageType = 'success';
            } 
            catch (\PHPMailer\PHPMailer\Exception $e) {
                $message = 'Error sending A/B test: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Import tracking function from send_newsletter.php
function addTrackingToEmail($html, $newsletter_id, $recipient_email, $site_url) {
    // URL encode the email
    $encoded_email = urlencode($recipient_email);
    
    // Add tracking pixel for opens
    $tracking_pixel = "<img src='$site_url/track_open.php?nid=$newsletter_id&email=$encoded_email' width='1' height='1' alt='' style='display:none;'>";
    $html .= $tracking_pixel;
    
    // Replace links with tracking links
    $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i';
    $html = preg_replace_callback($pattern, function($matches) use ($site_url, $newsletter_id, $encoded_email) {
        $url = $matches[2];
        $encoded_url = urlencode($url);
        $tracking_url = "$site_url/track_click.php?nid=$newsletter_id&email=$encoded_email&url=$encoded_url";
        return "<a href=\"$tracking_url\"";
    }, $html);
    
    return $html;
}

// Get all A/B tests
$testsResult = $db->query("SELECT ab.*, g.name as group_name, 
                          (SELECT COUNT(*) FROM email_opens eo JOIN newsletters n ON eo.newsletter_id = n.id WHERE n.ab_test_id = ab.id AND n.variant = 'A') as opens_a,
                          (SELECT COUNT(*) FROM email_opens eo JOIN newsletters n ON eo.newsletter_id = n.id WHERE n.ab_test_id = ab.id AND n.variant = 'B') as opens_b,
                          (SELECT COUNT(*) FROM link_clicks lc JOIN newsletters n ON lc.newsletter_id = n.id WHERE n.ab_test_id = ab.id AND n.variant = 'A') as clicks_a,
                          (SELECT COUNT(*) FROM link_clicks lc JOIN newsletters n ON lc.newsletter_id = n.id WHERE n.ab_test_id = ab.id AND n.variant = 'B') as clicks_b
                          FROM ab_tests ab 
                          LEFT JOIN groups g ON ab.group_id = g.id
                          ORDER BY ab.created_at DESC");
$tests = [];
while ($testsResult && $row = $testsResult->fetch_assoc()) {
    $tests[] = $row;
}

// Get all groups for dropdown
$groupsResult = $db->query("SELECT id, name FROM groups");
$groups = [];
while ($groupsResult && $row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A/B Testing | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/tinymce/tinymce/js/tinymce/tinymce.min.js"></script>
    <style>
        .ab-test-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .ab-test-header {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ab-test-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }
        
        .ab-test-meta {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .ab-test-body {
            padding: 15px;
        }
        
        .ab-test-variants {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 767px) {
            .ab-test-variants {
                grid-template-columns: 1fr;
            }
        }
        
        .variant-card {
            border: 1px solid #f0f0f0;
            border-radius: var(--radius);
            padding: 15px;
        }
        
        .variant-header {
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .variant-subject {
            font-style: italic;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .variant-content {
            max-height: 100px;
            overflow: hidden;
            position: relative;
            margin-bottom: 10px;
        }
        
        .variant-content::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(rgba(255, 255, 255, 0), #ffffff);
        }
        
        .variant-stats {
            background: var(--gray-light);
            padding: 8px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
        }
        
        .variant-metric {
            text-align: center;
        }
        
        .metric-value {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .metric-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .winner-badge {
            background: var(--accent);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .split-slider-container {
            margin: 20px 0;
        }
        
        .split-slider {
            display: flex;
            align-items: center;
        }
        
        .split-slider input[type="range"] {
            flex: 1;
        }
        
        .split-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        .split-value {
            text-align: center;
            font-weight: 600;
            margin-top: 5px;
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
                    <li><a href="ab_testing.php" class="nav-item active"><i class="fas fa-flask"></i> A/B Testing</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
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
                    <h1>A/B Testing</h1>
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
                    <h2><i class="fas fa-flask"></i> Newsletter A/B Testing</h2>
                </div>
                <div class="card-body">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="showTab('create-test', this)">Create Test</button>
                        <button class="tab-btn" onclick="showTab('view-tests', this)">View Results</button>
                    </div>
                    
                    <div id="create-test" class="tab-content active">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="test_name">Test Name:</label>
                                <input type="text" id="test_name" name="test_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="group_id">Recipient Group:</label>
                                <select id="group_id" name="group_id" required>
                                    <option value="">-- Select Group --</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="split-slider-container">
                                <label for="split_percentage">Test Split (A/B):</label>
                                <div class="split-slider">
                                    <input type="range" id="split_percentage" name="split_percentage" min="10" max="90" value="50" oninput="updateSplitValue()">
                                </div>
                                <div class="split-labels">
                                    <span>Version A</span>
                                    <span>Version B</span>
                                </div>
                                <div class="split-value">
                                    <span id="variant_a_percent">50%</span> / <span id="variant_b_percent">50%</span>
                                </div>
                            </div>
                            
                            <div class="ab-test-variants">
                                <div class="variant-card">
                                    <div class="variant-header">Variant A</div>
                                    <div class="form-group">
                                        <label for="subject_a">Subject Line A:</label>
                                        <input type="text" id="subject_a" name="subject_a" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="theme_a">Email Theme A:</label>
                                        <select id="theme_a" name="theme_a">
                                            <option value="">Default Theme</option>
                                            <?php foreach ($themes as $theme): ?>
                                                <option value="<?php echo $theme['id']; ?>"><?php echo htmlspecialchars($theme['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="content_a">Email Content A:</label>
                                        <textarea id="content_a" name="content_a"></textarea>
                                    </div>
                                </div>
                                
                                <div class="variant-card">
                                    <div class="variant-header">Variant B</div>
                                    <div class="form-group">
                                        <label for="subject_b">Subject Line B:</label>
                                        <input type="text" id="subject_b" name="subject_b" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="theme_b">Email Theme B:</label>
                                        <select id="theme_b" name="theme_b">
                                            <option value="">Default Theme</option>
                                            <?php foreach ($themes as $theme): ?>
                                                <option value="<?php echo $theme['id']; ?>"><?php echo htmlspecialchars($theme['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="content_b">Email Content B:</label>
                                        <textarea id="content_b" name="content_b"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="create_test" class="btn btn-primary">
                                    <i class="fas fa-flask"></i> Create A/B Test
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="view-tests" class="tab-content">
                        <?php if (empty($tests)): ?>
                            <div class="no-data">
                                <i class="fas fa-flask" style="font-size: 4rem; color: var(--gray-light); margin-bottom: 20px;"></i>
                                <h3>No A/B tests found</h3>
                                <p>Create your first A/B test to start optimizing your newsletters.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tests as $test): ?>
                                <div class="ab-test-card">
                                    <div class="ab-test-header">
                                        <div>
                                            <h3 class="ab-test-name"><?php echo htmlspecialchars($test['name']); ?></h3>
                                            <div class="ab-test-meta">
                                                Group: <?php echo htmlspecialchars($test['group_name']); ?> | 
                                                Created: <?php echo date('M j, Y', strtotime($test['created_at'])); ?>
                                                <?php if ($test['sent_at']): ?> | 
                                                    Sent: <?php echo date('M j, Y', strtotime($test['sent_at'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!$test['sent_at']): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                                <button type="submit" name="send_test" class="btn btn-sm">Send Now</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="ab-test-body">
                                        <div class="ab-test-variants">
                                            <div class="variant-card">
                                                <div class="variant-header">
                                                    Variant A (<?php echo $test['split_percentage']; ?>%)
                                                    <?php if ($test['opens_a'] > $test['opens_b'] && $test['sent_at']): ?>
                                                        <span class="winner-badge">WINNER</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="variant-subject"><?php echo htmlspecialchars($test['subject_a']); ?></div>
                                                <div class="variant-content">
                                                    <?php echo strip_tags($test['content_a']); ?>
                                                </div>
                                                <?php if ($test['sent_at']): ?>
                                                    <div class="variant-stats">
                                                        <div class="variant-metric">
                                                            <div class="metric-value"><?php echo $test['opens_a']; ?></div>
                                                            <div class="metric-label">Opens</div>
                                                        </div>
                                                        <div class="variant-metric">
                                                            <div class="metric-value"><?php echo $test['clicks_a']; ?></div>
                                                            <div class="metric-label">Clicks</div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="variant-card">
                                                <div class="variant-header">
                                                    Variant B (<?php echo 100 - $test['split_percentage']; ?>%)
                                                    <?php if ($test['opens_b'] > $test['opens_a'] && $test['sent_at']): ?>
                                                        <span class="winner-badge">WINNER</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="variant-subject"><?php echo htmlspecialchars($test['subject_b']); ?></div>
                                                <div class="variant-content">
                                                    <?php echo strip_tags($test['content_b']); ?>
                                                </div>
                                                <?php if ($test['sent_at']): ?>
                                                    <div class="variant-stats">
                                                        <div class="variant-metric">
                                                            <div class="metric-value"><?php echo $test['opens_b']; ?></div>
                                                            <div class="metric-label">Opens</div>
                                                        </div>
                                                        <div class="variant-metric">
                                                            <div class="metric-value"><?php echo $test['clicks_b']; ?></div>
                                                            <div class="metric-label">Clicks</div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#content_a, #content_b',
            plugins: 'print preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap hr pagebreak nonbreaking anchor toc insertdatetime advlist lists wordcount imagetools textpattern noneditable help charmap quickbars emoticons',
            toolbar: 'undo redo | bold italic underline strikethrough | fontselect fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  preview save print',
            height: 300,
            menubar: 'file edit view insert format tools table help',
            content_css: 'assets/css/newsletter-style.css',
            relative_urls: false,
            remove_script_host: false,
            convert_urls: true,
        });
        
        function updateSplitValue() {
            const slider = document.getElementById('split_percentage');
            const value = slider.value;
            document.getElementById('variant_a_percent').textContent = value + '%';
            document.getElementById('variant_b_percent').textContent = (100 - value) + '%';
        }
        
        function showTab(tabId, el) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Activate clicked button
            el.classList.add('active');
        }
        
        // Initialize the split value display
        updateSplitValue();
    </script>
</body>
</html>