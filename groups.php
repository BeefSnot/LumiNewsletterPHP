<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['group_name'])) {
        $group_name = $_POST['group_name'];
        $stmt = $db->prepare("SELECT id FROM groups WHERE name = ?");
        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($db->error));
        }
        $stmt->bind_param("s", $group_name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = 'Group already exists';
        } else {
            $stmt = $db->prepare("INSERT INTO groups (name) VALUES (?)");
            if ($stmt === false) {
                die('Prepare failed: ' . htmlspecialchars($db->error));
            }
            $stmt->bind_param("s", $group_name);
            if ($stmt->execute() === false) {
                die('Execute failed: ' . htmlspecialchars($stmt->error));
            }
            $stmt->close();
            $message = 'Group created successfully';
        }
    } elseif (isset($_POST['delete_group_id'])) {
        $group_id = $_POST['delete_group_id'];
        $stmt = $db->prepare("DELETE FROM groups WHERE id = ?");
        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($db->error));
        }
        $stmt->bind_param("i", $group_id);
        if ($stmt->execute() === false) {
            die('Execute failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        $message = 'Group deleted successfully';
    }
}

// Fetch available groups
$groupsResult = $db->query("SELECT id, name FROM groups");
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
    <title>Page Title | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-paper-plane"></i>
                    <h2>LumiNews</h2>
                </div>
            </div>
            <nav class="main-nav">
                <ul>
                    <!-- Navigation items with icons -->
                    <li><a href="index.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a></li>
                    <!-- More nav items... -->
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Page Title</h1>
                </div>
            </header>
            
            <!-- Main content in cards -->
            <div class="card">
                <div class="card-header">
                    <h2>Section Title</h2>
                </div>
                <div class="card-body">
                    <div class="section-title text-center">
                        <h6 class="text-uppercase text-muted">Manage Groups</h6>
                        <h4 class="font-weight-bold">Create or Delete Groups<span class="main">.</span></h4>
                    </div>
                    <div class="status-details text-center dark-background p-4 rounded">
                        <?php if (isset($message)): ?>
                            <p><?php echo $message; ?></p>
                        <?php endif; ?>
                        <form method="post">
                            <label for="group_name">Group Name:</label>
                            <input type="text" id="group_name" name="group_name" required>
                            <button type="submit" class="btn btn-primary mt-4">Create Group</button>
                        </form>
                    </div>
                    <div class="section-title text-center mt-5">
                        <h6 class="text-uppercase text-muted">Existing Groups</h6>
                        <h4 class="font-weight-bold">Delete Groups<span class="main">.</span></h4>
                    </div>
                    <div class="status-details text-center dark-background p-4 rounded">
                        <ul>
                            <?php foreach ($groups as $group): ?>
                                <li>
                                    <?php echo $group['name']; ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="delete_group_id" value="<?php echo $group['id']; ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
</body>
</html>