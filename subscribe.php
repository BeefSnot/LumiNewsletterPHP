<?php
require_once 'includes/init.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add these use statements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;  
use PHPMailer\PHPMailer\Exception;

require_once 'includes/db.php';

$config = require 'includes/config.php';

// Get privacy settings
$requireExplicitConsent = false;
$consentText = '';

$result = $db->query("SELECT setting_key, setting_value FROM privacy_settings WHERE setting_key IN ('require_explicit_consent', 'consent_prompt_text')");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] === 'require_explicit_consent') {
            $requireExplicitConsent = ($row['setting_value'] === '1');
        } elseif ($row['setting_key'] === 'consent_prompt_text') {
            $consentText = $row['setting_value'];
        }
    }
}

// Process form submission
$error = '';
$success = false;
$group_id = isset($_GET['group']) ? (int)$_GET['group'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $name = $_POST['name'] ?? '';
    $trackingConsent = isset($_POST['tracking_consent']) ? 1 : 0;
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if already subscribed to this group
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM group_subscriptions WHERE email = ? AND group_id = ?");
        $stmt->bind_param("si", $email, $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $isSubscribed = ($result->fetch_assoc()['count'] > 0);
        $stmt->close();
        
        if ($isSubscribed) {
            $error = 'This email is already subscribed to this newsletter.';
        } else {
            // Add subscription
            $stmt = $db->prepare("INSERT INTO group_subscriptions (email, name, group_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $email, $name, $group_id);
            $success = $stmt->execute();
            $stmt->close();
            
            // Record consent
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $consentRecord = json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'subscription_form',
                'form_data' => [
                    'email' => $email,
                    'group_id' => $group_id,
                    'tracking_consent_given' => $trackingConsent
                ]
            ]);
            
            $stmt = $db->prepare("INSERT INTO subscriber_consent (email, tracking_consent, ip_address, consent_record) 
                                 VALUES (?, ?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE 
                                 tracking_consent = ?, ip_address = ?, consent_record = ?, consent_date = CURRENT_TIMESTAMP");
            $stmt->bind_param("sssss", $email, $trackingConsent, $ip_address, $consentRecord, $trackingConsent, $ip_address, $consentRecord);
            $stmt->execute();
            $stmt->close();
            
            if ($success) {
                // Send confirmation email
                // This part would include your email sending code
                
                // Redirect to thank you page
                header("Location: thanks_for_subscribing.html");
                exit();
            } else {
                $error = 'There was a problem with your subscription. Please try again.';
            }
        }
    }
}

// Get group info if group_id is provided
$groupName = 'Our Newsletter';
if ($group_id > 0) {
    $stmt = $db->prepare("SELECT name FROM groups WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $groupName = $row['name'];
    }
    $stmt->close();
}

// Get site title
$siteTitle = 'LumiNewsletter';
$result = $db->query("SELECT value FROM settings WHERE name = 'title'");
if ($result && $result->num_rows > 0) {
    $siteTitle = $result->fetch_assoc()['value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'])) {
        $email = $_POST['email'];
        $action = $_POST['action'];
        $groups = $_POST['groups'];

        if ($action === 'subscribe') {
            foreach ($groups as $group_id) {
                $stmt = $db->prepare("INSERT INTO group_subscriptions (email, group_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE group_id = ?");
                if ($stmt === false) {
                    die('Prepare failed: ' . htmlspecialchars($db->error));
                }
                $stmt->bind_param("sii", $email, $group_id, $group_id);
                if ($stmt->execute() === false) {
                    die('Execute failed: ' . htmlspecialchars($stmt->error));
                }
                $stmt->close();
            }
            $message = 'Subscribed successfully';

            // Fetch the "Thanks For Subscribing" theme from the database
            $themeStmt = $db->prepare("SELECT content FROM themes WHERE name = 'Thanks For Subscribing'");
            if ($themeStmt === false) {
                die('Prepare failed: ' . htmlspecialchars($db->error));
            }
            $themeStmt->execute();
            $themeStmt->bind_result($themeContent);
            $themeStmt->fetch();
            $themeStmt->close();

            // Send the "Thanks For Subscribing" email
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_user'];
                $mail->Password = $config['smtp_pass'];
                $mail->SMTPSecure = $config['smtp_secure'];
                $mail->Port = $config['smtp_port'];

                // Recipients
                $mail->setFrom($config['smtp_user'], 'Newsletter');
                $mail->addAddress($email);
                $mail->Subject = 'Thanks for Subscribing!';
                $mail->Body = $themeContent;
                $mail->send();
            } catch (Exception $e) {
                die('Mailer Error: ' . $mail->ErrorInfo);
            }
        } elseif ($action === 'unsubscribe') {
            foreach ($groups as $group_id) {
                $stmt = $db->prepare("DELETE FROM group_subscriptions WHERE email = ? AND group_id = ?");
                if ($stmt === false) {
                    die('Prepare failed: ' . htmlspecialchars($db->error));
                }
                $stmt->bind_param("si", $email, $group_id);
                if ($stmt->execute() === false) {
                    die('Execute failed: ' . htmlspecialchars($stmt->error));
                }
                $stmt->close();
            }
            $message = 'Unsubscribed successfully';
        }
    }
}

// Fetch available groups
$groupsResult = $db->query("SELECT id, name FROM groups");
$groups = [];
while ($row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe | <?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: var(--gray-light);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .subscribe-container {
            max-width: 600px;
            width: 100%;
            padding: 2rem;
        }
        .card {
            margin-bottom: 0;
        }
        .card-header {
            text-align: center;
            padding: 2rem 1.5rem;
        }
        .card-header h1 {
            font-size: 2rem;
            margin: 0;
        }
        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
        }
        .logo i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-right: 0.5rem;
        }
        .message {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: var(--radius);
            background-color: rgba(52, 168, 83, 0.1);
            color: var(--accent);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="subscribe-container">
        <div class="card">
            <div class="card-header">
                <div class="logo">
                    <i class="fas fa-paper-plane"></i>
                    <h1>LumiNewsletter</h1>
                </div>
                <h2>Subscribe/Unsubscribe</h2>
            </div>
            <div class="card-body">
                <?php if (isset($message)): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email:</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="groups"><i class="fas fa-users"></i> Groups:</label>
                        <select class="form-control" id="groups" name="groups[]" multiple required>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo $group['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Hold Ctrl (or Cmd on Mac) to select multiple groups</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="action"><i class="fas fa-tasks"></i> Action:</label>
                        <select class="form-control" id="action" name="action" required>
                            <option value="subscribe">Subscribe</option>
                            <option value="unsubscribe">Unsubscribe</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter</p>
    </footer>
</body>
</html>