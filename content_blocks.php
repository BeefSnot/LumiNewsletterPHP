<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$currentVersion = require 'version.php';
$message = '';
$messageType = '';

// Handle creating/updating content blocks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_block'])) {
        $id = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
        $name = $_POST['block_name'] ?? '';
        $description = $_POST['block_description'] ?? '';
        $content = $_POST['block_content'] ?? '';
        $type = $_POST['block_type'] ?? 'static';
        $conditions = ($type === 'conditional') ? $_POST['block_conditions'] ?? '' : null;

        if (empty($name)) {
            $message = 'Block name is required';
            $messageType = 'error';
        } else {
            if ($id > 0) {
                // Update existing block
                $stmt = $db->prepare("UPDATE content_blocks SET name = ?, description = ?, content = ?, 
                                     type = ?, conditions = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $name, $description, $content, $type, $conditions, $id);
                
                if ($stmt->execute()) {
                    $message = 'Content block updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating content block: ' . $db->error;
                    $messageType = 'error';
                }
            } else {
                // Create new block
                $stmt = $db->prepare("INSERT INTO content_blocks (name, description, content, type, conditions) 
                                    VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $description, $content, $type, $conditions);
                
                if ($stmt->execute()) {
                    $message = 'Content block created successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Error creating content block: ' . $db->error;
                    $messageType = 'error';
                }
            }
            
            // Reset form after successful submission
            $id = 0;
            $name = '';
            $description = '';
            $content = '';
            $type = 'static';
            $conditions = '';
        }
    }
    
    // Delete block
    if (isset($_POST['delete_block'])) {
        $id = (int)$_POST['block_id'];
        $stmt = $db->prepare("DELETE FROM content_blocks WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = 'Content block deleted successfully';
            $messageType = 'success';
        } else {
            $message = 'Error deleting content block: ' . $db->error;
            $messageType = 'error';
        }
    }
}

// Get block for editing
$editBlock = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM content_blocks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editBlock = $stmt->get_result()->fetch_assoc();
}

// Get all content blocks
$result = $db->query("SELECT * FROM content_blocks ORDER BY name ASC");
$blocks = [];
while ($row = $result->fetch_assoc()) {
    $blocks[] = $row;
}

