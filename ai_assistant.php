<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if AI Assistant is enabled
$featureResult = $db->query("SELECT enabled FROM features WHERE feature_name = 'ai_assistant'");
$aiEnabled = true; // Default to true in case the query fails
if ($featureResult && $featureResult->num_rows > 0) {
    $aiEnabled = (bool)$featureResult->fetch_assoc()['enabled'];
}

if (!$aiEnabled) {
    header('Location: admin.php?error=AI+Assistant+is+disabled');
    exit();
}

// Get API settings
$result = $db->query("SELECT * FROM ai_settings LIMIT 1");
$ai_settings = $result->fetch_assoc();

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

$message = '';
$messageType = '';

// Handle API settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $api_provider = $_POST['api_provider'] ?? 'openai';
    $api_key = $_POST['api_key'] ?? '';
    $model = $_POST['model'] ?? 'gpt-3.5-turbo';
    $max_tokens = (int)($_POST['max_tokens'] ?? 500);
    $temperature = (float)($_POST['temperature'] ?? 0.7);
    
    if (empty($api_key)) {
        $message = 'API key is required';
        $messageType = 'error';
    } else {
        $stmt = $db->prepare("UPDATE ai_settings SET 
                            api_provider = ?, 
                            api_key = ?, 
                            model = ?, 
                            max_tokens = ?, 
                            temperature = ?");
        $stmt->bind_param('sssid', $api_provider, $api_key, $model, $max_tokens, $temperature);
        
        if ($stmt->execute()) {
            $message = 'AI settings updated successfully';
            $messageType = 'success';
            
            // Refresh settings
            $result = $db->query("SELECT * FROM ai_settings LIMIT 1");
            $ai_settings = $result->fetch_assoc();
        } else {
            $message = 'Error updating settings: ' . $stmt->error;
            $messageType = 'error';
        }
    }
}

// Process subject analysis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze_subject'])) {
    $subject = $_POST['subject'] ?? '';
    
    if (empty($subject)) {
        $message = 'Please enter a subject line to analyze';
        $messageType = 'error';
    } else {
        // In a real implementation, you would call the AI API here
        // For demonstration, we'll provide mock suggestions
        
        $suggestions = [
            [
                'subject' => 'Transform Your Business: 5 Game-Changing Strategies Inside',
                'reason' => 'Uses action verb and specific number for better open rates',
                'predicted_open_rate' => 0.28
            ],
            [
                'subject' => '[Limited Time] Your Exclusive Access to Our Latest Innovations',
                'reason' => 'Creates urgency and exclusivity',
                'predicted_open_rate' => 0.25
            ],
            [
                'subject' => 'What Our Most Successful Customers Discovered Last Month',
                'reason' => 'Leverages social proof and curiosity',
                'predicted_open_rate' => 0.26
            ]
        ];
        
        // Store suggestions in database
        foreach ($suggestions as $suggestion) {
            $stmt = $db->prepare("INSERT INTO subject_suggestions 
                                (original_subject, suggested_subject, reason, predicted_open_rate) 
                                VALUES (?, ?, ?, ?)");
            $stmt->bind_param('sssd', $subject, $suggestion['subject'], 
                              $suggestion['reason'], $suggestion['predicted_open_rate']);
            $stmt->execute();
        }
        
        $message = 'Subject line analysis complete. See suggestions below.';
        $messageType = 'success';
    }
}

// Fetch recent suggestions
$subjectSuggestions = [];
$result = $db->query("SELECT * FROM subject_suggestions ORDER BY created_at DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $subjectSuggestions[] = $row;
}

$contentSuggestions = [];
$result = $db->query("SELECT * FROM content_suggestions ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $contentSuggestions[] = $row;
}

