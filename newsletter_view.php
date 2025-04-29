<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/social_sharing.php';

// Get newsletter ID from URL
$newsletter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = isset($_GET['utm_source']) ? $_GET['utm_source'] : '';

if ($newsletter_id <= 0) {
    // Invalid or missing ID
    header('Location: index.php');
    exit();
}

// Check if this is a known social share click
if ($source === 'social') {
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Record the social media click based on the referrer
    if (strpos($referrer, 'facebook.com') !== false) {
        $platform = 'facebook';
    } elseif (strpos($referrer, 'twitter.com') !== false || strpos($referrer, 'x.com') !== false) {
        $platform = 'twitter';
    } elseif (strpos($referrer, 'linkedin.com') !== false) {
        $platform = 'linkedin';
    } else {
        $platform = 'other';
    }
    
    // Find the share record
    $stmt = $db->prepare("SELECT id FROM social_shares WHERE newsletter_id = ? AND platform = ?");
    $stmt->bind_param("is", $newsletter_id, $platform);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $share_id = $result->fetch_assoc()['id'];
        // Record the click
        recordSocialClick($share_id, $ip, $referrer);
    }
}

// Get newsletter content
$stmt = $db->prepare("SELECT n.*, u.username as sender 
                    FROM newsletters n 
                    JOIN users u ON n.sender_id = u.id 
                    WHERE n.id = ?");
$stmt->bind_param("i", $newsletter_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Newsletter not found
    header('Location: index.php');
    exit();
}

$newsletter = $result->fetch_assoc();

// Get site title
$siteTitle = 'LumiNewsletter';
$result = $db->query("SELECT value FROM settings WHERE name = 'title'");
if ($result && $result->num_rows > 0) {
    $siteTitle = $result->fetch_assoc()['value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($newsletter['subject']); ?> | <?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: var(--gray-light);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .newsletter-container {
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            box-sizing: border-box;
        }
        
        .newsletter-header {
            background-color: white;
            border-radius: var(--radius) var(--radius) 0 0;
            padding: 2rem;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .newsletter-body {
            background-color: white;
            padding: 2rem;
        }
        
        .newsletter-footer {
            background-color: white;
            border-radius: 0 0 var(--radius) var(--radius);
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--gray-light);
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .newsletter-meta {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .social-sharing {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        
        .social-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .social-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: var(--radius);
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: opacity 0.2s;
        }
        
        .social-btn:hover {
            opacity: 0.9;
        }
        
        .social-btn i {
            margin-right: 8px;
        }
        
        .facebook { background-color: #3b5998; }
        .twitter { background-color: #1da1f2; }
        .linkedin { background-color: #0077b5; }
        .email { background-color: #777; }
        
        @media (max-width: 600px) {
            .newsletter-container {
                padding: 1rem;
            }
            
            .newsletter-header, 
            .newsletter-body, 
            .newsletter-footer {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="newsletter-container">
        <div class="newsletter-header">
            <h1><?php echo htmlspecialchars($newsletter['subject']); ?></h1>
            <div class="newsletter-meta">
                Sent by <?php echo htmlspecialchars($newsletter['sender']); ?> on 
                <?php echo date('F j, Y', strtotime($newsletter['sent_at'] ?? $newsletter['created_at'])); ?>
            </div>
        </div>
        
        <div class="newsletter-body">
            <?php echo $newsletter['body']; ?>
            
            <?php echo generateSocialButtons($newsletter_id, $newsletter['subject'], $db); ?>
        </div>
        
        <div class="newsletter-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteTitle); ?></p>
        </div>
    </div>
    
    <script>
        // Track social sharing events
        document.addEventListener('DOMContentLoaded', function() {
            const socialButtons = document.querySelectorAll('.social-btn');
            socialButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const platform = this.getAttribute('data-platform');
                    const newsletterId = this.getAttribute('data-newsletter');
                    
                    // Record share via AJAX
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'track_social.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send(`type=share&platform=${platform}&newsletter_id=${newsletterId}`);
                });
            });
        });
    </script>
</body>
</html>