<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure the user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Check if AI Assistant is enabled
$featureResult = $db->query("SELECT enabled FROM features WHERE feature_name = 'ai_assistant'");
$aiEnabled = false;
if ($featureResult && $featureResult->num_rows > 0) {
    $aiEnabled = (bool)$featureResult->fetch_assoc()['enabled'];
}

if (!$aiEnabled) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'AI Assistant is disabled']);
    exit();
}

// Get API settings
$result = $db->query("SELECT * FROM ai_settings LIMIT 1");
$ai_settings = $result->fetch_assoc();

// Check if API key is configured
if (empty($ai_settings['api_key'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'AI API key not configured. Please contact admin.']);
    exit();
}

// Handle different AI actions
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'analyze_subject':
        $subject = $_POST['subject'] ?? '';
        if (empty($subject)) {
            echo json_encode(['error' => 'Subject is required']);
            exit();
        }
        
        // In a real implementation, this would call an AI API
        // For demonstration, we'll return mock data
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
        
        echo json_encode(['suggestions' => $suggestions]);
        break;
        
    case 'improve_content':
        $content = $_POST['content'] ?? '';
        if (empty($content)) {
            echo json_encode(['error' => 'Content is required']);
            exit();
        }
        
        // Mock improved content and analysis
        $analysis = 'Your content is good but could be more engaging. Consider adding more personalization and clearer calls-to-action.';
        $improved_content = $content . '<p>Additionally, we recommend taking advantage of our <strong>limited-time offer</strong> today. <a href="#signup" class="btn">Click here to get started</a></p>';
        
        // Store suggestion in database
        $type = 'content_enhancement';
        $stmt = $db->prepare("INSERT INTO content_suggestions 
                            (type, original_content, suggested_content) 
                            VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $type, $content, $improved_content);
        $stmt->execute();
        
        echo json_encode([
            'analysis' => $analysis,
            'improved_content' => $improved_content
        ]);
        break;
        
    case 'analyze_spam':
        $content = $_POST['content'] ?? '';
        if (empty($content)) {
            echo json_encode(['error' => 'Content is required']);
            exit();
        }
        
        // Mock spam analysis
        $score = 20; // 0-100, lower is better
        $risk_level = 'Low Risk';
        $summary = 'Your content has a low spam score and should deliver well to most inboxes.';
        $tips = [
            'Avoid excessive use of all-caps words',
            'Consider removing the word "free" from your content',
            'Balance image-to-text ratio'
        ];
        
        // Store analysis in database
        $spam_score = $score / 100;
        $analysis_json = json_encode([
            'risk_level' => $risk_level,
            'summary' => $summary,
            'tips' => $tips
        ]);
        
        $stmt = $db->prepare("INSERT INTO content_analysis 
                            (spam_score, analysis_json) 
                            VALUES (?, ?)");
        $stmt->bind_param('ds', $spam_score, $analysis_json);
        $stmt->execute();
        
        echo json_encode([
            'score' => $score,
            'risk_level' => $risk_level,
            'summary' => $summary,
            'tips' => $tips
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}