$contentAnalyses = [];
$result = $db->query("SELECT * FROM content_analysis ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $contentAnalyses[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .ai-suggestion {
            background: #f8f9ff;
            border-left: 3px solid #4285f4;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 0 4px 4px 0;
        }
        
        .suggestion-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .suggestion-title {
            font-weight: 600;
            color: #4285f4;
        }
        
        .suggestion-score {
            background: rgba(66, 133, 244, 0.1);
            color: #4285f4;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85rem;
        }
        
        .suggestion-content {
            margin-bottom: 10px;
        }
        
        .suggestion-reason {
            font-size: 0.9rem;
            font-style: italic;
            color: #666;
        }
        
        .suggestion-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        
        .tabs {
            margin-bottom: 20px;
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
                    <h1>AI Content Assistant</h1>
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
                    <h2><i class="fas fa-robot"></i> AI Assistant Tools</h2>
                </div>
                <div class="card-body">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="showTab('subject-tab', this)">Subject Line Helper</button>
                        <button class="tab-btn" onclick="showTab('content-tab', this)">Content Enhancement</button>
                        <button class="tab-btn" onclick="showTab('analysis-tab', this)">Content Analysis</button>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <button class="tab-btn" onclick="showTab('settings-tab', this)">AI Settings</button>
                        <?php endif; ?>
                    </div>
                    
                    <div id="subject-tab" class="tab-content active">
                        <h3>Subject Line Optimizer</h3>
                        <p>Get AI-powered suggestions to improve your email subject lines and increase open rates.</p>
                        
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="subject">Enter your subject line:</label>
                                <input type="text" id="subject" name="subject" placeholder="Your draft subject line here">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="analyze_subject" class="btn btn-primary">
                                    <i class="fas fa-magic"></i> Get Suggestions
                                </button>
                            </div>
                        </form>
                        
                        <h3>Recent Suggestions</h3>
                        <?php if (empty($subjectSuggestions)): ?>
                            <p>No subject line suggestions yet. Try the optimizer above!</p>
                        <?php else: ?>
                            <?php foreach ($subjectSuggestions as $suggestion): ?>
                                <div class="ai-suggestion">
                                    <div class="suggestion-header">
                                        <span class="suggestion-title">Suggested Subject</span>
                                        <span class="suggestion-score"><?php echo round($suggestion['predicted_open_rate'] * 100); ?>% Predicted Open Rate</span>
                                    </div>
                                    <div class="suggestion-content">
                                        <strong><?php echo htmlspecialchars($suggestion['suggested_subject']); ?></strong>
                                    </div>
                                    <div class="suggestion-reason">
                                        <?php echo htmlspecialchars($suggestion['reason'] ?? 'No specific reasoning provided'); ?>
                                    </div>
                                    <div class="suggestion-actions">
                                        <button class="btn btn-sm btn-outline" onclick="copyToClipboard('<?php echo addslashes($suggestion['suggested_subject']); ?>')">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                        <button class="btn btn-sm btn-primary">
                                            <i class="fas fa-check"></i> Use This
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div id="content-tab" class="tab-content">
                        <h3>Content Enhancement</h3>
                        <p>Improve your newsletter content with AI-powered suggestions for clarity, engagement, and impact.</p>
                        
                        <form method="post">
                            <div class="form-group">
                                <label for="content">Paste your content to enhance:</label>
                                <textarea id="content" name="content" rows="8" placeholder="Enter newsletter content to get improvement suggestions..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Enhancement options:</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="improve_clarity" checked> Improve clarity
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="improve_engagement" checked> Enhance engagement
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="fix_grammar" checked> Fix grammar
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="add_cta" checked> Optimize calls-to-action
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="enhance_content" class="btn btn-primary">
                                    <i class="fas fa-wand-magic-sparkles"></i> Enhance Content
                                </button>
                            </div>
                        </form>
                        
                        <div class="placeholder-message">
                            <p><i class="fas fa-info-circle"></i> Content enhancements will appear here after processing.</p>
                        </div>
                    </div>
                    
                    <div id="analysis-tab" class="tab-content">
                        <h3>Content Analysis</h3>
                        <p>Get detailed analysis of your newsletter content including readability, sentiment, and spam score.</p>
                        
                        <form method="post">
                            <div class="form-group">
                                <label for="analyze_content">Paste your content to analyze:</label>
                                <textarea id="analyze_content" name="analyze_content" rows="8" placeholder="Enter newsletter content to analyze..."></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="analyze_content_btn" class="btn btn-primary">
                                    <i class="fas fa-chart-bar"></i> Analyze Content
                                </button>
                            </div>
                        </form>
                        
                        <div class="placeholder-message">
                            <p><i class="fas fa-info-circle"></i> Content analysis results will appear here after processing.</p>
                        </div>
                    </div>
                    
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div id="settings-tab" class="tab-content">
                        <h3>AI Assistant Settings</h3>
                        <p>Configure your AI provider settings to power the content assistant features.</p>
                        
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="api_provider">AI Provider:</label>
                                <select id="api_provider" name="api_provider">
                                    <option value="openai" <?php echo ($ai_settings['api_provider'] ?? '') === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                                    <option value="anthropic" <?php echo ($ai_settings['api_provider'] ?? '') === 'anthropic' ? 'selected' : ''; ?>>Anthropic</option>
                                    <option value="gemini" <?php echo ($ai_settings['api_provider'] ?? '') === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="api_key">API Key:</label>
                                <input type="password" id="api_key" name="api_key" value="<?php echo htmlspecialchars($ai_settings['api_key'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="model">Model:</label>
                                <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($ai_settings['model'] ?? 'gpt-3.5-turbo'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="max_tokens">Max Tokens:</label>
                                <input type="number" id="max_tokens" name="max_tokens" value="<?php echo htmlspecialchars($ai_settings['max_tokens'] ?? '500'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="temperature">Temperature (0.0 - 1.0):</label>
                                <input type="number" id="temperature" name="temperature" min="0" max="1" step="0.1" value="<?php echo htmlspecialchars($ai_settings['temperature'] ?? '0.7'); ?>">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_settings" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </div>
                        </form>
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
    <script>
        function showTab(tabId, button) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked button
            button.classList.add('active');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            });
        }
    </script>
</body>
</html>