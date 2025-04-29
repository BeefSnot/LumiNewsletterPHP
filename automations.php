<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admins can manage automations
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Handle creating a new workflow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_workflow'])) {
    $name = $_POST['workflow_name'] ?? '';
    $description = $_POST['workflow_description'] ?? '';
    $trigger_type = $_POST['trigger_type'] ?? 'subscription';
    $trigger_data = [];
    
    // Handle different trigger types
    switch ($trigger_type) {
        case 'subscription':
            $trigger_data['group_id'] = $_POST['group_id'] ?? null;
            break;
        case 'date':
            $trigger_data['date_field'] = $_POST['date_field'] ?? '';
            $trigger_data['days_offset'] = $_POST['days_offset'] ?? 0;
            break;
        case 'tag_added':
            $trigger_data['tag'] = $_POST['tag_value'] ?? '';
            break;
        case 'segment_join':
            $trigger_data['segment_id'] = $_POST['segment_id'] ?? null;
            break;
        case 'inactivity':
            $trigger_data['days'] = $_POST['inactive_days'] ?? 30;
            break;
        case 'custom':
            $trigger_data['custom_data'] = $_POST['custom_data'] ?? '';
            break;
    }
    
    $trigger_data_json = json_encode($trigger_data);
    
    if (empty($name)) {
        $message = 'Workflow name is required';
        $messageType = 'error';
    } else {
        // Create new workflow
        $stmt = $db->prepare("INSERT INTO automation_workflows (name, description, trigger_type, trigger_data, status) VALUES (?, ?, ?, ?, 'draft')");
        $stmt->bind_param("ssss", $name, $description, $trigger_type, $trigger_data_json);
        
        if ($stmt->execute()) {
            $workflow_id = $stmt->insert_id;
            $message = 'Workflow created successfully';
            $messageType = 'success';
            
            // Redirect to workflow editor
            header("Location: automation_editor.php?id=$workflow_id");
            exit();
        } else {
            $message = 'Error creating workflow: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Delete workflow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_workflow'])) {
    $workflow_id = $_POST['workflow_id'] ?? 0;
    
    if ($workflow_id > 0) {
        $stmt = $db->prepare("DELETE FROM automation_workflows WHERE id = ?");
        $stmt->bind_param("i", $workflow_id);
        
        if ($stmt->execute()) {
            $message = 'Workflow deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Error deleting workflow: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Toggle workflow status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $workflow_id = $_POST['workflow_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? 'draft';
    
    if ($workflow_id > 0) {
        $stmt = $db->prepare("UPDATE automation_workflows SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $workflow_id);
        
        if ($stmt->execute()) {
            $message = 'Workflow status updated successfully';
            $messageType = 'success';
        } else {
            $message = 'Error updating workflow status: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Get all workflows
$workflowsResult = $db->query("SELECT * FROM automation_workflows ORDER BY created_at DESC");
$workflows = [];
while ($workflowsResult && $row = $workflowsResult->fetch_assoc()) {
    // Count steps in each workflow
    $stmt = $db->prepare("SELECT COUNT(*) as step_count FROM automation_steps WHERE workflow_id = ?");
    $stmt->bind_param("i", $row['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $step_count = $result->fetch_assoc()['step_count'];
    $stmt->close();
    
    $row['step_count'] = $step_count;
    $workflows[] = $row;
}

// Get groups for dropdown
$groupsResult = $db->query("SELECT id, name FROM groups ORDER BY name ASC");
$groups = [];
while ($groupsResult && $row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}

// Get segments for dropdown
$segmentsResult = $db->query("SELECT id, name FROM subscriber_segments ORDER BY name ASC");
$segments = [];
while ($segmentsResult && $row = $segmentsResult->fetch_assoc()) {
    $segments[] = $row;
}

// Get distinct tags from subscriber_tags
$tagsResult = $db->query("SELECT DISTINCT tag FROM subscriber_tags ORDER BY tag ASC");
$tags = [];
while ($tagsResult && $row = $tagsResult->fetch_assoc()) {
    $tags[] = $row['tag'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Automation | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Keep the rest of the head content -->
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
                    <h1>Email Automation</h1>
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
                    <h2><i class="fas fa-robot"></i> Email Automation Workflows</h2>
                </div>
                <div class="card-body">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="showTab('workflows-list', this)">Workflows List</button>
                        <button class="tab-btn" onclick="showTab('create-workflow', this)">Create Workflow</button>
                    </div>
                    
                    <div id="workflows-list" class="tab-content active">
                        <?php if (empty($workflows)): ?>
                            <div class="no-data">
                                <i class="fas fa-robot" style="font-size: 4rem; color: var(--gray-light); margin-bottom: 20px;"></i>
                                <h3>No automation workflows found</h3>
                                <p>Create your first workflow to start automating your email marketing.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($workflows as $workflow): ?>
                                <?php 
                                    $statusClass = '';
                                    switch ($workflow['status']) {
                                        case 'active': $statusClass = 'badge-active'; break;
                                        case 'paused': $statusClass = 'badge-paused'; break;
                                        default: $statusClass = 'badge-draft'; break;
                                    }
                                    
                                    $triggerData = json_decode($workflow['trigger_data'], true);
                                    $triggerName = '';
                                    switch ($workflow['trigger_type']) {
                                        case 'subscription': 
                                            $triggerName = 'When user subscribes'; 
                                            break;
                                        case 'date': 
                                            $triggerName = 'On specific date'; 
                                            break;
                                        case 'tag_added': 
                                            $triggerName = 'When tag is added'; 
                                            break;
                                        case 'segment_join': 
                                            $triggerName = 'When joins segment'; 
                                            break;
                                        case 'inactivity': 
                                            $triggerName = 'After inactivity'; 
                                            break;
                                        case 'custom': 
                                            $triggerName = 'Custom trigger'; 
                                            break;
                                    }
                                ?>
                                <div class="workflow-card">
                                    <div class="workflow-header">
                                        <div>
                                            <h3 class="workflow-name"><?php echo htmlspecialchars($workflow['name']); ?></h3>
                                            <div class="workflow-meta">
                                                Created: <?php echo date('M j, Y', strtotime($workflow['created_at'])); ?>
                                            </div>
                                        </div>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($workflow['status']); ?></span>
                                    </div>
                                    
                                    <div class="workflow-body">
                                        <?php if (!empty($workflow['description'])): ?>
                                            <div class="workflow-description"><?php echo htmlspecialchars($workflow['description']); ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="trigger-type">
                                            <i class="fas fa-bolt"></i> <?php echo $triggerName; ?>
                                        </div>
                                        
                                        <div class="workflow-stats">
                                            <div class="workflow-stat">
                                                <div class="stat-value"><?php echo $workflow['step_count']; ?></div>
                                                <div class="stat-label">Steps</div>
                                            </div>
                                            <div class="workflow-stat">
                                                <div class="stat-value">0</div>
                                                <div class="stat-label">In Progress</div>
                                            </div>
                                            <div class="workflow-stat">
                                                <div class="stat-value">0</div>
                                                <div class="stat-label">Completed</div>
                                            </div>
                                        </div>
                                        
                                        <div class="workflow-actions">
                                            <div>
                                                <a href="automation_editor.php?id=<?php echo $workflow['id']; ?>" class="btn btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                
                                                <?php if ($workflow['status'] === 'draft'): ?>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="workflow_id" value="<?php echo $workflow['id']; ?>">
                                                        <input type="hidden" name="new_status" value="active">
                                                        <button type="submit" name="toggle_status" class="btn btn-sm">
                                                            <i class="fas fa-play"></i> Activate
                                                        </button>
                                                    </form>
                                                <?php elseif ($workflow['status'] === 'active'): ?>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="workflow_id" value="<?php echo $workflow['id']; ?>">
                                                        <input type="hidden" name="new_status" value="paused">
                                                        <button type="submit" name="toggle_status" class="btn btn-sm">
                                                            <i class="fas fa-pause"></i> Pause
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="workflow_id" value="<?php echo $workflow['id']; ?>">
                                                        <input type="hidden" name="new_status" value="active">
                                                        <button type="submit" name="toggle_status" class="btn btn-sm">
                                                            <i class="fas fa-play"></i> Resume
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this workflow?');" style="display: inline;">
                                                <input type="hidden" name="workflow_id" value="<?php echo $workflow['id']; ?>">
                                                <button type="submit" name="delete_workflow" class="btn btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div id="create-workflow" class="tab-content">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="workflow_name">Workflow Name:</label>
                                <input type="text" id="workflow_name" name="workflow_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="workflow_description">Description (optional):</label>
                                <textarea id="workflow_description" name="workflow_description" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Trigger Type:</label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="trigger_type" value="subscription" checked onchange="showTriggerSettings('subscription')">
                                        <span>When a user subscribes</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="trigger_type" value="date" onchange="showTriggerSettings('date')">
                                        <span>On a specific date</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="trigger_type" value="tag_added" onchange="showTriggerSettings('tag_added')">
                                        <span>When a tag is added</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="trigger_type" value="segment_join" onchange="showTriggerSettings('segment_join')">
                                        <span>When subscriber joins a segment</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="trigger_type" value="inactivity" onchange="showTriggerSettings('inactivity')">
                                        <span>After subscriber inactivity</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="trigger_type" value="custom" onchange="showTriggerSettings('custom')">
                                        <span>Custom trigger</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div id="subscription-settings" class="trigger-settings active">
                                <div class="form-group">
                                    <label for="group_id">Subscription Group:</label>
                                    <select id="group_id" name="group_id">
                                        <option value="">Any group</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="date-settings" class="trigger-settings">
                                <div class="form-group">
                                    <label for="date_field">Date Field:</label>
                                    <input type="text" id="date_field" name="date_field" placeholder="e.g., birthday, anniversary">
                                </div>
                                <div class="form-group">
                                    <label for="days_offset">Days Offset:</label>
                                    <input type="number" id="days_offset" name="days_offset" value="0" min="-365" max="365">
                                    <small>Use negative values for days before the date, positive for after</small>
                                </div>
                            </div>
                            
                            <div id="tag-settings" class="trigger-settings">
                                <div class="form-group">
                                    <label for="tag_value">Tag:</label>
                                    <input type="text" id="tag_value" name="tag_value" list="available-tags" placeholder="Enter tag name">
                                    <datalist id="available-tags">
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo htmlspecialchars($tag); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                            
                            <div id="segment-settings" class="trigger-settings">
                                <div class="form-group">
                                    <label for="segment_id">Segment:</label>
                                    <select id="segment_id" name="segment_id">
                                        <?php foreach ($segments as $segment): ?>
                                            <option value="<?php echo $segment['id']; ?>"><?php echo htmlspecialchars($segment['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="inactivity-settings" class="trigger-settings">
                                <div class="form-group">
                                    <label for="inactive_days">Days of inactivity:</label>
                                    <input type="number" id="inactive_days" name="inactive_days" value="30" min="1" max="365">
                                </div>
                            </div>
                            
                            <div id="custom-settings" class="trigger-settings">
                                <div class="form-group">
                                    <label for="custom_data">Custom Trigger Data:</label>
                                    <textarea id="custom_data" name="custom_data" rows="3" placeholder="Enter custom trigger data"></textarea>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="create_workflow" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create Workflow
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script src="assets/js/sidebar.js"></script>
    <script>
        function showTab(tabId, el) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Activate clicked button
            el.classList.add('active');
        }
        
        function showTriggerSettings(triggerType) {
            // Hide all trigger settings
            document.querySelectorAll('.trigger-settings').forEach(settings => {
                settings.classList.remove('active');
            });
            
            // Show selected trigger settings
            document.getElementById(triggerType + '-settings').classList.add('active');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const mobileNavToggle = document.getElementById('mobileNavToggle');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('backdrop');
            const menuIcon = document.getElementById('menuIcon');
            
            function toggleMenu() {
                sidebar.classList.toggle('active');
                backdrop.classList.toggle('active');
                
                if (sidebar.classList.contains('active')) {
                    menuIcon.classList.remove('fa-bars');
                    menuIcon.classList.add('fa-times');
                } else {
                    menuIcon.classList.remove('fa-times');
                    menuIcon.classList.add('fa-bars');
                }
            }
            
            mobileNavToggle.addEventListener('click', toggleMenu);
            backdrop.addEventListener('click', toggleMenu);
            
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 991 && sidebar.classList.contains('active')) {
                        toggleMenu();
                    }
                });
            });
        });
    </script>
</body>
</html>