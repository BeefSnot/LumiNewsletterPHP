<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$currentVersion = require 'version.php';
$message = '';
$messageType = '';
$workflow_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$workflow = null;
$steps = [];

// Get the workflow details
if ($workflow_id > 0) {
    $stmt = $db->prepare("SELECT * FROM automation_workflows WHERE id = ?");
    $stmt->bind_param("i", $workflow_id);
    $stmt->execute();
    $workflow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$workflow) {
        header('Location: automations.php');
        exit();
    }
    
    // Get workflow steps
    $stmt = $db->prepare("SELECT * FROM automation_steps WHERE workflow_id = ? ORDER BY position ASC");
    $stmt->bind_param("i", $workflow_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $steps[] = $row;
    }
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add email step
    if (isset($_POST['add_email_step'])) {
        $subject = $_POST['email_subject'] ?? '';
        $body = $_POST['email_body'] ?? '';
        $position = count($steps);
        
        $step_data = json_encode([
            'subject' => $subject,
            'body' => $body
        ]);
        
        $stmt = $db->prepare("INSERT INTO automation_steps (workflow_id, step_type, step_data, position) VALUES (?, 'email', ?, ?)");
        $stmt->bind_param("isi", $workflow_id, $step_data, $position);
        
        if ($stmt->execute()) {
            $message = 'Email step added successfully';
            $messageType = 'success';
        } else {
            $message = 'Error adding email step: ' . $db->error;
            $messageType = 'error';
        }
        $stmt->close();
        
        // Refresh steps
        header("Location: automation_editor.php?id=$workflow_id");
        exit();
    }
    
    // Add delay step
    if (isset($_POST['add_delay_step'])) {
        $delay_value = $_POST['delay_value'] ?? 1;
        $delay_type = $_POST['delay_type'] ?? 'days';
        $position = count($steps);
        
        $step_data = json_encode([
            'delay_value' => $delay_value,
            'delay_type' => $delay_type
        ]);
        
        $stmt = $db->prepare("INSERT INTO automation_steps (workflow_id, step_type, step_data, position) VALUES (?, 'delay', ?, ?)");
        $stmt->bind_param("isi", $workflow_id, $step_data, $position);
        
        if ($stmt->execute()) {
            $message = 'Delay step added successfully';
            $messageType = 'success';
        } else {
            $message = 'Error adding delay step: ' . $db->error;
            $messageType = 'error';
        }
        $stmt->close();
        
        // Refresh steps
        header("Location: automation_editor.php?id=$workflow_id");
        exit();
    }
    
    // Add condition step
    if (isset($_POST['add_condition_step'])) {
        $condition_type = $_POST['condition_type'] ?? '';
        $condition_value = $_POST['condition_value'] ?? '';
        $position = count($steps);
        
        $step_data = json_encode([
            'condition_type' => $condition_type,
            'condition_value' => $condition_value
        ]);
        
        $stmt = $db->prepare("INSERT INTO automation_steps (workflow_id, step_type, step_data, position) VALUES (?, 'condition', ?, ?)");
        $stmt->bind_param("isi", $workflow_id, $step_data, $position);
        
        if ($stmt->execute()) {
            $message = 'Condition step added successfully';
            $messageType = 'success';
        } else {
            $message = 'Error adding condition step: ' . $db->error;
            $messageType = 'error';
        }
        $stmt->close();
        
        // Refresh steps
        header("Location: automation_editor.php?id=$workflow_id");
        exit();
    }
    
    // Add tag step
    if (isset($_POST['add_tag_step'])) {
        $tag_action = $_POST['tag_action'] ?? 'add';
        $tag_value = $_POST['tag_value'] ?? '';
        $position = count($steps);
        
        $step_data = json_encode([
            'tag_action' => $tag_action,
            'tag_value' => $tag_value
        ]);
        
        $stmt = $db->prepare("INSERT INTO automation_steps (workflow_id, step_type, step_data, position) VALUES (?, 'tag', ?, ?)");
        $stmt->bind_param("isi", $workflow_id, $step_data, $position);
        
        if ($stmt->execute()) {
            $message = 'Tag step added successfully';
            $messageType = 'success';
        } else {
            $message = 'Error adding tag step: ' . $db->error;
            $messageType = 'error';
        }
        $stmt->close();
        
        // Refresh steps
        header("Location: automation_editor.php?id=$workflow_id");
        exit();
    }
    
    // Add split step
    if (isset($_POST['add_split_step'])) {
        $split_type = $_POST['split_type'] ?? 'random';
        $split_percentage = $_POST['split_percentage'] ?? 50;
        $position = count($steps);
        
        $step_data = json_encode([
            'split_type' => $split_type,
            'split_percentage' => $split_percentage
        ]);
        
        $stmt = $db->prepare("INSERT INTO automation_steps (workflow_id, step_type, step_data, position) VALUES (?, 'split', ?, ?)");
        $stmt->bind_param("isi", $workflow_id, $step_data, $position);
        
        if ($stmt->execute()) {
            $message = 'Split test step added successfully';
            $messageType = 'success';
        } else {
            $message = 'Error adding split test step: ' . $db->error;
            $messageType = 'error';
        }
        $stmt->close();
        
        // Refresh steps
        header("Location: automation_editor.php?id=$workflow_id");
        exit();
    }
    
    // Update step positions
    if (isset($_POST['update_positions'])) {
        $positions = json_decode($_POST['update_positions'], true);
        
        if ($positions && is_array($positions)) {
            foreach ($positions as $step_id => $position) {
                $stmt = $db->prepare("UPDATE automation_steps SET position = ? WHERE id = ? AND workflow_id = ?");
                $stmt->bind_param("iii", $position, $step_id, $workflow_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Return JSON success for AJAX
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid positions data']);
            exit();
        }
    }
    
    // Delete step
    if (isset($_POST['delete_step']) && isset($_POST['step_id'])) {
        $step_id = (int)$_POST['step_id'];
        
        $stmt = $db->prepare("DELETE FROM automation_steps WHERE id = ? AND workflow_id = ?");
        $stmt->bind_param("ii", $step_id, $workflow_id);
        
        if ($stmt->execute()) {
            $message = 'Step deleted successfully';
            $messageType = 'success';
            
            // Re-order remaining steps
            $stmt = $db->prepare("SELECT id FROM automation_steps WHERE workflow_id = ? ORDER BY position ASC");
            $stmt->bind_param("i", $workflow_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $new_position = 0;
            
            while ($step = $result->fetch_assoc()) {
                $update_stmt = $db->prepare("UPDATE automation_steps SET position = ? WHERE id = ?");
                $update_stmt->bind_param("ii", $new_position, $step['id']);
                $update_stmt->execute();
                $update_stmt->close();
                $new_position++;
            }
        } else {
            $message = 'Error deleting step: ' . $db->error;
            $messageType = 'error';
        }
        $stmt->close();
        
        // Refresh steps
        header("Location: automation_editor.php?id=$workflow_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($workflow['name'] ?? 'Edit Workflow'); ?> | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/tinymce/tinymce.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <style>
        .workflow-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .step-type-selector {
            display: none;
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .step-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            grid-gap: 1rem;
        }
        
        .step-type-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }
        
        .step-type-button:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .step-type-button i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }
        
        .step-form {
            display: none;
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .step-form.active {
            display: block;
        }
        
        .workflow-steps {
            margin-top: 2rem;
        }
        
        .step-item {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            cursor: move;
        }
        
        .step-header {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .step-main {
            display: flex;
            align-items: center;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-light);
            border-radius: 50%;
            margin-right: 1rem;
            font-weight: 600;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-right: 1rem;
        }
        
        .step-icon.email {
            background: rgba(66, 133, 244, 0.1);
            color: var(--primary);
        }
        
        .step-icon.delay {
            background: rgba(251, 188, 5, 0.1);
            color: var(--warning);
        }
        
        .step-icon.condition {
            background: rgba(234, 67, 53, 0.1);
            color: var(--error);
        }
        
        .step-icon.tag {
            background: rgba(52, 168, 83, 0.1);
            color: var(--accent);
        }
        
        .step-icon.split {
            background: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .step-description {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .step-actions {
            display: flex;
        }
        
        .step-action {
            background: transparent;
            border: none;
            font-size: 1rem;
            color: var(--gray);
            cursor: pointer;
            margin-left: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .step-action:hover {
            color: var(--primary);
        }
        
        .step-action.delete:hover {
            color: var(--error);
        }
        
        .path-connector {
            height: 20px;
            width: 2px;
            background: var(--gray-light);
            margin: 0 auto;
        }
        
        .empty-workflow {
            text-align: center;
            padding: 3rem 0;
            color: var(--gray);
        }
        
        .empty-workflow i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .step-item-ghost {
            opacity: 0.5;
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <button class="mobile-nav-toggle" id="mobileNavToggle">
        <i class="fas fa-bars" id="menuIcon"></i>
    </button>
    
    <div class="backdrop" id="backdrop"></div>
    
    <div class="app-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-paper-plane"></i>
                    <h2>LumiNews</h2>
                </div>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="admin.php" class="nav-item"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="ab_testing.php" class="nav-item"><i class="fas fa-flask"></i> A/B Testing</a></li>
                    <li><a href="segments.php" class="nav-item"><i class="fas fa-tags"></i> Segments</a></li>
                    <li><a href="automations.php" class="nav-item active"><i class="fas fa-robot"></i> Automations</a></li>
                    <li><a href="logout.php" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>LumiNewsletter Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>
                        <a href="automations.php" class="header-back-link">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($workflow['name'] ?? 'Edit Workflow'); ?>
                    </h1>
                </div>
                <?php if ($workflow): ?>
                <div class="header-right">
                    <span class="workflow-status <?php echo $workflow['status']; ?>">
                        <?php echo ucfirst($workflow['status']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="workflow-container">
                <div class="workflow-header">
                    <h2><?php echo htmlspecialchars($workflow['description'] ?? ''); ?></h2>
                    <button class="btn" onclick="showStepTypeSelector()">
                        <i class="fas fa-plus"></i> Add Step
                    </button>
                </div>
                
                <div class="step-type-selector" id="step-type-selector">
                    <h3>Select Step Type</h3>
                    <div class="step-types">
                        <div class="step-type-button" onclick="showStepForm('email')">
                            <i class="fas fa-envelope"></i>
                            <span>Email</span>
                        </div>
                        <div class="step-type-button" onclick="showStepForm('delay')">
                            <i class="fas fa-clock"></i>
                            <span>Delay</span>
                        </div>
                        <div class="step-type-button" onclick="showStepForm('condition')">
                            <i class="fas fa-code-branch"></i>
                            <span>Condition</span>
                        </div>
                        <div class="step-type-button" onclick="showStepForm('tag')">
                            <i class="fas fa-tag"></i>
                            <span>Tag Action</span>
                        </div>
                        <div class="step-type-button" onclick="showStepForm('split')">
                            <i class="fas fa-random"></i>
                            <span>Split Test</span>
                        </div>
                    </div>
                </div>
                
                <!-- Step Forms -->
                <div id="email-form" class="step-form">
                    <h3>Add Email Step</h3>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="email_subject">Subject:</label>
                            <input type="text" id="email_subject" name="email_subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email_body">Email Body:</label>
                            <textarea id="email_body" name="email_body"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline" onclick="hideStepForms()">Cancel</button>
                            <button type="submit" name="add_email_step" class="btn btn-primary">Add Email Step</button>
                        </div>
                    </form>
                </div>
                
                <div id="delay-form" class="step-form">
                    <h3>Add Delay Step</h3>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="delay_value">Wait for:</label>
                            <div class="input-group">
                                <input type="number" id="delay_value" name="delay_value" min="1" value="1" required>
                                <select id="delay_type" name="delay_type">
                                    <option value="minutes">Minutes</option>
                                    <option value="hours">Hours</option>
                                    <option value="days" selected>Days</option>
                                    <option value="weeks">Weeks</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline" onclick="hideStepForms()">Cancel</button>
                            <button type="submit" name="add_delay_step" class="btn btn-primary">Add Delay Step</button>
                        </div>
                    </form>
                </div>
                
                <div id="condition-form" class="step-form">
                    <h3>Add Condition Step</h3>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="condition_type">Condition Type:</label>
                            <select id="condition_type" name="condition_type" required>
                                <option value="opened_email">Opened any email</option>
                                <option value="clicked_link">Clicked any link</option>
                                <option value="has_tag">Has specific tag</option>
                                <option value="in_segment">Is in segment</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="condition_value">Value:</label>
                            <input type="text" id="condition_value" name="condition_value" placeholder="Tag name or segment ID">
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline" onclick="hideStepForms()">Cancel</button>
                            <button type="submit" name="add_condition_step" class="btn btn-primary">Add Condition Step</button>
                        </div>
                    </form>
                </div>
                
                <div id="tag-form" class="step-form">
                    <h3>Add Tag Action Step</h3>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="tag_action">Action:</label>
                            <select id="tag_action" name="tag_action" required>
                                <option value="add">Add tag</option>
                                <option value="remove">Remove tag</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tag_value">Tag Name:</label>
                            <input type="text" id="tag_value" name="tag_value" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline" onclick="hideStepForms()">Cancel</button>
                            <button type="submit" name="add_tag_step" class="btn btn-primary">Add Tag Step</button>
                        </div>
                    </form>
                </div>
                
                <div id="split-form" class="step-form">
                    <h3>Add Split Test Step</h3>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="split_type">Split Type:</label>
                            <select id="split_type" name="split_type" required>
                                <option value="random">Random split</option>
                                <option value="tag">Based on tag</option>
                                <option value="segment">Based on segment</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="split_percentage">Split Percentage:</label>
                            <input type="range" id="split_percentage" name="split_percentage" min="10" max="90" value="50" oninput="updateSplitLabel(this.value)">
                            <span id="split_percentage_label">50% - 50%</span>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline" onclick="hideStepForms()">Cancel</button>
                            <button type="submit" name="add_split_step" class="btn btn-primary">Add Split Step</button>
                        </div>
                    </form>
                </div>
                
                <div class="workflow-steps" id="workflow-steps">
                    <?php if (empty($steps)): ?>
                        <div class="empty-workflow">
                            <i class="fas fa-sitemap"></i>
                            <h3>No steps added yet</h3>
                            <p>Start by adding a step to build your automation workflow.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($steps as $index => $step): ?>
                            <?php if ($index > 0): ?>
                                <div class="path-connector"></div>
                            <?php endif; ?>
                            
                            <?php 
                                $step_data = json_decode($step['step_data'], true);
                                $icon_class = '';
                                $step_title = '';
                                $step_desc = '';
                                
                                switch ($step['step_type']) {
                                    case 'email':
                                        $icon_class = 'fa-envelope';
                                        $step_title = 'Send Email';
                                        $step_desc = 'Subject: ' . htmlspecialchars($step_data['subject'] ?? '');
                                        break;
                                    case 'delay':
                                        $icon_class = 'fa-clock';
                                        $step_title = 'Wait';
                                        $value = $step_data['delay_value'] ?? 1;
                                        $type = $step_data['delay_type'] ?? 'days';
                                        $step_desc = "Wait for $value $type";
                                        break;
                                    case 'condition':
                                        $icon_class = 'fa-code-branch';
                                        $step_title = 'Condition';
                                        $condition_type = $step_data['condition_type'] ?? '';
                                        $condition_value = $step_data['condition_value'] ?? '';
                                        $step_desc = "Check if: $condition_type $condition_value";
                                        break;
                                    case 'tag':
                                        $icon_class = 'fa-tag';
                                        $action = $step_data['tag_action'] ?? 'add';
                                        $step_title = ucfirst($action) . ' Tag';
                                        $tag = $step_data['tag_value'] ?? '';
                                        $step_desc = "$action tag: $tag";
                                        break;
                                    case 'split':
                                        $icon_class = 'fa-random';
                                        $step_title = 'Split Test';
                                        $split_type = $step_data['split_type'] ?? 'random';
                                        $percentage = $step_data['split_percentage'] ?? 50;
                                        $step_desc = "$split_type split: $percentage%";
                                        break;
                                }
                            ?>
                            
                            <div class="step-item" data-id="<?php echo $step['id']; ?>">
                                <div class="step-header">
                                    <div class="step-main">
                                        <div class="step-number"><?php echo $index + 1; ?></div>
                                        <div class="step-icon <?php echo $step['step_type']; ?>">
                                            <i class="fas <?php echo $icon_class; ?>"></i>
                                        </div>
                                        <div class="step-content">
                                            <div class="step-title"><?php echo $step_title; ?></div>
                                            <div class="step-description"><?php echo $step_desc; ?></div>
                                        </div>
                                    </div>
                                    <div class="step-actions">
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this step?');">
                                            <input type="hidden" name="step_id" value="<?php echo $step['id']; ?>">
                                            <button type="submit" name="delete_step" class="step-action delete" title="Delete step">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize TinyMCE for email body
        tinymce.init({
            selector: '#email_body',
            height: 300,
            menubar: false,
            plugins: 'lists link image code table',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code'
        });
        
        // Toggle mobile menu
        document.getElementById('mobileNavToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('backdrop').classList.toggle('active');
            
            const menuIcon = document.getElementById('menuIcon');
            if (menuIcon.classList.contains('fa-bars')) {
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times');
            } else {
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });
        
        // Close mobile menu when clicking outside
        document.getElementById('backdrop').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('backdrop').classList.remove('active');
            document.getElementById('menuIcon').classList.remove('fa-times');
            document.getElementById('menuIcon').classList.add('fa-bars');
        });
        
        // Show/hide step type selector
        function showStepTypeSelector() {
            document.getElementById('step-type-selector').style.display = 'block';
        }
        
        // Show specific step form
        function showStepForm(type) {
            // Hide all forms
            const forms = document.querySelectorAll('.step-form');
            forms.forEach(form => {
                form.classList.remove('active');
            });
            
            // Show selected form
            document.getElementById(type + '-form').classList.add('active');
        }
        
        // Hide all step forms
        function hideStepForms() {
            document.getElementById('step-type-selector').style.display = 'none';
            const forms = document.querySelectorAll('.step-form');
            forms.forEach(form => {
                form.classList.remove('active');
            });
        }
        
        // Update split percentage label
        function updateSplitLabel(value) {
            document.getElementById('split_percentage_label').textContent = value + '% - ' + (100 - value) + '%';
        }
        
        // Make steps sortable with drag and drop
        const workflowSteps = document.getElementById('workflow-steps');
        if (workflowSteps) {
            new Sortable(workflowSteps, {
                animation: 150,
                handle: '.step-item',
                ghostClass: 'step-item-ghost',
                onEnd: function() {
                    const items = workflowSteps.querySelectorAll('.step-item');
                    const positions = {};
                    
                    items.forEach((item, index) => {
                        const stepId = item.dataset.id;
                        positions[stepId] = index;
                        
                        // Update step numbers
                        item.querySelector('.step-number').textContent = index + 1;
                    });
                    
                    // Save new positions via AJAX
                    fetch('automation_editor.php?id=<?php echo $workflow_id; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'update_positions=' + encodeURIComponent(JSON.stringify(positions))
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') {
                            console.error('Error updating positions');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>