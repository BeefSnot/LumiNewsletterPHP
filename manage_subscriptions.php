<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

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
    <title>Manage Subscriptions</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        .actions form {
            display: inline;
        }
    </style>
</head>
<body>
    <header>
        <h1>Manage Subscriptions</h1>
        <nav>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="admin.php">Admin Area</a></li>
                <li><a href="create_theme.php">Create Theme</a></li>
                <li><a href="send_newsletter.php">Send Newsletter</a></li>
                <li><a href="manage_newsletters.php">Manage Newsletters</a></li>
                <li><a href="manage_subscriptions.php">Manage Subscriptions</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="manage_smtp.php">SMTP Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h2>Manage Newsletter Subscriptions</h2>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="filter-section">
            <h3>Filter Subscriptions</h3>
            <form method="get">
                <label for="group">By Group:</label>
                <select id="group" name="group" onchange="this.form.submit()">
                    <option value="0">All Groups</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>" <?php echo ($selectedGroup == $group['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
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
                                    <button type="submit" name="delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>