<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Allow both admin and editor users to access the media library
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'editor')) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';
$uploadDir = 'uploads/media/';

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_file'])) {
    $file = $_FILES['media_file'];
    
    // Basic validation
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] === 0) {
        if (in_array($file['type'], $allowedTypes)) {
            if ($file['size'] <= $maxSize) {
                // Generate unique filename
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $file['name']);
                $filepath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Get image dimensions if it's an image
                    $dimensions = null;
                    if (strpos($file['type'], 'image/') === 0) {
                        $imageInfo = getimagesize($filepath);
                        if ($imageInfo) {
                            $dimensions = $imageInfo[0] . 'x' . $imageInfo[1];
                        }
                    }
                    
                    // Save to database
                    $stmt = $db->prepare("
                        INSERT INTO media_library 
                        (file_name, file_path, file_type, uploaded_by, uploaded_at, 
                         filename, filepath, filetype, filesize, dimensions, created_at)
                        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    // Bind the same values to both old and new column names 
                    $stmt->bind_param(
                        "sssssssss", 
                        $filename, $filepath, $file['type'], $_SESSION['user_id'],  // Old column naming
                        $filename, $filepath, $file['type'], $file['size'], $dimensions  // New column naming
                    );
                    
                    // Execute the statement
                    if ($stmt->execute()) {
                        $message = "File uploaded successfully";
                        $messageType = 'success';
                    } else {
                        $message = "Error uploading file: " . $stmt->error;
                        $messageType = 'error';
                    }
                } else {
                    $message = "Failed to move uploaded file";
                    $messageType = 'error';
                }
            } else {
                $message = "File is too large (max 5MB)";
                $messageType = 'error';
            }
        } else {
            $message = "Unsupported file type";
            $messageType = 'error';
        }
    } else {
        $message = "Upload error: " . $file['error'];
        $messageType = 'error';
    }
}

// Handle file deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $media_id = (int)$_GET['delete'];
    
    // Get file info before deletion
    $stmt = $db->prepare("SELECT filepath FROM media_library WHERE id = ?");
    $stmt->bind_param('i', $media_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $media = $result->fetch_assoc();
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM media_library WHERE id = ?");
        $stmt->bind_param('i', $media_id);
        
        if ($stmt->execute()) {
            // Delete file if it exists
            if (file_exists($media['filepath'])) {
                unlink($media['filepath']);
            }
            
            $message = "File deleted successfully";
            $messageType = 'success';
        } else {
            $message = "Error deleting file: " . $db->error;
            $messageType = 'error';
        }
    } else {
        $message = "File not found";
        $messageType = 'error';
    }
}

// Handle selection via iframe communication
$selectMode = isset($_GET['select']) && $_GET['select'] === '1';

// Get all media files
$media = [];
try {
    $mediaResult = $db->query("
        SELECT m.*, u.username as uploader_name 
        FROM media_library m
        LEFT JOIN users u ON m.uploaded_by = u.id
        ORDER BY m.created_at DESC
    ");
    if (!$mediaResult) {
        throw new Exception("Failed to fetch media files: " . $db->error);
    }

    while ($row = $mediaResult->fetch_assoc()) {
        $media[] = $row;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $selectMode ? 'Select Image' : 'Media Library'; ?> | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <?php if (!$selectMode): ?>
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .media-item {
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        
        .media-preview {
            height: 150px;
            background-color: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
        }
        
        .media-preview img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .media-preview .document-icon {
            font-size: 48px;
            color: #ddd;
        }
        
        .media-info {
            padding: 10px;
            font-size: 13px;
        }
        
        .media-name {
            word-break: break-all;
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .media-meta {
            color: #888;
            font-size: 12px;
            display: flex;
            flex-direction: column;
        }
        
        .media-actions {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 5px;
        }
        
        .media-actions .btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            color: #333;
            padding: 0;
        }
        
        .upload-form {
            background: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .select-mode .media-preview {
            cursor: pointer;
        }
        
        .select-mode .media-preview:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body class="<?php echo $selectMode ? 'select-mode' : ''; ?>">
    <?php if (!$selectMode): ?>
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
                    <h1>Media Library</h1>
                </div>
            </header>
    <?php else: ?>
    <div>
        <header style="padding: 15px; border-bottom: 1px solid #eee; margin-bottom: 15px;">
            <h2>Select an Image</h2>
        </header>
    <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="notification <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="upload-form">
                <h3><i class="fas fa-cloud-upload-alt"></i> Upload New Media</h3>
                <form method="post" enctype="multipart/form-data" action="">
                    <div class="form-group">
                        <label for="media_file">Select File</label>
                        <input type="file" id="media_file" name="media_file" required>
                        <small>Supported formats: JPG, PNG, GIF, SVG (Max: 5MB)</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
            
            <h3 style="margin-top: 20px;"><i class="fas fa-photo-video"></i> Media Library</h3>
            <?php if (empty($media)): ?>
                <div class="empty-state">
                    <i class="fas fa-images empty-icon"></i>
                    <h2>No Media Files Yet</h2>
                    <p>Upload your first media file to get started.</p>
                </div>
            <?php else: ?>
                <div class="media-grid">
                    <?php foreach ($media as $item): ?>
                        <div class="media-item">
                            <div class="media-preview" <?php if ($selectMode): ?>onclick="selectMedia('<?php echo htmlspecialchars($item['filepath']); ?>')"<?php endif; ?>>
                                <?php if (strpos($item['filetype'], 'image/') === 0): ?>
                                    <img src="<?php echo htmlspecialchars($item['filepath']); ?>" alt="<?php echo htmlspecialchars($item['filename']); ?>">
                                <?php else: ?>
                                    <div class="document-icon"><i class="fas fa-file"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="media-info">
                                <div class="media-name"><?php echo htmlspecialchars($item['filename']); ?></div>
                                <div class="media-meta">
                                    <span><?php echo formatFileSize($item['filesize']); ?></span>
                                    <?php if ($item['dimensions']): ?>
                                        <span><?php echo htmlspecialchars($item['dimensions']); ?></span>
                                    <?php endif; ?>
                                    <span>Uploaded: <?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
                                </div>
                            </div>
                            <?php if (!$selectMode): ?>
                            <div class="media-actions">
                                <a href="<?php echo htmlspecialchars($item['filepath']); ?>" class="btn btn-sm" target="_blank" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="media_library.php?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this file?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
    <?php if (!$selectMode): ?>
        </main>
    </div>
    
    <script src="assets/js/sidebar.js"></script>
    <?php else: ?>
    </div>
    <script>
        // Function to send selected media back to the parent window
        function selectMedia(url) {
            window.parent.postMessage({
                type: 'media-selected',
                url: url
            }, '*');
            
            // Close the window after selection
            setTimeout(function() {
                window.close();
            }, 300);
        }
    </script>
    <?php endif; ?>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>