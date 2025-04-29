<?php
/**
 * Social Media API Endpoint
 * Provides social sharing statistics
 */

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $segments[1] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'stats') {
            // Get overall social sharing statistics
            $result = $db->query("
                SELECT 
                    platform, 
                    SUM(share_count) as share_count,
                    SUM(click_count) as click_count 
                FROM social_shares
                GROUP BY platform
            ");
            
            $platforms = [];
            $totalShares = 0;
            $totalClicks = 0;
            
            while ($row = $result->fetch_assoc()) {
                $platform = $row['platform'];
                $shareCount = (int)$row['share_count'];
                $clickCount = (int)$row['click_count'];
                
                $platforms[$platform] = [
                    'shares' => $shareCount,
                    'clicks' => $clickCount
                ];
                
                $totalShares += $shareCount;
                $totalClicks += $clickCount;
            }
            
            apiResponse([
                'platforms' => $platforms,
                'total_shares' => $totalShares,
                'total_clicks' => $totalClicks
            ]);
        } else if ($action === 'newsletter' && isset($segments[2]) && is_numeric($segments[2])) {
            // Get social sharing statistics for a specific newsletter
            $newsletterId = (int)$segments[2];
            
            // Check if newsletter exists
            $newsletterCheck = $db->prepare("SELECT subject FROM newsletters WHERE id = ?");
            $newsletterCheck->bind_param('i', $newsletterId);
            $newsletterCheck->execute();
            $newsletterResult = $newsletterCheck->get_result();
            
            if ($newsletterResult->num_rows === 0) {
                apiResponse(['error' => 'Newsletter not found'], 404);
            }
            
            $newsletter = $newsletterResult->fetch_assoc();
            
            // Get social sharing data for this newsletter
            $stmt = $db->prepare("
                SELECT 
                    platform, 
                    share_count,
                    click_count 
                FROM social_shares
                WHERE newsletter_id = ?
            ");
            $stmt->bind_param('i', $newsletterId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $platforms = [];
            $totalShares = 0;
            $totalClicks = 0;
            
            while ($row = $result->fetch_assoc()) {
                $platform = $row['platform'];
                $shareCount = (int)$row['share_count'];
                $clickCount = (int)$row['click_count'];
                
                $platforms[$platform] = [
                    'shares' => $shareCount,
                    'clicks' => $clickCount
                ];
                
                $totalShares += $shareCount;
                $totalClicks += $clickCount;
            }
            
            apiResponse([
                'newsletter_id' => $newsletterId,
                'subject' => $newsletter['subject'],
                'platforms' => $platforms,
                'total_shares' => $totalShares,
                'total_clicks' => $totalClicks
            ]);
        } else if ($action === 'trending') {
            // Get trending newsletters based on social activity
            $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10;
            
            $stmt = $db->prepare("
                SELECT 
                    n.id,
                    n.subject,
                    SUM(ss.share_count) as total_shares,
                    SUM(ss.click_count) as total_clicks,
                    DATE_FORMAT(n.created_at, '%Y-%m-%d') as date
                FROM newsletters n
                JOIN social_shares ss ON n.id = ss.newsletter_id
                WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY n.id
                ORDER BY total_shares DESC, total_clicks DESC
                LIMIT ?
            ");
            $stmt->bind_param('ii', $days, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $trending = [];
            while ($row = $result->fetch_assoc()) {
                // Convert numeric values to integers
                $row['total_shares'] = (int)$row['total_shares'];
                $row['total_clicks'] = (int)$row['total_clicks'];
                $trending[] = $row;
            }
            
            apiResponse([
                'trending_newsletters' => $trending,
                'period_days' => $days
            ]);
        } else {
            apiResponse(['error' => 'Invalid endpoint'], 404);
        }
        break;
        
    case 'POST':
        if ($action === 'share') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['newsletter_id']) || !isset($data['platform'])) {
                apiResponse(['error' => 'Missing required parameters'], 400);
            }
            
            $newsletterId = (int)$data['newsletter_id'];
            $platform = $data['platform'];
            
            // Check if newsletter exists
            $newsletterCheck = $db->prepare("SELECT id FROM newsletters WHERE id = ?");
            $newsletterCheck->bind_param('i', $newsletterId);
            $newsletterCheck->execute();
            if ($newsletterCheck->get_result()->num_rows === 0) {
                apiResponse(['error' => 'Newsletter not found'], 404);
            }
            
            // Check if share record already exists
            $checkStmt = $db->prepare("SELECT id FROM social_shares WHERE newsletter_id = ? AND platform = ?");
            $checkStmt->bind_param("is", $newsletterId, $platform);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing share record
                $shareId = $result->fetch_assoc()['id'];
                $db->query("UPDATE social_shares SET share_count = share_count + 1 WHERE id = $shareId");
                
                apiResponse(['message' => 'Share count updated', 'share_id' => $shareId]);
            } else {
                // Create new share record
                $stmt = $db->prepare("INSERT INTO social_shares (newsletter_id, platform, share_count) VALUES (?, ?, 1)");
                $stmt->bind_param("is", $newsletterId, $platform);
                
                if ($stmt->execute()) {
                    $shareId = $db->insert_id;
                    apiResponse(['message' => 'Share recorded successfully', 'share_id' => $shareId], 201);
                } else {
                    apiResponse(['error' => 'Failed to record share: ' . $db->error], 500);
                }
            }
        } else if ($action === 'click') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['share_id'])) {
                apiResponse(['error' => 'Share ID is required'], 400);
            }
            
            $shareId = (int)$data['share_id'];
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $referrer = $data['referrer'] ?? null;
            
            // Check if share exists
            $shareCheck = $db->prepare("SELECT id FROM social_shares WHERE id = ?");
            $shareCheck->bind_param('i', $shareId);
            $shareCheck->execute();
            if ($shareCheck->get_result()->num_rows === 0) {
                apiResponse(['error' => 'Share not found'], 404);
            }
            
            // Update click count
            $db->query("UPDATE social_shares SET click_count = click_count + 1 WHERE id = $shareId");
            
            // Record detailed click data
            $stmt = $db->prepare("INSERT INTO social_clicks (share_id, ip_address, referrer) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $shareId, $ipAddress, $referrer);
            
            if ($stmt->execute()) {
                apiResponse(['message' => 'Click recorded successfully']);
            } else {
                apiResponse(['error' => 'Failed to record click: ' . $db->error], 500);
            }
        } else {
            apiResponse(['error' => 'Invalid endpoint'], 404);
        }
        break;
        
    default:
        apiResponse(['error' => 'Method not allowed'], 405);
        break;
}