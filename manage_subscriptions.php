<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'editor')) {
    header('Location: login.php');
    exit();
}

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

$message = '';
$selectedGroup = isset($_GET['group']) ? (int)$_GET['group'] : 0;

// Handle deletion
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $stmt = $db->prepare("DELETE FROM group_subscriptions WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $message = "Subscription deleted successfully";
    } else {
        $message = "Error deleting subscription: " . $db->error;
    }
    $stmt->close();
}

// Handle importing subscribers from CSV
if (isset($_POST['import_subscribers']) && isset($_FILES['import_file'])) {
    $defaultGroupId = (int)$_POST['default_group'];
    $skipDuplicates = isset($_POST['skip_duplicates']);
    $importFile = $_FILES['import_file'];
    
    $successCount = 0;
    $errorCount = 0;
    $duplicateCount = 0;
    
    // Check file upload
    if ($importFile['error'] == 0 && $importFile['size'] > 0) {
        // Check file type (simple check, can be improved)
        $fileExt = strtolower(pathinfo($importFile['name'], PATHINFO_EXTENSION));
        if ($fileExt == 'csv') {
            // Open the file
            $handle = fopen($importFile['tmp_name'], 'r');
            
            if ($handle !== FALSE) {
                // Read the header row
                $header = fgetcsv($handle);
                
                // Convert header to lowercase for case-insensitive matching
                $header = array_map('strtolower', $header);
                
                // Find column indexes
                $emailIndex = array_search('email', $header);
                $nameIndex = array_search('name', $header);
                $groupIndex = array_search('group_id', $header);
                
                // Prepare statements
                $checkStmt = $db->prepare("SELECT id FROM group_subscriptions WHERE email = ? AND group_id = ?");
                $insertStmt = $db->prepare("INSERT INTO group_subscriptions (email, name, group_id) VALUES (?, ?, ?)");
                
                // Process each row
                while (($row = fgetcsv($handle)) !== FALSE) {
                    // Get data from row
                    $email = filter_var($row[$emailIndex] ?? '', FILTER_SANITIZE_EMAIL);
                    $name = $row[$nameIndex] ?? '';
                    $groupId = (int)($row[$groupIndex] ?? $defaultGroupId);
                    
                    // Validate email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Validate group ID
                    if ($groupId <= 0) {
                        $groupId = $defaultGroupId;
                    }
                    
                    // Check for duplicates if requested
                    if ($skipDuplicates) {
                        $checkStmt->bind_param("si", $email, $groupId);
                        $checkStmt->execute();
                        $result = $checkStmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $duplicateCount++;
                            continue;
                        }
                    }
                    
                    // Insert the subscriber
                    $insertStmt->bind_param("ssi", $email, $name, $groupId);
                    if ($insertStmt->execute()) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }
                
                fclose($handle);
                $message = "Import complete: $successCount subscribers added";
                if ($duplicateCount > 0) {
                    $message .= ", $duplicateCount duplicates skipped";
                }
                if ($errorCount > 0) {
                    $message .= ", $errorCount errors";
                }
            } else {
                $message = "Could not open the uploaded file";
            }
        } else {
            $message = "Please upload a CSV file";
        }
    } else {
        $message = "Error uploading file: " . $importFile['error'];
    }
}

// Build query based on selected group
$query = "
    SELECT gs.id, gs.email, g.name as group_name, g.id as group_id
    FROM group_subscriptions gs
    JOIN groups g ON gs.group_id = g.id
";
if ($selectedGroup > 0) {
    $query .= " WHERE g.id = $selectedGroup";
}
$query .= " ORDER BY gs.email ASC";

$result = $db->query($query);
if ($result === false) {
    die('Query failed: ' . htmlspecialchars($db->error));
}

// Fetch all subscriptions
$subscriptions = [];
while ($row = $result->fetch_assoc()) {
    $subscriptions[] = $row;
}

// Get all groups for filter dropdown
$groupsResult = $db->query("SELECT id, name FROM groups ORDER BY name ASC");
$groups = [];
while ($row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscribers | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Additional stylesheets remain the same -->
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
                    <h1>Manage Subscriptions</h1>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-filter"></i> Filter Subscriptions</h2>
                </div>
                <div class="card-body">
                    <form method="get">
                        <div class="form-group">
                            <label for="group">By Group:</label>
                            <select id="group" name="group" onchange="this.form.submit()">
                                <option value="0">All Groups</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo ($selectedGroup == $group['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-upload"></i> Import Subscribers</h2>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="import_file">Upload CSV File:</label>
                            <input type="file" id="import_file" name="import_file" accept=".csv" required>
                            <small class="form-text">CSV format should have headers: email,name,group_id</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="default_group">Default Group (if not specified in CSV):</label>
                            <select id="default_group" name="default_group" required>
                                <option value="">-- Select Group --</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="skip_duplicates" checked>
                                Skip duplicate emails (recommended)
                            </label>
                        </div>
                        
                        <button type="submit" name="import_subscribers" class="btn btn-primary">
                            <i class="fas fa-file-import"></i> Import Subscribers
                        </button>
                    </form>
                    <p class="mt-3">
                        <a href="download_sample_csv.php" class="btn btn-sm">
                            <i class="fas fa-download"></i> Download Sample CSV
                        </a>
                    </p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Subscription List</h2>
                </div>
                <div class="card-body">
                    <?php if (count($subscriptions) === 0): ?>
                        <p>No subscriptions found.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Group</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subscription['id']); ?></td>
                                        <td><?php echo htmlspecialchars($subscription['email']); ?></td>
                                        <td><?php echo htmlspecialchars($subscription['group_name']); ?></td>
                                        <td class="actions">
                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this subscription?');">
                                                <input type="hidden" name="id" value="<?php echo $subscription['id']; ?>">
                                                <button type="submit" name="delete" class="btn btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
    
    <script src="assets/js/sidebar.js"></script>
    <!-- Other scripts remain the same -->
</body>
</html>