<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin users can access email builder
if (!canAccessEmailBuilder()) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';
$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$template = null;

// Get available layouts and components
$layouts = [];
$layoutsResult = $db->query("SELECT * FROM template_layouts ORDER BY name ASC");
while ($layoutsResult && $row = $layoutsResult->fetch_assoc()) {
    $layouts[] = $row;
}

$components = [];
$componentsResult = $db->query("SELECT * FROM template_components ORDER BY category, name");
while ($componentsResult && $row = $componentsResult->fetch_assoc()) {
    $components[] = $row;
}

// Group components by category
$componentsByCategory = [];
foreach ($components as $component) {
    $category = $component['category'];
    if (!isset($componentsByCategory[$category])) {
        $componentsByCategory[$category] = [];
    }
    $componentsByCategory[$category][] = $component;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateName = htmlspecialchars($_POST['template_name']);
    $templateDescription = htmlspecialchars($_POST['template_description']);
    $templateContent = $_POST['template_content'];
    
    if (empty($templateName)) {
        $message = 'Template name is required';
        $messageType = 'error';
    } else {
        // Update or insert template
        if ($templateId > 0) {
            $stmt = $db->prepare("UPDATE email_templates SET name = ?, description = ?, content = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('sssi', $templateName, $templateDescription, $templateContent, $templateId);
            $action = 'updated';
        } else {
            $createdBy = $_SESSION['user_id'];
            $stmt = $db->prepare("INSERT INTO email_templates (name, description, content, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('sssi', $templateName, $templateDescription, $templateContent, $createdBy);
            $action = 'created';
        }
        
        if ($stmt->execute()) {
            // If new template, get the ID
            if ($templateId === 0) {
                $templateId = $db->insert_id;
            }
            
            $message = "Template $action successfully!";
            $messageType = 'success';
            
            // Redirect to edit the template with ID in URL
            header("Location: email_builder.php?id=$templateId&message=$message&type=$messageType");
            exit();
        } else {
            $message = "Error $action template: " . $db->error;
            $messageType = 'error';
        }
    }
}

// If editing, load template
if ($templateId > 0) {
    $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->bind_param('i', $templateId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $template = $result->fetch_assoc();
    } else {
        $message = "Template not found";
        $messageType = 'error';
        $templateId = 0;
    }
}

// Display any messages passed via query params
if (isset($_GET['message']) && empty($message)) {
    $message = $_GET['message'];
    $messageType = $_GET['type'] ?? 'info';
}

// Define the media library endpoint for image selection
$mediaLibraryEndpoint = 'media_library.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $templateId > 0 ? 'Edit' : 'Create'; ?> Email Template | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/email-builder.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <h1><?php echo $templateId > 0 ? 'Edit Template' : 'Create New Template'; ?></h1>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="email-builder-container">
                <div class="template-info">
                    <form id="template-form" method="post" action="email_builder.php<?php echo $templateId > 0 ? '?id=' . $templateId : ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="template_name">Template Name</label>
                                <input type="text" id="template_name" name="template_name" value="<?php echo $template ? htmlspecialchars($template['name']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="template_description">Description</label>
                                <input type="text" id="template_description" name="template_description" value="<?php echo $template ? htmlspecialchars($template['description']) : ''; ?>">
                            </div>
                        </div>
                        
                        <input type="hidden" id="template_content" name="template_content" value="<?php echo $template ? htmlspecialchars($template['content']) : ''; ?>">
                        
                        <div class="template-actions">
                            <a href="manage_templates.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Templates
                            </a>
                            <button type="button" id="preview-btn" class="btn">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <button type="button" id="responsive-preview-btn" class="btn">
                                <i class="fas fa-mobile-alt"></i> Mobile Preview
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Template
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="builder-interface">
                    <div class="components-panel">
                        <h3>Components</h3>
                        
                        <!-- Component Tabs -->
                        <div class="component-tabs">
                            <?php
                            $categories = array_keys($componentsByCategory);
                            foreach ($categories as $index => $category) {
                                $activeClass = $index === 0 ? 'active' : '';
                                echo "<button class='tab-btn $activeClass' data-category='$category'>" . ucfirst($category) . "</button>";
                            }
                            ?>
                        </div>
                        
                        <!-- Component Lists -->
                        <?php foreach ($componentsByCategory as $category => $categoryComponents): ?>
                            <div class="category-components" data-category="<?php echo $category; ?>" style="display: <?php echo $category === $categories[0] ? 'block' : 'none'; ?>">
                                <?php foreach ($categoryComponents as $component): ?>
                                    <div class="component-item" data-component='<?php echo json_encode(["id" => $component["id"], "html_content" => $component["html_content"]]); ?>'>
                                        <i class="fas <?php echo $component['icon']; ?>"></i>
                                        <span><?php echo htmlspecialchars($component['name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <h3>Layouts</h3>
                        <div class="layouts-container">
                            <?php foreach ($layouts as $layout): ?>
                                <div class="component-item layout-item" data-layout='<?php echo json_encode(["id" => $layout["id"], "html_structure" => $layout["html_structure"]]); ?>'>
                                    <i class="fas fa-columns"></i>
                                    <span><?php echo htmlspecialchars($layout['name']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="editor-container">
                        <div class="editor-toolbar">
                            <button id="delete-btn" class="btn btn-danger" disabled>
                                <i class="fas fa-trash"></i> Delete Element
                            </button>
                        </div>
                        
                        <div id="email-editor" class="email-editor"></div>
                    </div>
                    
                    <div class="properties-panel">
                        <h3>Properties</h3>
                        <div id="element-properties">
                            <p class="no-selection-message">Select an element to edit its properties</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview Modal -->
            <div id="preview-modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Template Preview</h2>
                    <iframe id="preview-frame" class="preview-frame"></iframe>
                </div>
            </div>
            
            <!-- Mobile Preview Modal -->
            <div id="mobile-preview-modal" class="modal">
                <div class="modal-content mobile-frame-container">
                    <span class="close">&times;</span>
                    <h2>Mobile Preview</h2>
                    <div class="mobile-device-frame">
                        <iframe id="mobile-preview-frame" class="mobile-preview-frame"></iframe>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/email-builder.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        // Function to convert RGB to Hex
        function rgbToHex(rgb) {
            // If rgb is already a hex color, just return it
            if (rgb.startsWith('#')) return rgb;
            
            // Extract RGB values
            let rgbArr = rgb.match(/\d+/g);
            if (!rgbArr || rgbArr.length < 3) return '#ffffff';
            
            // Convert to hex
            return '#' + ((1 << 24) + (parseInt(rgbArr[0]) << 16) + (parseInt(rgbArr[1]) << 8) + parseInt(rgbArr[2])).toString(16).slice(1);
        }
        
        // Function to open the media library
        function openMediaLibrary() {
            // Open media library in new window
            window.open('<?php echo $mediaLibraryEndpoint; ?>', 'mediaLibrary', 'width=800,height=600');
            
            // Handle selected image from media library
            window.addEventListener('message', function(event) {
                if (event.data.type === 'media-selected') {
                    const selectedImg = document.querySelector('.content-block.active');
                    if (selectedImg && selectedImg.nodeName === 'IMG') {
                        selectedImg.src = event.data.url;
                        document.getElementById('img-src').value = event.data.url;
                    }
                }
            });
        }
    </script>
</body>
</html>