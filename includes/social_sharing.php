<?php
/**
 * Social Media Sharing Tools
 * Functions for enabling newsletter social sharing
 */

/**
 * Generate social sharing buttons for a newsletter
 */
function generateSocialButtons($newsletter_id, $newsletter_subject, $db) {
    // Get site URL from settings for creating full URLs
    $siteUrl = '';
    $result = $db->query("SELECT value FROM settings WHERE name = 'site_url'");
    if ($result && $result->num_rows > 0) {
        $siteUrl = $result->fetch_assoc()['value'];
    } else {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        $siteUrl = $protocol . $host . rtrim($path, '/');
    }

    // Create share URL
    $shareUrl = "$siteUrl/newsletter_view.php?id=$newsletter_id&utm_source=social";
    $encodedUrl = urlencode($shareUrl);
    $encodedSubject = urlencode($newsletter_subject);
    
    // Generate sharing buttons HTML
    $html = '<div class="social-sharing">';
    $html .= '<h4>Share this newsletter:</h4>';
    $html .= '<div class="social-buttons">';
    
    // Facebook
    $html .= '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl . '" target="_blank" class="social-btn facebook" data-platform="facebook" data-newsletter="' . $newsletter_id . '">';
    $html .= '<i class="fab fa-facebook-f"></i> Facebook</a>';
    
    // Twitter/X
    $html .= '<a href="https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . $encodedSubject . '" target="_blank" class="social-btn twitter" data-platform="twitter" data-newsletter="' . $newsletter_id . '">';
    $html .= '<i class="fab fa-twitter"></i> Twitter</a>';
    
    // LinkedIn
    $html .= '<a href="https://www.linkedin.com/shareArticle?mini=true&url=' . $encodedUrl . '&title=' . $encodedSubject . '" target="_blank" class="social-btn linkedin" data-platform="linkedin" data-newsletter="' . $newsletter_id . '">';
    $html .= '<i class="fab fa-linkedin-in"></i> LinkedIn</a>';
    
    // Email
    $html .= '<a href="mailto:?subject=' . $encodedSubject . '&body=Check%20out%20this%20newsletter:%20' . $encodedUrl . '" class="social-btn email" data-platform="email" data-newsletter="' . $newsletter_id . '">';
    $html .= '<i class="fas fa-envelope"></i> Email</a>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Record a social share event
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
        $row = $result->fetch_assoc();
        $share_id = $row['id'];
        $db->query("UPDATE social_shares SET share_count = share_count + 1 WHERE id = $share_id");
    } else {
        // Create new share record
        $stmt = $db->prepare("INSERT INTO social_shares (newsletter_id, platform, share_count) VALUES (?, ?, 1)");
        $stmt->bind_param("is", $newsletter_id, $platform);
        $stmt->execute();
    }
}

/**
 * Record a click from a social share
 */
function recordSocialClick($share_id, $ip_address = null, $referrer = null) {
    global $db;
    
    // Update click count on the share record
    $db->query("UPDATE social_shares SET click_count = click_count + 1 WHERE id = $share_id");
    
    // Record the detailed click data
    $stmt = $db->prepare("INSERT INTO social_clicks (share_id, ip_address, referrer) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $share_id, $ip_address, $referrer);
    $stmt->execute();
}