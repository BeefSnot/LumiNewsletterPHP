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

// Debugging: Log the current user's role
if (!isset($_SESSION['role'])) {
    error_log('User role is not set in session.');
    die('User role is not set in session.');
}

error_log('Current user role: ' . $_SESSION['role']);

// Allow access only to admins
if ($_SESSION['role'] !== 'admin') {
    error_log('Unauthorized access attempt by user with role: ' . $_SESSION['role']);
    header('Location: unauthorized.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        error_log('Prepare failed: ' . htmlspecialchars($db->error));
        die('Prepare failed: ' . htmlspecialchars($db->error));
    }
    $stmt->bind_param("ssss", $username, $email, $password, $role);
    if ($stmt->execute() === false) {
        error_log('Execute failed: ' . htmlspecialchars($stmt->error));
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }
    $stmt->close();

    $message = 'User created successfully';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User | LumiNewsletter</title>
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
                    <li><a href="index.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="create_user.php" class="nav-item"><i class="fas fa-user-plus"></i> Create User</a></li>
                    <li><a href="manage_users.php" class="nav-item"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                    <li><a href="send_newsletter.php" class="nav-item"><i class="fas fa-envelope"></i> Send Newsletter</a></li>
                    <li><a href="manage_newsletters.php" class="nav-item"><i class="fas fa-newspaper"></i> Manage Newsletters</a></li>
                    <li><a href="analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>Version <?php echo htmlspecialchars($currentVersion); ?></p>
            </div>
        </aside>

        <main class="content">
            <header class="top-header">
                <div class="header-left">
                    <h1>Create User</h1>
                </div>
            </header>
            
            <div class="card">
                <div class="card-header">
                    <h2>Create a New User</h2>
                </div>
                <div class="card-body">
                    <?php if (isset($message)): ?>
                        <p><?php echo $message; ?></p>
                    <?php endif; ?>
                    <form method="post">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                        <label for="role">Role:</label>
                        <select id="role" name="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <button type="submit">Create User</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
    </footer>
</body>
</html>