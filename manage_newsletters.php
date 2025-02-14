<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Fetch all newsletters
$query = "
    SELECT n.id, n.subject, n.body, n.sent_at, u.username AS sender, GROUP_CONCAT(g.name SEPARATOR ', ') AS groups
    FROM newsletters n
    JOIN users u ON n.sender_id = u.id
    LEFT JOIN newsletter_groups ng ON n.id = ng.newsletter_id
    LEFT JOIN groups g ON ng.group_id = g.id
    GROUP BY n.id
    ORDER BY n.sent_at DESC
";

$newslettersResult = $db->query($query);

if ($newslettersResult === false) {
    die('Query failed: ' . htmlspecialchars($db->error));
}

$newsletters = [];
while ($row = $newslettersResult->fetch_assoc()) {
    $newsletters[] = $row;
}

// Debugging: Log the query and the fetched newsletters
error_log('Query: ' . $query);
error_log('Fetched newsletters: ' . print_r($newsletters, true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Newsletters</title>
    <link rel="stylesheet" href="assets/css/newsletter.css">
</head>
<body>
    <header>
        <h1>Manage Newsletters</h1>
        <nav>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="create_theme.php">Create Theme</a></li>
                <li><a href="send_newsletter.php">Send Newsletter</a></li>
                <li><a href="manage_newsletters.php">Manage Newsletters</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h2>Previous Newsletters</h2>
        <?php if (empty($newsletters)): ?>
            <p>No newsletters found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Sender</th>
                        <th>Sent At</th>
                        <th>Groups</th>
                        <th>Content</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($newsletters as $newsletter): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($newsletter['subject'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($newsletter['sender'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($newsletter['sent_at'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($newsletter['groups'] ?? ''); ?></td>
                            <td>
                                <button onclick="toggleContent(<?php echo $newsletter['id']; ?>)">View Content</button>
                                <div id="content-<?php echo $newsletter['id']; ?>" style="display: none;">
                                    <?php echo $newsletter['body'] ?? ''; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <script>
        function toggleContent(id) {
            var contentDiv = document.getElementById('content-' + id);
            if (contentDiv.style.display === 'none') {
                contentDiv.style.display = 'block';
            } else {
                contentDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>