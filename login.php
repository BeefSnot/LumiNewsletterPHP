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
$success = '';

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

                    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=$token";
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
                        $success = 'Password reset link sent to your email';
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
    <title>Login | LumiNewsletter</title>
    <link rel="stylesheet" href="assets/css/newsletter-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--gray-light);
            background-image: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            padding: 2rem;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .login-logo i {
            font-size: 3rem;
            color: white;
        }
        
        .login-logo h1 {
            color: white;
            font-size: 2rem;
            margin-top: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card-header {
            text-align: center;
            padding: 1.5rem;
        }
        
        .card-header h2 {
            margin: 0;
            color: var(--primary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 1.5rem;
        }
        
        .tab {
            flex: 1;
            text-align: center;
            padding: 1rem;
            cursor: pointer;
            color: var(--gray);
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .form-group input {
            padding-left: 3rem;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .forgot-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary);
            text-decoration: none;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: white;
            font-size: 0.875rem;
        }
        
        .notification {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
        }
        
        .notification.error {
            background-color: rgba(234, 67, 53, 0.1);
            color: var(--error);
            border-left: 3px solid var(--error);
        }
        
        .notification.success {
            background-color: rgba(52, 168, 83, 0.1);
            color: var(--accent);
            border-left: 3px solid var(--accent);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <i class="fas fa-paper-plane"></i>
            <h1>LumiNewsletter</h1>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Welcome Back</h2>
            </div>
            <div class="card-body">
                <div class="tabs">
                    <div class="tab active" id="login-tab" onclick="switchTab('login')">Login</div>
                    <div class="tab" id="reset-tab" onclick="switchTab('reset')">Reset Password</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="notification error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="notification success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <div class="tab-content active" id="login-content">
                    <form method="post">
                        <div class="form-group">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username" placeholder="Username" required>
                        </div>
                        <div class="form-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </form>
                    <a href="#" class="forgot-link" onclick="switchTab('reset')">Forgot your password?</a>
                </div>
                
                <div class="tab-content" id="reset-content">
                    <form method="post">
                        <div class="form-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="Email Address" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-paper-plane"></i> Send Reset Link
                        </button>
                    </form>
                    <a href="#" class="forgot-link" onclick="switchTab('login')">Back to login</a>
                </div>
            </div>
        </div>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> LumiNewsletter - Professional Newsletter Management</p>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Hide all tabs and content
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Activate selected tab
            document.getElementById(tab + '-tab').classList.add('active');
            document.getElementById(tab + '-content').classList.add('active');
        }
        
        // If there's a password reset success message, show the login tab
        <?php if ($success): ?>
        window.onload = function() {
            switchTab('login');
        }
        <?php endif; ?>
    </script>
</body>
</html>