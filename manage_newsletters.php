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
    SELECT n.id, n.subject, n.content, COALESCE(n.created_at, n.send_date) AS sent_at, 
           u.username AS sender, 
           GROUP_CONCAT(g.name SEPARATOR ', ') AS `groups`
    FROM newsletters n
    JOIN users u ON n.creator_id = u.id
    LEFT JOIN newsletter_groups ng ON n.id = ng.newsletter_id
    LEFT JOIN `groups` g ON ng.group_id = g.id
    GROUP BY n.id
    ORDER BY COALESCE(n.created_at, n.send_date) DESC
";

$newslettersResult = $db->query($query);

if ($newslettersResult === false) {
    die('Query failed: ' . htmlspecialchars($db->error));
}

$newsletters = [];
while ($row = $newslettersResult->fetch_assoc()) {
    $newsletters[] = $row;
}

// Get current user role
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Debugging: Log the query and the fetched newsletters
error_log('Query: ' . $query);
error_log('Fetched newsletters: ' . print_r($newsletters, true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Newsletters | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Other head content remains the same -->
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
                    <h1>Manage Newsletters</h1>
                </div>
                <div class="header-right">
                    <a href="send_newsletter.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create New Newsletter
                    </a>
                </div>
            </header>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Previous Newsletters</h2>
                </div>
                <div class="card-body">
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
                                            <button onclick="toggleContent(<?php echo $newsletter['id']; ?>)" class="btn btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <div id="content-<?php echo $newsletter['id']; ?>" style="display: none; margin-top: 10px; padding: 10px; background-color: #f9f9f9; border-radius: 4px;">
                                                <?php echo $newsletter['content'] ?? ''; ?>
                                            </div>
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
    
    <!-- Other scripts remain the same, but remove any existing mobile menu toggle script -->
</body>
</html>