// Get all available personalization tags
$result = $db->query("SELECT * FROM personalization_tags ORDER BY tag_name ASC");
$personalizationTags = [];
while ($row = $result->fetch_assoc()) {
    $personalizationTags[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Content Blocks | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/tinymce/tinymce/js/tinymce/tinymce.min.js"></script>
    <style>
        .block-list {
            margin-top: 20px;
        }
        
        .block-item {
            border-left: 3px solid var(--primary);
            background: white;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .block-info {
            flex: 1;
        }
        
        .block-name {
            font-weight: 600;
            color: var(--primary);
            margin: 0 0 5px 0;
        }
        
        .block-description {
            color: var(--gray);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .block-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            background: var(--gray-light);
            margin-top: 5px;
        }
        
        .block-type.static {
            background: #e1f5fe;
            color: #0288d1;
        }
        
        .block-type.dynamic {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .block-type.conditional {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .block-actions {
            display: flex;
            gap: 10px;
        }
        
        .block-action {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray);
            transition: color 0.2s;
        }
        
        .block-action:hover {
            color: var(--primary);
        }
        
        .block-action.delete:hover {
            color: var(--error);
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .tab-btn {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-weight: 500;
            color: var(--gray);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            color: var(--primary);
        }
        
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        .personalization-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tag-item {
            padding: 5px 10px;
            background: var(--gray-light);
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .tag-item:hover {
            background: var(--primary-light);
        }
        
        .tag-preview {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-top: 20px;
        }
        
        .conditional-group {
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 15px;
        }
        
        .conditional-rule {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .conditional-actions {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <!-- Mobile navigation toggle -->
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
                    <?php if ($isAdmin): ?>
                    <li><a href="admin.php" class="nav-item"><i class="fas fa-cog"></i> Admin Settings</a></li>
                    <?php endif; ?>
                    <li><a href="create_theme.php" class="nav-item"><i class="fas fa-palette"></i> Create Theme</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-envelope"></i> Manage Newsletters</a></li>
                    <li><a href="content_blocks.php" class="nav-item active"><i class="fas fa-puzzle-piece"></i> Content Blocks</a></li>
                    <?php if ($isAdmin): ?>
                    <li><a href="manage_subscriptions.php" class="nav-item"><i class="fas fa-users"></i> Subscribers</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-user-shield"></i> Users</a></li>
                    <li><a href="manage_smtp.php" class="nav-item"><i class="fas fa-server"></i> SMTP Settings</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="ab_testing.php" class="nav-item"><i class="fas fa-flask"></i> A/B Testing</a></li>
                    <li><a href="segments.php" class="nav-item"><i class="fas fa-tags"></i> Segments</a></li>
                    <li><a href="automations.php" class="nav-item"><i class="fas fa-robot"></i> Automations</a></li>
                    <li><a href="embed_docs.php" class="nav-item"><i class="fas fa-code"></i> Embed Widget</a></li>
                    <?php endif; ?>
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
                    <h1>Dynamic Content Blocks</h1>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('blocks-tab', this)">Content Blocks</button>
                <button class="tab-btn" onclick="showTab('personalization-tab', this)">Personalization Tags</button>
                <button class="tab-btn" onclick="showTab('tag-tester-tab', this)">Tag Tester</button>
            </div>
            
            <div id="blocks-tab" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h2><?php echo $editBlock ? 'Edit' : 'Create'; ?> Content Block</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" id="content-block-form">
                            <input type="hidden" name="block_id" value="<?php echo $editBlock ? $editBlock['id'] : 0; ?>">
                            
                            <div class="form-group">
                                <label for="block_name">Block Name:</label>
                                <input type="text" id="block_name" name="block_name" value="<?php echo htmlspecialchars($editBlock['name'] ?? ''); ?>" required>
                                <small>Give your content block a descriptive name</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="block_description">Description:</label>
                                <textarea id="block_description" name="block_description" rows="2"><?php echo htmlspecialchars($editBlock['description'] ?? ''); ?></textarea>
                                <small>Optional description of what this block is used for</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="block_type">Block Type:</label>
                                <select id="block_type" name="block_type" onchange="showConditionalOptions(this.value)">
                                    <option value="static" <?php echo (isset($editBlock['type']) && $editBlock['type'] === 'static') ? 'selected' : ''; ?>>Static Content</option>
                                    <option value="dynamic" <?php echo (isset($editBlock['type']) && $editBlock['type'] === 'dynamic') ? 'selected' : ''; ?>>Dynamic Content</option>
                                    <option value="conditional" <?php echo (isset($editBlock['type']) && $editBlock['type'] === 'conditional') ? 'selected' : ''; ?>>Conditional Content</option>
                                </select>
                            </div>
                            
                            <div id="conditional-options" style="<?php echo (isset($editBlock['type']) && $editBlock['type'] === 'conditional') ? '' : 'display: none;'; ?>">
                                <div class="conditional-group">
                                    <h3>Condition Rules</h3>
                                    <p>Define when this content should be shown:</p>
                                    
                                    <div class="conditional-rule">
                                        <select name="condition_field">
                                            <option value="country">Country</option>
                                            <option value="tag">Has Tag</option>
                                            <option value="subscription_date">Subscription Date</option>
                                            <option value="open_rate">Open Rate</option>
                                        </select>
                                        
                                        <select name="condition_operator">
                                            <option value="equals">Equals</option>
                                            <option value="not_equals">Not Equals</option>
                                            <option value="contains">Contains</option>
                                            <option value="greater_than">Greater Than</option>
                                            <option value="less_than">Less Than</option>
                                        </select>
                                        
                                        <input type="text" name="condition_value" placeholder="Value">
                                    </div>
                                    
                                    <textarea name="block_conditions" style="display: none;"><?php echo htmlspecialchars($editBlock['conditions'] ?? ''); ?></textarea>
                                    
                                    <div class="conditional-actions">
                                        <button type="button" class="btn btn-sm" onclick="addConditionRule()">
                                            <i class="fas fa-plus"></i> Add Rule
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="block_content">Content:</label>
                                <div class="personalization-tags">
                                    <?php foreach ($personalizationTags as $tag): ?>
                                        <span class="tag-item" onclick="insertTag('{{<?php echo $tag['tag_name']; ?>}}')">
                                            {{<?php echo $tag['tag_name']; ?>}}
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <textarea id="block_content" name="block_content"><?php echo htmlspecialchars($editBlock['content'] ?? ''); ?></textarea>
                                <small>Enter the content for this block. You can use personalization tags shown above.</small>
                            </div>
                            
                            <div class="form-actions">
                                <?php if ($editBlock): ?>
                                    <a href="content_blocks.php" class="btn btn-outline">Cancel</a>
                                <?php endif; ?>
                                <button type="submit" name="save_block" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $editBlock ? 'Update' : 'Save'; ?> Block
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($blocks)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Your Content Blocks</h2>
                    </div>
                    <div class="card-body">
                        <div class="block-list">
                            <?php foreach ($blocks as $block): ?>
                                <div class="block-item">
                                    <div class="block-info">
                                        <h3 class="block-name"><?php echo htmlspecialchars($block['name']); ?></h3>
                                        <p class="block-description"><?php echo htmlspecialchars($block['description'] ?? ''); ?></p>
                                        <span class="block-type <?php echo $block['type']; ?>">
                                            <?php echo ucfirst($block['type']); ?>
                                        </span>
                                    </div>
                                    <div class="block-actions">
                                        <a href="content_blocks.php?edit=<?php echo $block['id']; ?>" class="block-action edit" title="Edit block">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this block?');">
                                            <input type="hidden" name="block_id" value="<?php echo $block['id']; ?>">
                                            <button type="submit" name="delete_block" class="block-action delete" title="Delete block">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="personalization-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Personalization Tags</h2>
                    </div>
                    <div class="card-body">
                        <p>Use these tags in your newsletters and content blocks to personalize content for each subscriber.</p>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Tag</th>
                                    <th>Description</th>
                                    <th>Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($personalizationTags as $tag): ?>
                                <tr>
                                    <td><code>{{<?php echo htmlspecialchars($tag['tag_name']); ?>}}</code></td>
                                    <td><?php echo htmlspecialchars($tag['description']); ?></td>
                                    <td><?php echo htmlspecialchars($tag['example']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <!-- Default tags if none are in the database -->
                                <?php if (empty($personalizationTags)): ?>
                                <tr>
                                    <td><code>{{email}}</code></td>
                                    <td>Subscriber's email address</td>
                                    <td>subscriber@example.com</td>
                                </tr>
                                <tr>
                                    <td><code>{{first_name}}</code></td>
                                    <td>Subscriber's first name</td>
                                    <td>John</td>
                                </tr>
                                <tr>
                                    <td><code>{{last_name}}</code></td>
                                    <td>Subscriber's last name</td>
                                    <td>Doe</td>
                                </tr>
                                <tr>
                                    <td><code>{{full_name}}</code></td>
                                    <td>Subscriber's full name</td>
                                    <td>John Doe</td>
                                </tr>
                                <tr>
                                    <td><code>{{subscription_date}}</code></td>
                                    <td>Date when subscriber joined</td>
                                    <td>January 15, 2023</td>
                                </tr>
                                <tr>
                                    <td><code>{{current_date}}</code></td>
                                    <td>Today's date</td>
                                    <td>April 29, 2025</td>
                                </tr>
                                <tr>
                                    <td><code>{{unsubscribe_link}}</code></td>
                                    <td>Link to unsubscribe</td>
                                    <td>https://example.com/unsubscribe?token=abc123</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if ($isAdmin): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Advanced Personalization</h2>
                    </div>
                    <div class="card-body">
                        <p>Create complex personalization with conditional logic:</p>
                        
                        <div class="code-block">
                            <pre>{if tag="premium"}
  Premium content here
{else}
  Standard content here
{/if}</pre>
                        </div>
                        
                        <p>Compare values:</p>
                        
                        <div class="code-block">
                            <pre>{if subscription_months > 6}
  Thanks for being a long-term subscriber!
{/if}</pre>
                        </div>
                        
                        <p>Add fallback values:</p>
                        
                        <div class="code-block">
                            <pre>Hello {{first_name|there}}</pre>
                        </div>
                        
                        <p>This will display "Hello John" if first_name is available, or "Hello there" if it's not.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="tag-tester-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Personalization Tag Tester</h2>
                    </div>
                    <div class="card-body">
                        <p>Test how your personalization tags will look with different subscriber data.</p>
                        
                        <div class="form-group">
                            <label for="test-email">Subscriber Email:</label>
                            <input type="email" id="test-email" placeholder="Enter an email address">
                            <small>Enter a subscriber's email to test with their actual data</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="test-content">Content with Tags:</label>
                            <textarea id="test-content" rows="5">Hello {{first_name}},

Thank you for subscribing to our newsletter on {{subscription_date}}.

Your email address is {{email}}.</textarea>
                        </div>
                        
                        <button type="button" id="preview-btn" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        
                        <div class="tag-preview" id="tag-preview-result" style="display: none;">
                            <h3>Preview Result:</h3>
                            <div id="preview-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#block_content',
            height: 400,
            menubar: false,
            plugins: 'lists link image code table',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save(); // Save content back to textarea
                });
            }
        });
        
        // Show/hide conditional options
        function showConditionalOptions(type) {
            const conditionalOptions = document.getElementById('conditional-options');
            if (type === 'conditional') {
                conditionalOptions.style.display = 'block';
            } else {
                conditionalOptions.style.display = 'none';
            }
        }
        
        // Add condition rule
        function addConditionRule() {
            const rulesContainer = document.querySelector('.conditional-group');
            const newRule = document.createElement('div');
            newRule.className = 'conditional-rule';
            newRule.innerHTML = `
                <select name="condition_field">
                    <option value="country">Country</option>
                    <option value="tag">Has Tag</option>
                    <option value="subscription_date">Subscription Date</option>
                    <option value="open_rate">Open Rate</option>
                </select>
                
                <select name="condition_operator">
                    <option value="equals">Equals</option>
                    <option value="not_equals">Not Equals</option>
                    <option value="contains">Contains</option>
                    <option value="greater_than">Greater Than</option>
                    <option value="less_than">Less Than</option>
                </select>
                
                <input type="text" name="condition_value" placeholder="Value">
                <button type="button" class="btn btn-sm btn-outline" onclick="removeConditionRule(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            rulesContainer.insertBefore(newRule, document.querySelector('.conditional-actions'));
            updateConditionsJson();
        }
        
        // Remove condition rule
        function removeConditionRule(button) {
            const rule = button.parentNode;
            rule.parentNode.removeChild(rule);
            updateConditionsJson();
        }
        
        // Update conditions JSON
        function updateConditionsJson() {
            const rules = document.querySelectorAll('.conditional-rule');
            const conditions = [];
            
            rules.forEach(rule => {
                const field = rule.querySelector('[name="condition_field"]').value;
                const operator = rule.querySelector('[name="condition_operator"]').value;
                const value = rule.querySelector('[name="condition_value"]').value;
                
                if (field && operator && value) {
                    conditions.push({
                        field: field,
                        operator: operator,
                        value: value
                    });
                }
            });
            
            document.querySelector('[name="block_conditions"]').value = JSON.stringify(conditions);
        }
        
        // Insert tag into content
        function insertTag(tag) {
            if (tinymce.activeEditor) {
                tinymce.activeEditor.execCommand('mceInsertContent', false, tag);
            }
        }
        
        // Preview tag replacement
        document.getElementById('preview-btn').addEventListener('click', function() {
            const email = document.getElementById('test-email').value;
            const content = document.getElementById('test-content').value;
            
            // Show the preview area
            document.getElementById('tag-preview-result').style.display = 'block';
            
            // Simple preview with default values if no email is provided
            let previewContent = content;
            
            if (!email) {
                // Replace with sample data
                previewContent = previewContent
                    .replace(/{{first_name}}/g, 'John')
                    .replace(/{{last_name}}/g, 'Doe')
                    .replace(/{{full_name}}/g, 'John Doe')
                    .replace(/{{email}}/g, 'example@email.com')
                    .replace(/{{subscription_date}}/g, 'January 1, 2023')
                    .replace(/{{current_date}}/g, new Date().toLocaleDateString('en-US', {
                        month: 'long', 
                        day: 'numeric', 
                        year: 'numeric'
                    }))
                    .replace(/{{unsubscribe_link}}/g, 'https://example.com/unsubscribe');
                
                document.getElementById('preview-content').innerHTML = previewContent;
            } else {
                // Make AJAX request to get actual subscriber data
                fetch('ajax_preview_tags.php?email=' + encodeURIComponent(email) + '&content=' + encodeURIComponent(content))
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('preview-content').innerHTML = data;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('preview-content').innerHTML = 'Error processing preview';
                    });
            }
        });
        
        // Show selected tab
        function showTab(tabId, button) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).style.display = 'block';
            
            // Add active class to clicked button
            button.classList.add('active');
        }
        
        // Mobile menu toggle
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