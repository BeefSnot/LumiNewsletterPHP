<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new group
    if (isset($_POST['create_group'])) {
        $name = trim($_POST['group_name']);
        $description = trim($_POST['group_description']);
        
        if (empty($name)) {
            $message = 'Group name is required';
            $messageType = 'error';
        } else {
            // Check if group already exists
            $checkStmt = $db->prepare("SELECT id FROM groups WHERE name = ?");
            $checkStmt->bind_param('s', $name);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'A group with this name already exists';
                $messageType = 'error';
            } else {
                $stmt = $db->prepare("INSERT INTO groups (name, description) VALUES (?, ?)");
                $stmt->bind_param('ss', $name, $description);
                
                if ($stmt->execute()) {
                    $message = 'Group created successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Error creating group: ' . $db->error;
                    $messageType = 'error';
                }
            }
        }
    }
    
    // Update group
    if (isset($_POST['update_group'])) {
        $groupId = (int)$_POST['group_id'];
        $name = trim($_POST['group_name']);
        $description = trim($_POST['group_description']);
        
        if (empty($name)) {
            $message = 'Group name is required';
            $messageType = 'error';
        } else {
            // Check if another group with the same name exists
            $checkStmt = $db->prepare("SELECT id FROM groups WHERE name = ? AND id != ?");
            $checkStmt->bind_param('si', $name, $groupId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = 'Another group with this name already exists';
                $messageType = 'error';
            } else {
                $stmt = $db->prepare("UPDATE groups SET name = ?, description = ? WHERE id = ?");
                $stmt->bind_param('ssi', $name, $description, $groupId);
                
                if ($stmt->execute()) {
                    $message = 'Group updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating group: ' . $db->error;
                    $messageType = 'error';
                }
            }
        }
    }
    
    // Delete group
    if (isset($_POST['delete_group'])) {
        $groupId = (int)$_POST['group_id'];
        
        // First, remove all subscribers from this group
        $db->query("DELETE FROM group_subscriptions WHERE group_id = $groupId");
        
        // Then delete the group
        $stmt = $db->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->bind_param('i', $groupId);
        
        if ($stmt->execute()) {
            $message = 'Group deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Error deleting group: ' . $db->error;
            $messageType = 'error';
        }
    }
}

// Get all groups
$groupsResult = $db->query("
    SELECT g.*, 
        (SELECT COUNT(*) FROM group_subscriptions WHERE group_id = g.id) as subscriber_count 
    FROM groups g 
    ORDER BY g.name
");

$groups = [];
if ($groupsResult) {
    while ($row = $groupsResult->fetch_assoc()) {
        $groups[] = $row;
    }
}

// Get group details for editing
$editGroup = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $groupId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $editGroup = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Groups | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .group-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .group-header {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .group-name {
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
            color: var(--primary);
        }
        
        .group-body {
            padding: 15px;
        }
        
        .group-description {
            color: var(--gray);
            margin-bottom: 15px;
            font-style: italic;
        }
        
        .group-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .subscriber-badge {
            display: inline-block;
            background: var(--gray-light);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 500;
        }
        
        .group-filter {
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Mobile navigation toggle button -->
    <button class="mobile-nav-toggle" id="mobileNavToggle">
        <i class="fas fa-bars" id="menuIcon"></i>
    </button>
    
    <!-- Backdrop for mobile menu -->
    <div class="backdrop" id="backdrop"></div>
    
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Manage Subscriber Groups</h1>
                </div>
                <div class="header-right">
                    <button class="btn btn-primary" data-toggle="modal" data-target="create-group-modal">
                        <i class="fas fa-plus"></i> New Group
                    </button>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>Subscriber Groups</h2>
                </div>
                <div class="card-body">
                    <?php if ($editGroup): ?>
                        <div class="edit-form">
                            <h3>Edit Group</h3>
                            <form method="post">
                                <input type="hidden" name="group_id" value="<?php echo $editGroup['id']; ?>">
                                
                                <div class="form-group">
                                    <label for="group_name">Group Name:</label>
                                    <input type="text" id="group_name" name="group_name" value="<?php echo htmlspecialchars($editGroup['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="group_description">Description:</label>
                                    <textarea id="group_description" name="group_description" rows="3"><?php echo htmlspecialchars($editGroup['description']); ?></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <a href="manage_groups.php" class="btn btn-outline">Cancel</a>
                                    <button type="submit" name="update_group" class="btn btn-primary">Update Group</button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <?php if (empty($groups)): ?>
                            <div class="empty-state">
                                <i class="fas fa-layer-group"></i>
                                <h3>No groups found</h3>
                                <p>Create your first subscriber group to organize your audience.</p>
                                <button class="btn btn-primary" data-toggle="modal" data-target="create-group-modal">
                                    <i class="fas fa-plus"></i> Create Group
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="group-filter">
                                <input type="text" id="group-search" placeholder="Search groups..." class="form-control">
                            </div>
                            
                            <div class="groups-grid">
                                <?php foreach ($groups as $group): ?>
                                    <div class="group-card">
                                        <div class="group-header">
                                            <h3 class="group-name"><?php echo htmlspecialchars($group['name']); ?></h3>
                                            <span class="subscriber-badge">
                                                <i class="fas fa-users"></i> <?php echo (int)$group['subscriber_count']; ?>
                                            </span>
                                        </div>
                                        <div class="group-body">
                                            <div class="group-description">
                                                <?php echo htmlspecialchars($group['description'] ?: 'No description'); ?>
                                            </div>
                                            <div class="group-actions">
                                                <a href="manage_groups.php?edit=<?php echo $group['id']; ?>" class="btn btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this group? All subscribers will be removed from this group.');">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <button type="submit" name="delete_group" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Create Group Modal -->
    <div class="modal" id="create-group-modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Create New Group</h3>
                    <button type="button" class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="new_group_name">Group Name:</label>
                            <input type="text" id="new_group_name" name="group_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_group_description">Description:</label>
                            <textarea id="new_group_description" name="group_description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline close-modal">Cancel</button>
                            <button type="submit" name="create_group" class="btn btn-primary">Create Group</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script src="assets/js/sidebar.js"></script>
    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Modal open buttons
            const modalToggleButtons = document.querySelectorAll('[data-toggle="modal"]');
            modalToggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetModalId = this.getAttribute('data-target');
                    document.getElementById(targetModalId).classList.add('active');
                });
            });
            
            // Modal close buttons
            const closeButtons = document.querySelectorAll('.close-modal');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    modal.classList.remove('active');
                });
            });
            
            // Close modal when clicking outside
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('active');
                    }
                });
            });
            
            // Group search filter
            const groupSearch = document.getElementById('group-search');
            if (groupSearch) {
                groupSearch.addEventListener('input', function() {
                    const searchValue = this.value.toLowerCase();
                    const groupCards = document.querySelectorAll('.group-card');
                    
                    groupCards.forEach(card => {
                        const groupName = card.querySelector('.group-name').textContent.toLowerCase();
                        const groupDesc = card.querySelector('.group-description').textContent.toLowerCase();
                        
                        if (groupName.includes(searchValue) || groupDesc.includes(searchValue)) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>