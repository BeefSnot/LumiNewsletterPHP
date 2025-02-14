<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if db connection is established
    if ($db->connect_error) {
        $error = 'Database connection failed: ' . $db->connect_error;
    } else {
        // Prepare and execute the query to fetch user details
        $stmt = $db->prepare("SELECT id, password, role FROM users WHERE username = ?");
        if ($stmt === false) {
            $error = 'Prepare failed: ' . htmlspecialchars($db->error);
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($id, $hashed_password, $role);
            $stmt->fetch();

            // Check if user exists
            if ($stmt->num_rows > 0) {
                // Verify the password
                if (password_verify($password, $hashed_password)) {
                    // Set session variables
                    $_SESSION['user_id'] = $id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;

                    // Debugging: Log session variables
                    error_log('Session variables set: user_id=' . $_SESSION['user_id'] . ', username=' . $_SESSION['username'] . ', role=' . $_SESSION['role']);

                    // Redirect to the dashboard
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Invalid username or password';
                }
            } else {
                $error = 'Invalid username or password';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/newsletter.css">
</head>
<body>
    <main>
        <h2>Login</h2>
        <?php if (!empty($error)): ?>
            <p><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Login</button>
        </form>
    </main>
</body>
</html>