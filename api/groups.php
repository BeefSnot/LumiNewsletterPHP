<?php
/**
 * Groups API Endpoint
 * Manage subscriber groups
 */

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $segments[1] ?? '';

switch ($method) {
    case 'GET':
        if (is_numeric($action)) {
            // Get specific group
            $groupId = (int)$action;
            $stmt = $db->prepare("SELECT * FROM groups WHERE id = ?");
            $stmt->bind_param('i', $groupId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                apiResponse(['error' => 'Group not found'], 404);
            }
            
            $group = $result->fetch_assoc();
            
            // Get subscriber count
            $countStmt = $db->prepare("SELECT COUNT(*) as count FROM group_subscriptions WHERE group_id = ?");
            $countStmt->bind_param('i', $groupId);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $count = $countResult->fetch_assoc()['count'];
            
            $group['subscribers_count'] = (int)$count;
            
            apiResponse($group);
        } else if ($action === 'subscribers' && isset($segments[2]) && is_numeric($segments[2])) {
            // Get subscribers in a group
            $groupId = (int)$segments[2];
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
            $offset = ($page - 1) * $limit;
            
            // Check if group exists
            $checkStmt = $db->prepare("SELECT id FROM groups WHERE id = ?");
            $checkStmt->bind_param('i', $groupId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                apiResponse(['error' => 'Group not found'], 404);
            }
            
            // Count total subscribers in this group
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM group_subscriptions WHERE group_id = ?");
            $countStmt->bind_param('i', $groupId);
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            
            // Get paginated subscribers
            $stmt = $db->prepare("
                SELECT s.* 
                FROM subscribers s
                JOIN group_subscriptions gs ON s.email = gs.email
                WHERE gs.group_id = ?
                ORDER BY s.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('iii', $groupId, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $subscribers = [];
            while ($row = $result->fetch_assoc()) {
                $subscribers[] = $row;
            }
            
            apiResponse([
                'group_id' => $groupId,
                'subscribers' => $subscribers,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } else {
            // List all groups
            $result = $db->query("SELECT * FROM groups ORDER BY name ASC");
            $groups = [];
            
            while ($row = $result->fetch_assoc()) {
                $groups[] = $row;
            }
            
            apiResponse(['groups' => $groups]);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Create new group
        if (!isset($data['name']) || empty($data['name'])) {
            apiResponse(['error' => 'Group name is required'], 400);
        }
        
        $name = $data['name'];
        $description = $data['description'] ?? '';
        
        // Check if group already exists
        $stmt = $db->prepare("SELECT id FROM groups WHERE name = ?");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            apiResponse(['error' => 'A group with this name already exists'], 409);
        }
        
        // Insert new group
        $stmt = $db->prepare("INSERT INTO groups (name, description) VALUES (?, ?)");
        $stmt->bind_param('ss', $name, $description);
        
        if ($stmt->execute()) {
            $groupId = $db->insert_id;
            apiResponse([
                'id' => $groupId,
                'name' => $name,
                'description' => $description,
                'message' => 'Group created successfully'
            ], 201);
        } else {
            apiResponse(['error' => 'Failed to create group: ' . $db->error], 500);
        }
        break;
        
    case 'PUT':
        if (!is_numeric($action)) {
            apiResponse(['error' => 'Invalid group ID'], 400);
        }
        
        $groupId = (int)$action;
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Check if group exists
        $stmt = $db->prepare("SELECT id FROM groups WHERE id = ?");
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            apiResponse(['error' => 'Group not found'], 404);
        }
        
        // Update group
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        
        if (!$name && !$description) {
            apiResponse(['error' => 'No update data provided'], 400);
        }
        
        $query = "UPDATE groups SET ";
        $params = [];
        $types = '';
        
        if ($name) {
            $query .= "name = ?, ";
            $params[] = $name;
            $types .= 's';
        }
        
        if ($description) {
            $query .= "description = ?, ";
            $params[] = $description;
            $types .= 's';
        }
        
        // Remove trailing comma
        $query = rtrim($query, ', ');
        $query .= " WHERE id = ?";
        $params[] = $groupId;
        $types .= 'i';
        
        $stmt = $db->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            apiResponse(['message' => 'Group updated successfully']);
        } else {
            apiResponse(['error' => 'Failed to update group: ' . $db->error], 500);
        }
        break;
        
    case 'DELETE':
        if (!is_numeric($action)) {
            apiResponse(['error' => 'Invalid group ID'], 400);
        }
        
        $groupId = (int)$action;
        
        // Check if group exists
        $stmt = $db->prepare("SELECT id FROM groups WHERE id = ?");
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            apiResponse(['error' => 'Group not found'], 404);
        }
        
        // Delete group
        $stmt = $db->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->bind_param('i', $groupId);
        
        if ($stmt->execute()) {
            apiResponse(['message' => 'Group deleted successfully']);
        } else {
            apiResponse(['error' => 'Failed to delete group: ' . $db->error], 500);
        }
        break;
        
    default:
        apiResponse(['error' => 'Method not allowed'], 405);
        break;
}