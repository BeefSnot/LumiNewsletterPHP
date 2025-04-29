<?php
// This file is meant to be run via cron job to process automation workflows
require_once 'includes/db.php';
require_once 'includes/init.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$config = require 'includes/config.php';

// Get all active workflows
$workflowsResult = $db->query("SELECT * FROM automation_workflows WHERE status = 'active'");
if (!$workflowsResult) {
    die("Error fetching workflows: " . $db->error);
}

while ($workflow = $workflowsResult->fetch_assoc()) {
    processWorkflow($workflow, $db, $config);
}

function processWorkflow($workflow, $db, $config) {
    echo "Processing workflow: " . $workflow['name'] . " (ID: " . $workflow['id'] . ")\n";
    
    // Find triggered subscribers based on the trigger type
    $subscribers = getTriggeredSubscribers($workflow, $db);
    
    foreach ($subscribers as $subscriber) {
        processStepsForSubscriber($workflow['id'], $subscriber, $db, $config);
    }
}

function getTriggeredSubscribers($workflow, $db) {
    $subscribers = [];
    $trigger_data = json_decode($workflow['trigger_data'], true);
    
    switch ($workflow['trigger_type']) {
        case 'subscription':
            // Find new subscribers in the last day
            $group_id = $trigger_data['group_id'] ?? null;
            $query = "SELECT email FROM group_subscriptions WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
            if ($group_id) {
                $query .= " AND group_id = " . (int)$group_id;
            }
            $result = $db->query($query);
            while ($result && $row = $result->fetch_assoc()) {
                $subscribers[] = $row['email'];
            }
            break;
            
        case 'tag_added':
            // Find subscribers who got a specific tag in the last day
            $tag = $trigger_data['tag'] ?? '';
            if ($tag) {
                $query = "SELECT email FROM subscriber_tags WHERE tag = ? AND added_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
                $stmt = $db->prepare($query);
                $stmt->bind_param("s", $tag);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $subscribers[] = $row['email'];
                }
                $stmt->close();
            }
            break;
            
        case 'segment_join':
            // Find subscribers who joined a specific segment in the last day
            // This would require segment processing
            break;
            
        case 'inactivity':
            // Find subscribers who haven't opened or clicked in X days
            $days = $trigger_data['days'] ?? 30;
            $query = "SELECT DISTINCT gs.email 
                      FROM group_subscriptions gs
                      LEFT JOIN email_opens eo ON gs.email = eo.email AND eo.opened_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                      LEFT JOIN link_clicks lc ON gs.email = lc.email AND lc.clicked_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                      WHERE eo.id IS NULL AND lc.id IS NULL";
            $stmt = $db->prepare($query);
            $stmt->bind_param("ii", $days, $days);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $subscribers[] = $row['email'];
            }
            $stmt->close();
            break;
    }
    
    return $subscribers;
}

