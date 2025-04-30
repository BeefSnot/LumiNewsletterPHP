<?php
require_once 'includes/init.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add these use statements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;  
use PHPMailer\PHPMailer\Exception;

require_once 'includes/db.php';
require_once 'includes/functions.php';  // Include functions file for any helper functions

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
$message = '';
$group_id = isset($_GET['group']) ? (int)$_GET['group'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $name = $_POST['name'] ?? '';
        $trackingConsent = isset($_POST['tracking_consent']) ? 1 : 0;
        $action = $_POST['action'] ?? 'subscribe';
        $groups = isset($_POST['groups']) ? $_POST['groups'] : [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else if ($requireExplicitConsent && !$trackingConsent && $action === 'subscribe') {
            // Require consent if explicit consent is enabled for subscriptions
            $error = 'You must consent to our privacy policy to subscribe.';
        } else if (empty($groups)) {
            $error = 'Please select at least one group.';
        } else {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $consentRecord = json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'subscription_form',
                'form_data' => [
                    'email' => $email,
                    'groups' => $groups,
                    'tracking_consent_given' => $trackingConsent
                ]
            ]);

            if ($action === 'subscribe') {
                $success = true;
                foreach ($groups as $group_id) {
                    $stmt = $db->prepare("INSERT INTO group_subscriptions (email, group_id) VALUES (?, ?) 
                                        ON DUPLICATE KEY UPDATE email = ?");
                    if ($stmt === false) {
                        $success = false;
                        $error = 'Database error: ' . htmlspecialchars($db->error);
                        break;
                    }
                    
                    $stmt->bind_param("sis", $email, $group_id, $email);
                    if ($stmt->execute() === false) {
                        $success = false;
                        $error = 'Database error: ' . htmlspecialchars($stmt->error);
                        break;
                    }
                    $stmt->close();
                }

                if ($success) {
                    // Record consent
                    $stmt = $db->prepare("INSERT INTO subscriber_consent (email, tracking_consent, ip_address, consent_record) 
                                        VALUES (?, ?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE 
                                        tracking_consent = ?, ip_address = ?, consent_record = ?, consent_date = CURRENT_TIMESTAMP");
                    $stmt->bind_param("sisssss", $email, $trackingConsent, $ip_address, $consentRecord, $trackingConsent, $ip_address, $consentRecord);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Send confirmation email if possible
                    try {
                        // Fetch the "Thanks For Subscribing" theme from the database
                        $themeStmt = $db->prepare("SELECT content FROM themes WHERE name LIKE 'Thanks%' LIMIT 1");
                        $themeStmt->execute();
                        $themeStmt->bind_result($themeContent);
                        $hasTheme = $themeStmt->fetch();
                        $themeStmt->close();
                        
                        if ($hasTheme && !empty($themeContent)) {
                            // Send the "Thanks For Subscribing" email
                            $mail = new PHPMailer(true);
                            
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
                            $mail->isHTML(true);
                            $mail->Subject = 'Thanks for Subscribing!';
                            $mail->Body = $themeContent;
                            $mail->send();
                        }
                    } catch (Exception $e) {
                        // Log error but don't prevent subscription success
                        error_log('Mailer Error: ' . $e->getMessage());
                    }

                    $message = 'You have been successfully subscribed!';
                }
            } elseif ($action === 'unsubscribe') {
                $success = true;
                foreach ($groups as $group_id) {
                    $stmt = $db->prepare("DELETE FROM group_subscriptions WHERE email = ? AND group_id = ?");
                    if ($stmt === false) {
                        $success = false;
                        $error = 'Database error: ' . htmlspecialchars($db->error);
                        break;
                    }
                    
                    $stmt->bind_param("si", $email, $group_id);
                    if ($stmt->execute() === false) {
                        $success = false;
                        $error = 'Database error: ' . htmlspecialchars($stmt->error);
                        break;
                    }
                    $stmt->close();
                }
                
                if ($success) {
                    $message = 'You have been successfully unsubscribed from the selected groups.';
                }
            }
        }
    }
}

// Get site title
$siteTitle = 'LumiNewsletter';
$result = $db->query("SELECT value FROM settings WHERE name = 'title'");
if ($result && $result->num_rows > 0) {
    $siteTitle = $result->fetch_assoc()['value'];
}

// If no explicit consent text was provided, use a default
if (empty($consentText)) {
    $consentText = 'I consent to receiving newsletters and agree that my email engagement may be tracked for analytics purposes.';
}

// Fetch available groups
$groupsResult = $db->query("SELECT id, name, description FROM groups ORDER BY name ASC");
$groups = [];
while ($row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe/Unsubscribe | <?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .group-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .group-option {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: var(--radius);
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .group-option:hover {
            background: #f0f0f0;
            border-color: #ccc;
        }
        
        .group-option.selected {
            background: rgba(66, 133, 244, 0.1);
            border-color: var(--primary);
        }
        
        .group-option h3 {
            margin-top: 0;
            font-size: 1.1rem;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        .group-option h3 input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .group-option p {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0;
        }
        
        .consent-checkbox {
            margin: 20px 0;
            padding: 15px;
            background: #f5f7fa;
            border-radius: var(--radius);
            border-left: 3px solid var(--primary);
        }
        
        .consent-checkbox label {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
        }
        
        .consent-checkbox input[type="checkbox"] {
            margin-right: 10px;
            margin-top: 3px;
        }
        
        .page-intro {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .subscription-form {
            max-width: 800px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="simple-header">
            <div class="logo">
                <i class="fas fa-paper-plane"></i>
                <h2><?php echo htmlspecialchars($siteTitle); ?></h2>
            </div>
        </header>
    
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Newsletter Management</h1>
                </div>
            </header>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-paper-plane"></i> Subscribe or Unsubscribe</h2>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="notification error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success && !empty($message)): ?>
                        <div class="notification success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="page-intro">
                        <p>Use the form below to manage your newsletter subscriptions. Subscribe to receive our latest updates or unsubscribe if you no longer wish to receive our communications.</p>
                    </div>
                    
                    <form method="post" class="subscription-form">
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Your Name (Optional):</label>
                            <input type="text" id="name" name="name">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-users"></i> Select Newsletter Groups:</label>
                            <div class="group-grid">
                                <?php foreach ($groups as $group): ?>
                                <div class="group-option" onclick="toggleGroupSelection(this, <?php echo $group['id']; ?>)">
                                    <h3>
                                        <input type="checkbox" name="groups[]" value="<?php echo $group['id']; ?>" class="group-checkbox">
                                        <?php echo htmlspecialchars($group['name']); ?>
                                    </h3>
                                    <?php if (!empty($group['description'])): ?>
                                    <p><?php echo htmlspecialchars($group['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-tasks"></i> What would you like to do?</label>
                            <div class="radio-buttons">
                                <label>
                                    <input type="radio" name="action" value="subscribe" checked> Subscribe to these groups
                                </label>
                                <label>
                                    <input type="radio" name="action" value="unsubscribe"> Unsubscribe from these groups
                                </label>
                            </div>
                        </div>
                        
                        <!-- GDPR Consent Checkbox - Always show but hide/show with JS based on action -->
                        <div class="consent-checkbox" id="consent-section">
                            <label>
                                <input type="checkbox" name="tracking_consent" id="tracking_consent">
                                <span><?php echo htmlspecialchars($consentText); ?></span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteTitle); ?> - Professional Newsletter Management</p>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Keep only this part that controls consent visibility
            const actionRadios = document.querySelectorAll('input[name="action"]');
            const consentCheckbox = document.getElementById('consent-section');
            
            actionRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (consentCheckbox) {
                        if (this.value === 'subscribe') {
                            consentCheckbox.style.display = 'block';
                        } else {
                            consentCheckbox.style.display = 'none';
                        }
                    }
                });
            });
        });
        
        // Function to handle group selection via the entire card
        function toggleGroupSelection(element, groupId) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            // Toggle selected class
            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
        }
    </script>
</body>
</html>