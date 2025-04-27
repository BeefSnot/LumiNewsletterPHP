<?php
require_once 'includes/init.php';
require_once 'includes/db.php';

// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get available groups
$groupsResult = $db->query("SELECT id, name FROM groups ORDER BY name ASC");
$groups = [];
while ($row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $groupId = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address';
        $messageType = 'error';
    } elseif (!$groupId) {
        $message = 'Please select a group';
        $messageType = 'error';
    } else {
        // Insert into database
        $stmt = $db->prepare("INSERT INTO group_subscriptions (email, group_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE group_id = ?");
        if ($stmt) {
            $stmt->bind_param("sii", $email, $groupId, $groupId);
            if ($stmt->execute()) {
                $message = 'Subscribed successfully! Please check your email to confirm.';
                $messageType = 'success';
                
                // Send welcome email (similar to your subscribe.php logic)
                // This would be where you'd add the code to send the welcome email
                // I'm omitting it here to keep the response brief, but it would be similar
                // to the PHPMailer setup in subscribe.php
            } else {
                $message = 'Subscription failed: ' . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Database error: ' . $db->error;
            $messageType = 'error';
        }
    }
    
    // If it's an AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['message' => $message, 'type' => $messageType]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe Widget</title>
    <style>
        /* Mini version of your styles for the widget */
        :root {
            --primary: #4285f4;
            --primary-dark: #3367d6;
            --primary-light: #a8c7fa;
            --accent: #34a853;
            --error: #ea4335;
            --gray: #5f6368;
            --gray-light: #f1f3f4;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }
        
        .lumi-widget {
            font-family: 'Arial', sans-serif;
            box-sizing: border-box;
            max-width: 100%;
            border-radius: var(--radius);
            overflow: hidden;
            background: white;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .lumi-widget * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        .lumi-header {
            background: var(--primary);
            color: white;
            padding: 12px 15px;
            display: flex;
            align-items: center;
        }
        
        .lumi-header i {
            font-size: 18px;
            margin-right: 8px;
        }
        
        .lumi-header h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }
        
        .lumi-body {
            padding: 15px;
        }
        
        .lumi-form-group {
            margin-bottom: 12px;
        }
        
        .lumi-form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: var(--gray);
        }
        
        .lumi-form-control {
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        
        .lumi-btn {
            width: 100%;
            padding: 8px 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .lumi-btn:hover {
            background: var(--primary-dark);
        }
        
        .lumi-message {
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            text-align: center;
        }
        
        .lumi-message.success {
            background-color: rgba(52, 168, 83, 0.1);
            color: var(--accent);
        }
        
        .lumi-message.error {
            background-color: rgba(234, 67, 53, 0.1);
            color: var(--error);
        }
        
        .lumi-footer {
            padding: 10px 15px;
            background: var(--gray-light);
            font-size: 12px;
            text-align: center;
            color: var(--gray);
        }
        
        .lumi-footer a {
            color: var(--primary);
            text-decoration: none;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="lumi-widget">
        <div class="lumi-header">
            <i class="fas fa-paper-plane"></i>
            <h3>Subscribe to Our Newsletter</h3>
        </div>
        <div class="lumi-body">
            <?php if ($message): ?>
                <div class="lumi-message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" id="lumi-subscribe-form">
                <div class="lumi-form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email:</label>
                    <input type="email" class="lumi-form-control" id="email" name="email" required>
                </div>
                
                <div class="lumi-form-group">
                    <label for="group_id"><i class="fas fa-users"></i> Newsletter:</label>
                    <select class="lumi-form-control" id="group_id" name="group_id" required>
                        <option value="">Select a Newsletter</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="lumi-btn">
                    <i class="fas fa-check"></i> Subscribe
                </button>
            </form>
        </div>
        <div class="lumi-footer">
            Powered by <a href="https://yoursite.com/newsletter" target="_blank">LumiNewsletter</a>
        </div>
    </div>
</body>
</html>