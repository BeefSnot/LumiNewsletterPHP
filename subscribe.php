<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$config = require 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'])) {
        $email = $_POST['email'];
        $action = $_POST['action'];
        $groups = $_POST['groups'];

        if ($action === 'subscribe') {
            foreach ($groups as $group_id) {
                $stmt = $db->prepare("INSERT INTO group_subscriptions (email, group_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE group_id = ?");
                if ($stmt === false) {
                    die('Prepare failed: ' . htmlspecialchars($db->error));
                }
                $stmt->bind_param("sii", $email, $group_id, $group_id);
                if ($stmt->execute() === false) {
                    die('Execute failed: ' . htmlspecialchars($stmt->error));
                }
                $stmt->close();
            }
            $message = 'Subscribed successfully';

            // Fetch the "Thanks For Subscribing" theme from the database
            $themeStmt = $db->prepare("SELECT content FROM themes WHERE name = 'Thanks For Subscribing'");
            if ($themeStmt === false) {
                die('Prepare failed: ' . htmlspecialchars($db->error));
            }
            $themeStmt->execute();
            $themeStmt->bind_result($themeContent);
            $themeStmt->fetch();
            $themeStmt->close();

            // Send the "Thanks For Subscribing" email
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_user'];
                $mail->Password = $config['smtp_pass'];
                $mail->SMTPSecure = $config['smtp_secure'];
                $mail->Port = $config['smtp_port'];

                // Recipients
                $mail->setFrom($config['smtp_user'], 'Newsletter');
                $mail->addAddress($email);
                $mail->Subject = 'Thanks for Subscribing!';
                $mail->Body = $themeContent;
                $mail->send();
            } catch (Exception $e) {
                die('Mailer Error: ' . $mail->ErrorInfo);
            }
        } elseif ($action === 'unsubscribe') {
            foreach ($groups as $group_id) {
                $stmt = $db->prepare("DELETE FROM group_subscriptions WHERE email = ? AND group_id = ?");
                if ($stmt === false) {
                    die('Prepare failed: ' . htmlspecialchars($db->error));
                }
                $stmt->bind_param("si", $email, $group_id);
                if ($stmt->execute() === false) {
                    die('Execute failed: ' . htmlspecialchars($stmt->error));
                }
                $stmt->close();
            }
            $message = 'Unsubscribed successfully';
        }
    }
}

// Fetch available groups
$groupsResult = $db->query("SELECT id, name FROM groups");
$groups = [];
while ($row = $groupsResult->fetch_assoc()) {
    $groups[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe/Unsubscribe</title>
    <link rel="stylesheet" href="assets/css/newsletter.css">
</head>
<body>
    <main>
        <h2>Subscribe/Unsubscribe</h2>
        <?php if (isset($message)): ?>
            <p><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="groups">Groups:</label>
                <select class="form-control" id="groups" name="groups[]" multiple required>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>"><?php echo $group['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="action">Action:</label>
                <select class="form-control" id="action" name="action" required>
                    <option value="subscribe">Subscribe</option>
                    <option value="unsubscribe">Unsubscribe</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Submit</button>
        </form>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>