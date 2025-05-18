<?php
/**
 * Social Sharing Functions
 * Handles social sharing features for newsletters
 */

/**
 * Generate social sharing widget for newsletters
 * 
 * @param int $newsletter_id Newsletter ID
 * @param string $subject Newsletter subject for share text
 * @param array $options Optional configuration options
 * @return string HTML for social sharing widget
 */
function generateSocialWidget($newsletter_id, $subject, $options = []) {
    global $db;
    
    // Default options
    $defaults = [
        'facebook' => true,
        'twitter' => true,
        'linkedin' => true,
        'email' => true,
        'style' => 'full', // 'full', 'minimal', 'outlined'
        'size' => 'medium', // 'small', 'medium', 'large'
        'show_count' => false,
        'display' => 'horizontal' // 'horizontal', 'vertical'
    ];
    
    $options = array_merge($defaults, $options);
    
    // Get base URL for shares
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $siteUrl = $protocol . "://" . $_SERVER['HTTP_HOST'];
    $shareUrl = $siteUrl . "/newsletter_view.php?id=" . $newsletter_id;
    
    // URL encode data for sharing
    $encodedUrl = urlencode($shareUrl);
    $encodedSubject = urlencode($subject);
    
    // Generate HTML classes based on options
    $sizeClass = 'size-' . $options['size'];
    $styleClass = 'style-' . $options['style'];
    $displayClass = 'display-' . $options['display'];
    
    // Start building HTML
    $html = '<div class="social-sharing-widget ' . $displayClass . '">';
    $html .= '<div class="share-buttons">';
    
    // Add each enabled social button
    if ($options['facebook']) {
        $html .= '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl . '" target="_blank" class="social-btn facebook ' . $sizeClass . ' ' . $styleClass . '" data-platform="facebook" data-newsletter="' . $newsletter_id . '">';
        $html .= '<i class="fab fa-facebook-f"></i> ' . ($options['style'] === 'minimal' ? '' : 'Facebook') . '</a>';
    }
    
    if ($options['twitter']) {
        $html .= '<a href="https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . $encodedSubject . '" target="_blank" class="social-btn twitter ' . $sizeClass . ' ' . $styleClass . '" data-platform="twitter" data-newsletter="' . $newsletter_id . '">';
        $html .= '<i class="fab fa-twitter"></i> ' . ($options['style'] === 'minimal' ? '' : 'Twitter') . '</a>';
    }
    
    if ($options['linkedin']) {
        $html .= '<a href="https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl . '" target="_blank" class="social-btn linkedin ' . $sizeClass . ' ' . $styleClass . '" data-platform="linkedin" data-newsletter="' . $newsletter_id . '">';
        $html .= '<i class="fab fa-linkedin-in"></i> ' . ($options['style'] === 'minimal' ? '' : 'LinkedIn') . '</a>';
    }
    
    if ($options['email']) {
        $html .= '<a href="mailto:?subject=' . $encodedSubject . '&body=' . urlencode("I thought you might be interested in this newsletter: " . $shareUrl) . '" class="social-btn email ' . $sizeClass . ' ' . $styleClass . '" data-platform="email" data-newsletter="' . $newsletter_id . '">';
        $html .= '<i class="fas fa-envelope"></i> ' . ($options['style'] === 'minimal' ? '' : 'Email') . '</a>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    // Add tracking script
    $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const socialButtons = document.querySelectorAll(".social-btn");
            socialButtons.forEach(button => {
                button.addEventListener("click", function(e) {
                    const platform = this.getAttribute("data-platform");
                    const newsletterId = this.getAttribute("data-newsletter");
                    
                    // Record share via AJAX
                    const xhr = new XMLHttpRequest();
                    xhr.open("POST", "' . $siteUrl . '/track_social.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.send(`type=share&platform=${platform}&newsletter_id=${newsletterId}`);
                });
            });
        });
    </script>';
    
    return $html;
}

/**
 * Get social sharing stats for a newsletter
 */
function getSocialStats($newsletter_id, $db) {
    $stmt = $db->prepare("
        SELECT 
            platform, 
            share_count,
            click_count 
        FROM social_shares
        WHERE newsletter_id = ?
    ");
    $stmt->bind_param('i', $newsletter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = [
        'platforms' => [],
        'total_shares' => 0,
        'total_clicks' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $platform = $row['platform'];
        $shareCount = (int)$row['share_count'];
        $clickCount = (int)$row['click_count'];
        
        $stats['platforms'][$platform] = [
            'shares' => $shareCount,
            'clicks' => $clickCount
        ];
        
        $stats['total_shares'] += $shareCount;
        $stats['total_clicks'] += $clickCount;
    }
    
    return $stats;
}

/**
 * Record a social share
 */
function recordSocialShare($newsletter_id, $platform) {
    global $db;
    
    // Check if share record already exists
    $stmt = $db->prepare("SELECT id FROM social_shares WHERE newsletter_id = ? AND platform = ?");
    $stmt->bind_param("is", $newsletter_id, $platform);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing share record
        $share_id = $result->fetch_assoc()['id'];
        $db->query("UPDATE social_shares SET share_count = share_count + 1 WHERE id = $share_id");
        return $share_id;
    } else {
        // Create new share record
        $stmt = $db->prepare("INSERT INTO social_shares (newsletter_id, platform, share_count) VALUES (?, ?, 1)");
        $stmt->bind_param("is", $newsletter_id, $platform);
        $stmt->execute();
        return $db->insert_id;
    }
}

/**
 * Record a social click
 */
function recordSocialClick($share_id, $ip_address = null, $referrer = null) {
    global $db;
    
    $db->query("UPDATE social_shares SET click_count = click_count + 1 WHERE id = $share_id");
    
    // Record the detailed click data
    $stmt = $db->prepare("INSERT INTO social_clicks (share_id, ip_address, referrer) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $share_id, $ip_address, $referrer);
    $stmt->execute();
}