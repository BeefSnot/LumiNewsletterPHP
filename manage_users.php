<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

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
    <title>Manage Users</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
        .user-form {
            background-color: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .user-form input, .user-form select {
            margin-bottom: 10px;
        }
        .actions form {
            display: inline;
        }
        .actions button {
            margin-right: 5px;
        }
    </style>
    <script>
        function editUser(id, username, email, role) {
            document.getElementById('form-title').innerText = 'Edit User';
            document.getElementById('id').value = id;
            document.getElementById('username').value = username;
            document.getElementById('email').value = email;
            document.getElementById('role').value = role;
            document.getElementById('password-label').innerText = 'Password (leave blank to keep current)';
            document.getElementById('password').required = false;
            window.scrollTo(0, 0);
        }
        
        function newUser() {
            document.getElementById('form-title').innerText = 'Add New User';
            document.getElementById('user-form').reset();
            document.getElementById('id').value = '';
            document.getElementById('password-label').innerText = 'Password';
            document.getElementById('password').required = true;
        }
    </script>
</head>
<body>
    <header>
        <h1>Manage Users</h1>
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
        <h2>Manage Users</h2>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <button onclick="newUser()">Add New User</button>
        
        <div class="user-form">
            <h3 id="form-title">Add New User</h3>
            <form id="user-form" method="post">
                <input type="hidden" id="id" name="id" value="">
                
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                
                <label id="password-label" for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="admin">Admin</option>
                    <option value="editor">Editor</option>
                </select>
                
                <button type="submit" name="save_user">Save User</button>
            </form>
        </div>
        
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
                                )">Edit</button>
                                
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete">Delete</button>
                                    </form>
                                <?php endif; ?>
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