function processStepsForSubscriber($workflow_id, $subscriber_email, $db, $config) {
    echo "Processing steps for subscriber: $subscriber_email\n";
    
    // Get workflow steps
    $stmt = $db->prepare("SELECT * FROM automation_steps WHERE workflow_id = ? ORDER BY position ASC");
    $stmt->bind_param("i", $workflow_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $steps = [];
    while ($row = $result->fetch_assoc()) {
        $steps[] = $row;
    }
    $stmt->close();
    
    foreach ($steps as $step) {
        // Check if this step has already been processed for this subscriber
        $stmt = $db->prepare("SELECT * FROM automation_logs WHERE workflow_id = ? AND step_id = ? AND subscriber_email = ?");
        $stmt->bind_param("iis", $workflow_id, $step['id'], $subscriber_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $log = $result->fetch_assoc();
        $stmt->close();
        
        // If already processed, skip to next step
        if ($log) {
            continue;
        }
        
        // Process the step
        $step_data = json_decode($step['step_data'], true);
        
        switch ($step['step_type']) {
            case 'email':
                sendEmail($subscriber_email, $step_data, $config);
                logStepCompletion($workflow_id, $step['id'], $subscriber_email, 'completed', $db);
                break;
                
            case 'delay':
                // For delay, we'll create the log but set it to pending
                // It will be completed when the delay period is over
                $delay_value = $step_data['delay_value'] ?? 1;
                $delay_type = $step_data['delay_type'] ?? 'days';
                
                logStepCompletion($workflow_id, $step['id'], $subscriber_email, 'pending', $db);
                // In a real system, you'd need to track the delay and process it later
                break;
                
            case 'condition':
                $condition_met = evaluateCondition($subscriber_email, $step_data, $db);
                $status = $condition_met ? 'completed' : 'skipped';
                logStepCompletion($workflow_id, $step['id'], $subscriber_email, $status, $db);
                
                // If condition is not met, break out of the workflow for this subscriber
                if (!$condition_met) {
                    break 2; // Break out of both foreach loops
                }
                break;
                
            case 'tag':
                processTagAction($subscriber_email, $step_data, $db);
                logStepCompletion($workflow_id, $step['id'], $subscriber_email, 'completed', $db);
                break;
                
            case 'split':
                // For simplicity, just choose a random path
                $split_percentage = $step_data['split_percentage'] ?? 50;
                $result = (rand(1, 100) <= $split_percentage) ? 'A' : 'B';
                // Log the split result
                logStepCompletion($workflow_id, $step['id'], $subscriber_email, 'completed', $db);
                break;
        }
    }
}

function sendEmail($email, $step_data, $config) {
    $subject = $step_data['subject'] ?? 'No Subject';
    $body = $step_data['body'] ?? 'No Content';
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_user'];
        $mail->Password = $config['smtp_pass'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port = $config['smtp_port'];
        
        $mail->setFrom($config['smtp_user']);
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        echo "Email sent to $email\n";
    } catch (Exception $e) {
        echo "Failed to send email to $email: " . $mail->ErrorInfo . "\n";
    }
}

function evaluateCondition($email, $step_data, $db) {
    $condition_type = $step_data['condition_type'] ?? '';
    $condition_value = $step_data['condition_value'] ?? '';
    
    switch ($condition_type) {
        case 'opened_email':
            // Check if subscriber has opened a specific newsletter
            $stmt = $db->prepare("SELECT COUNT(*) AS count FROM email_opens WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result['count'] > 0;
            
        case 'clicked_link':
            // Check if subscriber has clicked a link
            $stmt = $db->prepare("SELECT COUNT(*) AS count FROM link_clicks WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result['count'] > 0;
            
        case 'has_tag':
            // Check if subscriber has a specific tag
            $stmt = $db->prepare("SELECT COUNT(*) AS count FROM subscriber_tags WHERE email = ? AND tag = ?");
            $stmt->bind_param("ss", $email, $condition_value);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result['count'] > 0;
            
        case 'in_segment':
            // This would require segment processing
            return true;
            
        case 'custom':
            // Custom conditions would be handled here
            return true;
    }
    
    return false;
}

function processTagAction($email, $step_data, $db) {
    $tag_action = $step_data['tag_action'] ?? 'add';
    $tag_value = $step_data['tag_value'] ?? '';
    
    if (empty($tag_value)) {
        return;
    }
    
    if ($tag_action === 'add') {
        // Add tag if it doesn't exist
        $stmt = $db->prepare("INSERT IGNORE INTO subscriber_tags (email, tag) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $tag_value);
        $stmt->execute();
        $stmt->close();
    } else {
        // Remove tag
        $stmt = $db->prepare("DELETE FROM subscriber_tags WHERE email = ? AND tag = ?");
        $stmt->bind_param("ss", $email, $tag_value);
        $stmt->execute();
        $stmt->close();
    }
}

function logStepCompletion($workflow_id, $step_id, $email, $status, $db) {
    $stmt = $db->prepare("INSERT INTO automation_logs (workflow_id, subscriber_email, step_id, status) 
                         VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $workflow_id, $email, $step_id, $status);
    $stmt->execute();
    $stmt->close();
    
    echo "Logged step $step_id for $email with status: $status\n";
}