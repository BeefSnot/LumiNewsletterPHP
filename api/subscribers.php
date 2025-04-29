<?php
// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $segments[1] ?? '';

switch ($method) {
    case 'GET':
        if ($action == 'count') {
            // Count subscribers
            $result = $db->query("SELECT COUNT(*) as count FROM subscribers");
            $data = $result->fetch_assoc();
            apiResponse(['count' => (int)$data['count']]);
        } elseif (is_numeric($action)) {
            // Get specific subscriber by ID
            $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
            $stmt->bind_param('i', $action);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                apiResponse(['error' => 'Subscriber not found'], 404);
            }
            
            $data = $result->fetch_assoc();
            apiResponse($data);
        } else {
            // List all subscribers with pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            // Optional search filter
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $whereClause = '';
            $params = [];
            $types = '';
            
            if (!empty($search)) {
                $whereClause = "WHERE email LIKE ? OR name LIKE ?";
                $searchTerm = "%$search%";
                $params = [$searchTerm, $searchTerm];
                $types = 'ss';
            }
            
            // Get total count for pagination
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM subscribers $whereClause");
            if (!empty($search)) {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $totalResult = $countStmt->get_result();
            $total = $totalResult->fetch_assoc()['total'];
            
            // Get paginated results
            $stmt = $db->prepare("SELECT * FROM subscribers $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
            if (!empty($search)) {
                $stmt->bind_param($types . 'ii', ...[...$params, $limit, $offset]);
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $subscribers = [];
            while ($row = $result->fetch_assoc()) {
                $subscribers[] = $row;
            }
            
            apiResponse([
                'subscribers' => $subscribers,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['email']) || empty($data['email'])) {
            apiResponse(['error' => 'Email is required'], 400);
        }
        
        $email = $data['email'];
        $name = $data['name'] ?? '';
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            apiResponse(['error' => 'Email already subscribed'], 409);
        }
        
        // Insert new subscriber
        $stmt = $db->prepare("INSERT INTO subscribers (email, name) VALUES (?, ?)");
        $stmt->bind_param('ss', $email, $name);
        
        if ($stmt->execute()) {
            $subscriberId = $db->insert_id;
            
            // Add to group if specified
            if (isset($data['group_id']) && !empty($data['group_id'])) {
                $groupId = (int)$data['group_id'];
                $stmt = $db->prepare("INSERT INTO group_subscriptions (group_id, email) VALUES (?, ?)");
                $stmt->bind_param('is', $groupId, $email);
                $stmt->execute();
            }
            
            apiResponse([
                'id' => $subscriberId,
                'email' => $email,
                'name' => $name,
                'message' => 'Subscriber added successfully'
            ], 201);
        } else {
            apiResponse(['error' => 'Failed to add subscriber: ' . $db->error], 500);
        }
        break;
        
    // DELETE and PUT methods would be implemented here
        
    default:
        apiResponse(['error' => 'Method not allowed'], 405);
        break;
}