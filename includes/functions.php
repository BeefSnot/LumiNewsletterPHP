<?php
// Make sure there is NO whitespace or line breaks before the opening PHP tag

/* Add these at the top to properly locate errors */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Process personalization tags in content for a specific subscriber
 *
 * @param string $content The email content
 * @param array $subscriber Subscriber data
 * @param array $db Database connection
 * @return string Processed content
 */
function processPersonalization($content, $subscriber, $db) {
    // Replace basic tags
    $replacements = [
        '{{email}}' => $subscriber['email'],
        '{{first_name}}' => $subscriber['first_name'] ?? 'Subscriber',
        '{{last_name}}' => $subscriber['last_name'] ?? '',
        '{{subscription_date}}' => date('F j, Y', strtotime($subscriber['created_at'] ?? 'now')),
        '{{current_date}}' => date('F j, Y')
    ];
    
    // Full name (combine first and last if available)
    $fullName = trim(($subscriber['first_name'] ?? '') . ' ' . ($subscriber['last_name'] ?? ''));
    $fullName = empty($fullName) ? 'Subscriber' : $fullName;
    $replacements['{{full_name}}'] = $fullName;
    
    // Unsubscribe link
    $token = md5($subscriber['email'] . '|' . ($subscriber['token'] ?? 'token'));
    $siteUrl = getSiteUrl($db);
    $unsubscribeLink = $siteUrl . '/unsubscribe.php?email=' . urlencode($subscriber['email']) . '&token=' . $token;
    $replacements['{{unsubscribe_link}}'] = $unsubscribeLink;
    
    // Perform replacements
    foreach ($replacements as $tag => $value) {
        $content = str_replace($tag, $value, $content);
    }
    
    // Process content blocks first
    $pattern = '/<!-- CONTENT_BLOCK_START:(\d+) -->(.+?)<!-- CONTENT_BLOCK_END:\1 -->/s';
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $blockId = $match[1];
            $blockContent = $match[2];
            
            // Get the block from database to check its type
            $stmt = $db->prepare("SELECT type, conditions FROM content_blocks WHERE id = ?");
            $stmt->bind_param("i", $blockId);
            $stmt->execute();
            $block = $stmt->get_result()->fetch_assoc();
            
            // Skip if block not found
            if (!$block) continue;
            
            // For conditional blocks, check if conditions are met
            if ($block['type'] === 'conditional') {
                $conditions = json_decode($block['conditions'], true);
                $showBlock = evaluateConditions($conditions, $subscriber, $db);
                
                // Remove the block completely if conditions are not met
                if (!$showBlock) {
                    $content = str_replace($match[0], '', $content);
                }
            }
        }
    }
    
    // Process advanced personalization with fallback values
    // Format: {{tag|fallback}}
    $pattern = '/{{(\w+)\|(.*?)}}/';
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tag = $match[1];
            $fallback = $match[2];
            $value = '';
            
            // Get value based on tag
            if (isset($replacements['{{'.$tag.'}}'])) {
                $value = $replacements['{{'.$tag.'}}'];
            }
            
            // Use fallback if value is empty
            if (empty($value)) {
                $value = $fallback;
            }
            
            // Replace in content
            $content = str_replace($match[0], $value, $content);
        }
    }
    
    // Process conditional content
    // Format: {if tag="value"}content{else}alternative{/if}
    $pattern = '/{if\s+(\w+)([=!<>]+)"([^"]*)"}(.*?)(?:{else}(.*?))?{\/if}/s';
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tag = $match[1];
            $operator = $match[2];
            $compareValue = $match[3];
            $contentIfTrue = $match[4];
            $contentIfFalse = isset($match[5]) ? $match[5] : '';
            
            $show = false;
            $tagValue = '';
            
            // Get the tag value
            if (array_key_exists('{{'.$tag.'}}', $replacements)) {
                $tagValue = $replacements['{{'.$tag.'}}'];
            }
            
            // Compare values based on operator
            switch ($operator) {
                case '=':
                case '==':
                    $show = ($tagValue == $compareValue);
                    break;
                case '!=':
                    $show = ($tagValue != $compareValue);
                    break;
                case '>':
                    $show = ($tagValue > $compareValue);
                    break;
                case '<':
                    $show = ($tagValue < $compareValue);
                    break;
                case '>=':
                    $show = ($tagValue >= $compareValue);
                    break;
                case '<=':
                    $show = ($tagValue <= $compareValue);
                    break;
            }
            
            // Replace with appropriate content
            $content = str_replace($match[0], $show ? $contentIfTrue : $contentIfFalse, $content);
        }
    }
    
    return $content;
}

/**
 * Evaluate conditions for a conditional block
 */
function evaluateConditions($conditions, $subscriber, $db) {
    if (empty($conditions) || !is_array($conditions)) {
        return true; // If no conditions, show by default
    }
    
    foreach ($conditions as $condition) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        $fieldValue = '';
        
        // Get the field value based on field type
        switch ($field) {
            case 'country':
                // In a real system, you'd get this from subscriber data
                $fieldValue = $subscriber['country'] ?? '';
                break;
                
            case 'tag':
                // Check if subscriber has this tag
                $stmt = $db->prepare("SELECT COUNT(*) AS count FROM subscriber_tags WHERE email = ? AND tag = ?");
                $stmt->bind_param("ss", $subscriber['email'], $value);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $fieldValue = $result['count'] > 0 ? $value : '';
                break;
                
            case 'subscription_date':
                // Get subscription date
                $fieldValue = $subscriber['created_at'] ?? '';
                break;
                
            case 'open_rate':
                // Calculate open rate (would require tracking data)
                // For now, just use a sample value
                $fieldValue = 50;
                break;
                
            default:
                $fieldValue = $subscriber[$field] ?? '';
        }
        
        // Evaluate based on operator
        $result = evaluateOperator($fieldValue, $operator, $value);
        
        // For simplicity, we'll return true if any condition is met
        // In a more complex system, you might want to support AND/OR logic
        if ($result) {
            return true;
        }
    }
    
    // If no conditions matched, return false
    return false;
}

/**
 * Evaluate a single operator comparison
 */
function evaluateOperator($fieldValue, $operator, $compareValue) {
    switch ($operator) {
        case 'equals':
            return $fieldValue == $compareValue;
        case 'not_equals':
            return $fieldValue != $compareValue;
        case 'contains':
            return strpos($fieldValue, $compareValue) !== false;
        case 'greater_than':
            return $fieldValue > $compareValue;
        case 'less_than':
            return $fieldValue < $compareValue;
        default:
            return false;
    }
}

/**
 * Get site URL from database settings
 */
function getSiteUrl($db) {
    $siteUrl = '';
    $result = $db->query("SELECT value FROM settings WHERE name = 'site_url'");
    if ($result && $result->num_rows > 0) {
        $siteUrl = $result->fetch_assoc()['value'];
    } else {
        // Fallback to constructed URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $siteUrl = $protocol . $host;
    }
    return $siteUrl;
}