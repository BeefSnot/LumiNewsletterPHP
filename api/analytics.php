<?php
/**
 * Analytics API Endpoint
 * Provides analytics data for newsletters
 */

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $segments[1] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'newsletter' && isset($segments[2]) && is_numeric($segments[2])) {
            $newsletter_id = (int)$segments[2];
            
            // Get newsletter details
            $stmt = $db->prepare("SELECT subject FROM newsletters WHERE id = ?");
            $stmt->bind_param('i', $newsletter_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                apiResponse(['error' => 'Newsletter not found'], 404);
            }
            
            $newsletter = $result->fetch_assoc();
            
            // Get total sent count
            $sentResult = $db->prepare("SELECT COUNT(DISTINCT email) as count FROM email_queue WHERE newsletter_id = ? AND status = 'sent'");
            $sentResult->bind_param('i', $newsletter_id);
            $sentResult->execute();
            $sentData = $sentResult->get_result()->fetch_assoc();
            $sentCount = (int)($sentData['count'] ?? 0);
            
            // Get opens data
            $opensStmt = $db->prepare("SELECT COUNT(*) as total, COUNT(DISTINCT email) as unique_count FROM email_opens WHERE newsletter_id = ?");
            $opensStmt->bind_param('i', $newsletter_id);
            $opensStmt->execute();
            $opensData = $opensStmt->get_result()->fetch_assoc();
            $totalOpens = (int)($opensData['total'] ?? 0);
            $uniqueOpens = (int)($opensData['unique_count'] ?? 0);
            $openRate = $sentCount > 0 ? round(($uniqueOpens / $sentCount) * 100, 1) : 0;
            
            // Get clicks data
            $clicksStmt = $db->prepare("SELECT COUNT(*) as total, COUNT(DISTINCT email) as unique_count FROM link_clicks WHERE newsletter_id = ?");
            $clicksStmt->bind_param('i', $newsletter_id);
            $clicksStmt->execute();
            $clicksData = $clicksStmt->get_result()->fetch_assoc();
            $totalClicks = (int)($clicksData['total'] ?? 0);
            $uniqueClicks = (int)($clicksData['unique_count'] ?? 0);
            $clickRate = $sentCount > 0 ? round(($uniqueClicks / $sentCount) * 100, 1) : 0;
            
            // Get top clicked links
            $linksStmt = $db->prepare("
                SELECT url, COUNT(*) as clicks 
                FROM link_clicks 
                WHERE newsletter_id = ? 
                GROUP BY url 
                ORDER BY clicks DESC 
                LIMIT 10
            ");
            $linksStmt->bind_param('i', $newsletter_id);
            $linksStmt->execute();
            $linksResult = $linksStmt->get_result();
            $topLinks = [];
            while ($link = $linksResult->fetch_assoc()) {
                $topLinks[] = [
                    'url' => $link['url'],
                    'clicks' => (int)$link['clicks']
                ];
            }
            
            // Get geographic data
            $geoStmt = $db->prepare("
                SELECT country, COUNT(*) as count 
                FROM email_geo_data eg
                JOIN email_opens eo ON eg.open_id = eo.id
                WHERE eo.newsletter_id = ? 
                GROUP BY country 
                ORDER BY count DESC
                LIMIT 10
            ");
            $geoStmt->bind_param('i', $newsletter_id);
            $geoStmt->execute();
            $geoResult = $geoStmt->get_result();
            $geoData = [];
            while ($geo = $geoResult->fetch_assoc()) {
                $geoData[] = [
                    'country' => $geo['country'],
                    'count' => (int)$geo['count']
                ];
            }
            
            // Prepare response
            $response = [
                'newsletter_id' => $newsletter_id,
                'subject' => $newsletter['subject'],
                'stats' => [
                    'sent' => $sentCount,
                    'opens' => [
                        'total' => $totalOpens,
                        'unique' => $uniqueOpens,
                        'rate' => $openRate
                    ],
                    'clicks' => [
                        'total' => $totalClicks,
                        'unique' => $uniqueClicks,
                        'rate' => $clickRate
                    ]
                ],
                'top_links' => $topLinks,
                'geo_data' => $geoData
            ];
            
            apiResponse($response);
        } else {
            // Return general analytics data
            $result = $db->query("
                SELECT 
                    (SELECT COUNT(*) FROM newsletters) as total_newsletters,
                    (SELECT COUNT(*) FROM email_opens) as total_opens,
                    (SELECT COUNT(*) FROM link_clicks) as total_clicks,
                    (SELECT COUNT(*) FROM subscribers) as total_subscribers,
                    (SELECT COUNT(DISTINCT email) FROM email_opens) as unique_opens,
                    (SELECT COUNT(DISTINCT email) FROM link_clicks) as unique_clicks
            ");
            
            if ($result) {
                $data = $result->fetch_assoc();
                
                // Convert string values to integers
                foreach ($data as $key => $value) {
                    $data[$key] = (int)$value;
                }
                
                apiResponse($data);
            } else {
                apiResponse(['error' => 'Failed to retrieve analytics data'], 500);
            }
        }
        break;
        
    default:
        apiResponse(['error' => 'Method not allowed'], 405);
        break;
}