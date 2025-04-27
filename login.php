<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/init.php';  // Replace vendor/autoload.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$config = require 'includes/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $stmt = $db->prepare("SELECT id, password, role FROM users WHERE username = ?");
        if ($stmt === false) {
            $error = 'Prepare failed: ' . htmlspecialchars($db->error);
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($id, $hashed_password, $role);
            $stmt->fetch();

            if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                header('Location: index.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['email'])) {
        $email = $_POST['email'];
        $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
        if ($stmt === false) {
            $error = 'Prepare failed: ' . htmlspecialchars($db->error);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($id, $username);
            $stmt->fetch();

            if ($stmt->num_rows > 0) {
                $token = bin2hex(random_bytes(16));
                $stmt = $db->prepare("INSERT INTO password_resets (user_id, token) VALUES (?, ?)");
                if ($stmt === false) {
                    $error = 'Prepare failed: ' . htmlspecialchars($db->error);
                } else {
                    $stmt->bind_param("is", $id, $token);
                    $stmt->execute();
                    $stmt->close();

                    $resetLink = "http://yourdomain.com/reset_password.php?token=$token";
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = $config['smtp_host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $config['smtp_user'];
                        $mail->Password = $config['smtp_pass'];
                        $mail->SMTPSecure = $config['smtp_secure'];
                        $mail->Port = $config['smtp_port'];

                        $mail->setFrom($config['smtp_user'], 'Newsletter');
                        $mail->addAddress($email);
                        $mail->Subject = 'Password Reset Request';
                        $mail->Body = "Click the following link to reset your password: $resetLink";
                        $mail->send();
                        $error = 'Password reset link sent to your email';
                    } catch (Exception $e) {
                        $error = 'Mailer Error: ' . $mail->ErrorInfo;
                    }
                }
            } else {
                $error = 'No user found with that email address';
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
    <link rel="stylesheet" href="assets/css/newsletter-style.css
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
        <h2>Forgot Password?</h2>
        <form method="post">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            <button type="submit">Reset Password</button>
        </form>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>