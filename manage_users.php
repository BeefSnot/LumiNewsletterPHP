<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fix for undefined $currentVersion variable
$currentVersion = require 'version.php';

$message = '';

// Handle user deletion
if (isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    // Don't allow deleting yourself
    if ($id == $_SESSION['user_id']) {
        $message = "You cannot delete your own account";
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = "User deleted successfully";
        } else {
            $message = "Error deleting user: " . $db->error;
        }
        $stmt->close();
    }
}

// Handle user creation/update
if (isset($_POST['save_user'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    // Check if username exists (for new users)
    if ($id === 0) {
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param('s', $username);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            $message = "Username already exists";
        }
        $checkStmt->close();
    }
    
    if (empty($message)) {
        if ($id === 0) {
            // This is a new user
            $password = $_POST['password'];
            if (empty($password)) {
                $message = "Password is required for new users";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('ssss', $username, $email, $hashed_password, $role);
                if ($stmt->execute()) {
                    $message = "User created successfully";
                } else {
                    $message = "Error creating user: " . $db->error;
                }
                $stmt->close();
            }
        } else {
            // This is an existing user
            if (!empty($_POST['password'])) {
                // Update with password
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
                $stmt->bind_param('ssssi', $username, $email, $hashed_password, $role, $id);
            } else {
                // Update without password
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param('sssi', $username, $email, $role, $id);
            }
            
            if ($stmt->execute()) {
                $message = "User updated successfully";
            } else {
                $message = "Error updating user: " . $db->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all users
$result = $db->query("SELECT id, username, email, role FROM users ORDER BY id ASC");
if ($result === false) {
    die('Query failed: ' . htmlspecialchars($db->error));
}

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | LumiNewsletter</title>
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
                    <h1>Manage Users</h1>
                </div>
                <div class="header-right">
                    <button onclick="newUser()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New User
                    </button>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-plus"></i> <span id="form-title">Add New User</span></h2>
                </div>
                <div class="card-body">
                    <form id="user-form" method="post">
                        <input type="hidden" id="id" name="id" value="">
                        
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label id="password-label" for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role:</label>
                            <select id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="editor">Editor</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="save_user" class="btn btn-primary">Save User</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> All Users</h2>
                </div>
                <div class="card-body">
                    <?php if (count($users) === 0): ?>
                        <p>No users found.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                                        <td class="actions">
                                            <button onclick="editUser(
                                                '<?php echo $user['id']; ?>', 
                                                '<?php echo htmlspecialchars($user['username']); ?>', 
                                                '<?php echo htmlspecialchars($user['email']); ?>',
                                                '<?php echo htmlspecialchars($user['role']); ?>'
                                            )" class="btn btn-sm">Edit</button>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display:inline;">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="delete" class="btn btn-sm">Delete</button>
                                                </form>
                                            <?php endif; ?>
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
</body>
</html>