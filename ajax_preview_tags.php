<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    exit('Not authorized');
}

$email = $_GET['email'] ?? '';
$content = $_GET['content'] ?? '';

if (empty($email) || empty($content)) {
    exit('Missing required parameters');
}

// Get subscriber data
$stmt = $db->prepare("SELECT * FROM group_subscriptions WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$subscriber = $result->fetch_assoc();

if (!$subscriber) {
    // If subscriber not found, use placeholder data
    echo str_replace(
        ['{{first_name}}', '{{last_name}}', '{{email}}', '{{subscription_date}}', '{{current_date}}'],
        ['[Unknown]', '[Unknown]', $email, '[Not subscribed]', date('F j, Y')],
        $content
    );
    exit();
}

// Process the content with personalization tags
echo processPersonalization($content, $subscriber, $db);

function processPersonalization($content, $subscriber, $db) {
    // Process the content with actual subscriber data
    $replacedContent = $content;

    // Replace basic tags
    $replacedContent = str_replace('{{email}}', $subscriber['email'], $replacedContent);
    $replacedContent = str_replace('{{first_name}}', $subscriber['first_name'] ?? 'Subscriber', $replacedContent);
    $replacedContent = str_replace('{{last_name}}', $subscriber['last_name'] ?? '', $replacedContent);

    // Full name (combine first and last if available)
    $fullName = trim(($subscriber['first_name'] ?? '') . ' ' . ($subscriber['last_name'] ?? ''));
    $fullName = empty($fullName) ? 'Subscriber' : $fullName;
    $replacedContent = str_replace('{{full_name}}', $fullName, $replacedContent);

    // Format subscription date
    $subscriptionDate = date('F j, Y', strtotime($subscriber['created_at'] ?? 'now'));
    $replacedContent = str_replace('{{subscription_date}}', $subscriptionDate, $replacedContent);

    // Current date
    $currentDate = date('F j, Y');
    $replacedContent = str_replace('{{current_date}}', $currentDate, $replacedContent);

    // Unsubscribe link
    $token = md5($subscriber['email'] . '|' . ($subscriber['token'] ?? 'token'));
    $unsubscribeLink = 'unsubscribe.php?email=' . urlencode($subscriber['email']) . '&token=' . $token;
    $replacedContent = str_replace('{{unsubscribe_link}}', $unsubscribeLink, $replacedContent);

    // Process advanced personalization with fallback values
    // Format: {{tag|fallback}}
    $pattern = '/{{(\w+)\|(.*?)}}/';
    if (preg_match_all($pattern, $replacedContent, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tag = $match[1];
            $fallback = $match[2];
            $value = '';

            // Get value based on tag
            switch ($tag) {
                case 'email':
                    $value = $subscriber['email'] ?? '';
                    break;
                case 'first_name':
                    $value = $subscriber['first_name'] ?? '';
                    break;
                case 'last_name':
                    $value = $subscriber['last_name'] ?? '';
                    break;
                case 'full_name':
                    $value = $fullName;
                    break;
                default:
                    $value = '';
            }

            // Use fallback if value is empty
            if (empty($value)) {
                $value = $fallback;
            }

            // Replace in content
            $replacedContent = str_replace($match[0], $value, $replacedContent);
        }
    }

    // Process conditional logic
    // Format: {if tag="value"}content{else}alternative{/if}
    $pattern = '/{if\s+(\w+)([=!<>]+)"([^"]*)"}(.*?)(?:{else}(.*?))?{\/if}/s';
    if (preg_match_all($pattern, $replacedContent, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $field = $match[1];
            $operator = $match[2];
            $compareValue = $match[3];
            $content = $match[4];
            $elseContent = isset($match[5]) ? $match[5] : '';

            $fieldValue = '';

            // Get the field value
            switch ($field) {
                case 'tag':
                    // Check if subscriber has this tag
                    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM subscriber_tags WHERE email = ? AND tag = ?");
                    $stmt->bind_param("ss", $subscriber['email'], $compareValue);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $fieldValue = $result['count'] > 0 ? $compareValue : '';
                    break;

                case 'subscription_months':
                    // Calculate months since subscription
                    $subscriptionDate = new DateTime($subscriber['created_at'] ?? 'now');
                    $currentDate = new DateTime();
                    $interval = $currentDate->diff($subscriptionDate);
                    $fieldValue = ($interval->y * 12) + $interval->m;
                    break;

                default:
                    $fieldValue = $subscriber[$field] ?? '';
            }

            // Evaluate the condition
            $conditionMet = false;
            switch ($operator) {
                case '=':
                    $conditionMet = $fieldValue == $compareValue;
                    break;
                case '!=':
                    $conditionMet = $fieldValue != $compareValue;
                    break;
                case '>':
                    $conditionMet = $fieldValue > $compareValue;
                    break;
                case '<':
                    $conditionMet = $fieldValue < $compareValue;
                    break;
                case '>=':
                    $conditionMet = $fieldValue >= $compareValue;
                    break;
                case '<=':
                    $conditionMet = $fieldValue <= $compareValue;
                    break;
            }

            // Replace the conditional block with the appropriate content
            $replacement = $conditionMet ? $content : $elseContent;
            $replacedContent = str_replace($match[0], $replacement, $replacedContent);
        }
    }

    return nl2br(htmlspecialchars($replacedContent));
}
?>