<?php
session_start();
require_once 'includes/db.php';
require 'vendor/autoload.php'; // Include the Composer autoload file

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['token']) && isset($_POST['password'])) {
        $token = $_POST['token'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

        // Verify the token
        $stmt = $db->prepare("SELECT user_id FROM password_resets WHERE token = ?");
        if ($stmt === false) {
            $error = 'Prepare failed: ' . htmlspecialchars($db->error);
        } else {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($user_id);
            $stmt->fetch();

            if ($stmt->num_rows > 0) {
                // Update the user's password
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt === false) {
                    $error = 'Prepare failed: ' . htmlspecialchars($db->error);
                } else {
                    $stmt->bind_param("si", $password, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    // Delete the token
                    $stmt = $db->prepare("DELETE FROM password_resets WHERE token = ?");
                    if ($stmt === false) {
                        $error = 'Prepare failed: ' . htmlspecialchars($db->error);
                    } else {
                        $stmt->bind_param("s", $token);
                        $stmt->execute();
                        $stmt->close();

                        $success = 'Password has been reset successfully. You can now <a href="login.php">login</a>.';
                    }
                }
            } else {
                $error = 'Invalid or expired token.';
            }
            $stmt->close();
        }
    }
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
} else {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main>
        <h2>Reset Password</h2>
        <?php if (!empty($error)): ?>
            <p><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p><?php echo $success; ?></p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required>
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>