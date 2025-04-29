<?php
// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $segments[1] ?? '';

switch ($method) {
    case 'GET':
        if ($action == 'stats') {
            // Get newsletter statistics
            $result = $db->query("
                SELECT 
                    COUNT(*) as total_newsletters,
                    (SELECT COUNT(*) FROM email_opens) as total_opens,
                    (SELECT COUNT(*) FROM link_clicks) as total_clicks,
                    (SELECT COUNT(*) FROM subscribers) as subscribers_count
                FROM newsletters
            ");
            $data = $result->fetch_assoc();
            
            // Convert string values to integers
            foreach ($data as $key => $value) {
                $data[$key] = (int)$value;
            }
            
            apiResponse($data);
        } elseif (is_numeric($action)) {
            // Get specific newsletter by ID
            $stmt = $db->prepare("SELECT n.*, u.username as sender_name FROM newsletters n JOIN users u ON n.sender_id = u.id WHERE n.id = ?");
            $stmt->bind_param('i', $action);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                apiResponse(['error' => 'Newsletter not found'], 404);
            }
            
            $newsletter = $result->fetch_assoc();
            
            // Get groups this newsletter was sent to
            $groupStmt = $db->prepare("SELECT g.id, g.name FROM newsletter_groups ng JOIN groups g ON ng.group_id = g.id WHERE ng.newsletter_id = ?");
            $groupStmt->bind_param('i', $action);
            $groupStmt->execute();
            $groupResult = $groupStmt->get_result();
            
            $groups = [];
            while ($group = $groupResult->fetch_assoc()) {
                $groups[] = $group;
            }
            
            $newsletter['groups'] = $groups;
            
            // Get analytics for this newsletter
            $statsStmt = $db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM email_opens WHERE newsletter_id = ?) as opens,
                    (SELECT COUNT(*) FROM link_clicks WHERE newsletter_id = ?) as clicks
            ");
            $statsStmt->bind_param('ii', $action, $action);
            $statsStmt->execute();
            $statsResult = $statsStmt->get_result();
            $stats = $statsResult->fetch_assoc();
            
            $newsletter['stats'] = [
                'opens' => (int)$stats['opens'],
                'clicks' => (int)$stats['clicks']
            ];
            
            apiResponse($newsletter);
        } else {
            // List all newsletters with pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = ($page - 1) * $limit;
            
            // Get total count for pagination
            $totalResult = $db->query("SELECT COUNT(*) as total FROM newsletters");
            $total = $totalResult->fetch_assoc()['total'];
            
            // Get paginated results
            $stmt = $db->prepare("
                SELECT 
                    n.*, 
                    u.username as sender_name,
                    (SELECT COUNT(*) FROM email_opens WHERE newsletter_id = n.id) as opens,
                    (SELECT COUNT(*) FROM link_clicks WHERE newsletter_id = n.id) as clicks
                FROM newsletters n
                JOIN users u ON n.sender_id = u.id
                ORDER BY n.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $newsletters = [];
            while ($row = $result->fetch_assoc()) {
                // Convert stats to integers
                $row['opens'] = (int)$row['opens'];
                $row['clicks'] = (int)$row['clicks'];
                $newsletters[] = $row;
            }
            
            apiResponse([
                'newsletters' => $newsletters,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        break;
    
    // Other methods would be implemented here
        
    default:
        apiResponse(['error' => 'Method not allowed'], 405);
        break;
}