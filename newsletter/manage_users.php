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
    $userId = $_POST['user_id'];
    $role = $_POST['role'];

    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
    if ($stmt === false) {
        error_log('Prepare failed: ' . htmlspecialchars($db->error));
        die('Prepare failed: ' . htmlspecialchars($db->error));
    }
    $stmt->bind_param("si", $role, $userId);
    if ($stmt->execute() === false) {
        error_log('Execute failed: ' . htmlspecialchars($stmt->error));
        die('Execute failed: ' . htmlspecialchars($stmt->error));
    }
    $stmt->close();
}

$result = $db->query("SELECT id, username, email, role FROM users");
if ($result === false) {
    error_log('Query failed: ' . htmlspecialchars($db->error));
    die('Query failed: ' . htmlspecialchars($db->error));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="assets/css/newsletter.css">
</head>
<body>
    <header>
        <h1>Manage Users</h1>
        <nav>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="create_user.php">Create User</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
                <li><a href="send_newsletter.php">Send Newsletter</a></li>
                <li><a href="manage_newsletters.php">Manage Newsletters</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h2>Manage Users</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['username']; ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td><?php echo $row['role']; ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                            <select name="role">
                                <option value="user" <?php if ($row['role'] == 'user') echo 'selected'; ?>>User</option>
                                <option value="admin" <?php if ($row['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>
</body>
</html>