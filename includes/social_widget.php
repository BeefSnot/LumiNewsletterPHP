<?php
/**
 * Social Widget Helper
 * Helper functions for social sharing widgets
 */

/**
 * Get social sharing buttons with real-time tracking
 */
function getSocialShareButtons($newsletter_id, $subject, $db, $options = []) {
    // Default options
    $defaults = [
        'facebook' => true,
        'twitter' => true,
        'linkedin' => true,
        'email' => true,
        'size' => 'normal',  // 'small', 'normal', 'large'
        'style' => 'default' // 'default', 'simple', 'minimal'
    ];
    
    $options = array_merge($defaults, $options);
    
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
    $encodedSubject = urlencode($subject);
    
    // Size class
    $sizeClass = $options['size'] === 'small' ? 'btn-sm' : ($options['size'] === 'large' ? 'btn-lg' : '');
    
    // Style class
    $styleClass = '';
    if ($options['style'] === 'simple') {
        $styleClass = 'social-btn-simple';
    } elseif ($options['style'] === 'minimal') {
        $styleClass = 'social-btn-minimal';
    }
    
    // Generate sharing buttons HTML
    $html = '<div class="social-sharing">';
    $html .= '<div class="social-buttons">';
    
    if ($options['facebook']) {
        $html .= '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl . '" target="_blank" class="social-btn facebook ' . $sizeClass . ' ' . $styleClass . '" data-platform="facebook" data-newsletter="' . $newsletter_id . '">';
        $html .= '<i class="fab fa-facebook-f"></i> ' . ($options['style'] === 'minimal' ? '' : 'Facebook') . '</a>';
    }
    
    if ($options['twitter']) {
        $html .= '<a href="https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . $encodedSubject . '" target="_blank" class="social-btn twitter ' . $sizeClass . ' ' . $styleClass . '" data-platform="twitter" data-newsletter="' . $newsletter_id . '">';
        $html .= '<i class="fab fa-twitter"></i> ' . ($options['style'] === 'minimal' ? '' : 'Twitter') . '</a>';
    }
    
    if ($options['linkedin']) {
        $html .= '<a href="https://www.linkedin.com/shareArticle?mini=true&url=' . $encodedUrl . '&title=' . $encodedSubject . '" target="_blank" class="social-btn linkedin ' . $sizeClass . ' ' . $styleClass . '" data-platform="linkedin" data-newsletter="' . $newsletter_id . '">';
        $html .= '<i class="fab fa-linkedin-in"></i> ' . ($options['style'] === 'minimal' ? '' : 'LinkedIn') . '</a>';
    }
    
    if ($options['email']) {
        $html .= '<a href="mailto:?subject=' . $encodedSubject . '&body=Check%20out%20this%20newsletter:%20' . $encodedUrl . '" class="social-btn email ' . $sizeClass . ' ' . $styleClass . '" data-platform="email" data-newsletter="' . $newsletter_id . '">';
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