<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

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
                $mail->Host = 'mail.lumihost.net';
                $mail->SMTPAuth = true;
                $mail->Username = 'newsletter@lumihost.net';
                $mail->Password = 'rcfY6UFxEa2KhXcxb2LW';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('newsletter@lumihost.net', 'Lumi Host Newsletter');
                $mail->addAddress($email); // Add the recipient's email address

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Thanks for Subscribing!';
                $mail->Body = $themeContent;

                $mail->send();
            } catch (Exception $e) {
                error_log("Mailer Error: {$mail->ErrorInfo}");
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

            // Fetch the "Unsubscribed" theme from the database
            $themeStmt = $db->prepare("SELECT content FROM themes WHERE name = 'Unsubscribed'");
            if ($themeStmt === false) {
                die('Prepare failed: ' . htmlspecialchars($db->error));
            }
            $themeStmt->execute();
            $themeStmt->bind_result($themeContent);
            $themeStmt->fetch();
            $themeStmt->close();

            // Send the "Unsubscribed" email
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'mail.lumihost.net';
                $mail->SMTPAuth = true;
                $mail->Username = 'newsletter@lumihost.net';
                $mail->Password = 'rcfY6UFxEa2KhXcxb2LW';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('newsletter@lumihost.net', 'Lumi Host Newsletter');
                $mail->addAddress($email); // Add the recipient's email address

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'You have unsubscribed';
                $mail->Body = $themeContent;

                $mail->send();
            } catch (Exception $e) {
                error_log("Mailer Error: {$mail->ErrorInfo}");
            }
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
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.0/dist/aos.css" />
    <title>Lumi Host | Subscription Management</title>
    <style>
        .cookie-alert {
            position: fixed;
            bottom: 15px;
            right: 15px;
            width: 320px;
            margin: 0 !important;
            z-index: 999;
            opacity: 0;
            border-radius: 8px;
            border: none;
            background-color: #151720; /* Ensure background color is set */
            color: #ffffff; /* Ensure text color is set */
            transform: translateY(100%);
            transition: all 500ms ease-out;
        }

        .cookie-alert.show {
            opacity: 1;
            transform: translateY(0%);
            transition-delay: 1000ms;
        }

        .cookie-alert .card-body {
            background-color: #151720; /* Ensure background color is set */
            color: #ffffff; /* Ensure text color is set */
        }

        .cookie-alert .btn-primary {
            background-color: #1592e8;
            border-color: #1592e8;
        }

        .cookie-alert .btn-primary:hover {
            background-color: #1487d3;
            border-color: #1487d3;
        }
    </style>
</head>
<body>
    <div class="card cookie-alert shadow">
        <div class="card-body">
            <h5 class="card-title">&#x1F36A; Do you like cookies?</h5>
            <p class="card-text">We use cookies to ensure you get the best experience on our website.</p>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="essentialCookies" checked disabled>
                <label class="form-check-label" for="essentialCookies">
                    Essential Cookies
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="performanceCookies">
                <label class="form-check-label" for="performanceCookies">
                    Performance Cookies
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="functionalCookies">
                <label class="form-check-label" for="functionalCookies">
                    Functional Cookies
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="targetingCookies">
                <label class="form-check-label" for="targetingCookies">
                    Targeting Cookies
                </label>
            </div>
            <div class="btn-toolbar justify-content-between mt-3">
                <a href="http://cookiesandyou.com/" target="_blank" class="btn btn-link">Learn more</a>
                <div>
                    <button class="btn btn-primary accept-selected-cookies">Accept Selected</button>
                    <button class="btn btn-primary accept-cookies ml-2">Accept All</button>
                </div>
            </div>
        </div>
    </div>
    <header class="hero">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="https://lumihost.net">
                    <img class="img-fluid" src="../assets/img/logonew.svg" alt="Lumi Host">
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link " href="../index.php">Home</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Hosting
                            </a>
                            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="https://billing.lumihost.net/">Minecraft Hosting</a>
                                <a class="dropdown-item" href="https://lumihost.net/">Rust Hosting (coming soon)</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="https://billing.lumihost.net/web-hosting">Website Hosting</a>
                                <a class="dropdown-item" href="https://lumihost.net/">Dedicated Server Hosting (coming soon)</a>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://lumihost.net/tickets/tickets.php">Support (coming soon)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../status/index.php">Status</a>
                        </li>
                        <li class="nav-item">
                            <a href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link nav-btn dropdown-toggle">
                                login
                            </a>
                            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="https://gamepanel.lumihost.net">Game Panel</a>
                                <a class="dropdown-item" href="https://webpanel.lumihost.net:2222">Web Panel</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="https://billing.lumihost.net">Billing Panel</a>
                                <a class="dropdown-item" href="../staff.php">Staff Center </a>
                          </div>
                      </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container mt-5">
        <div class="section-title text-center">
            <h6 class="text-uppercase text-muted">Newsletter Subscription</h6>
            <h4 class="font-weight-bold">Subscribe/Unsubscribe to Newsletters<span class="main">.</span></h4>
        </div>
        <div class="status-details text-center dark-background p-4 rounded">
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
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="footer-col py-4">
                        <h5>Navigation<span class="main">.</span></h5>
                        <ul>
                            <li><a href="https://lumihost.net">Home</a></li>
                            <li><a href="https://lumihost.net">About</a></li>
                            <li><a href="https://billing.lumihost.net/">Hosting</a></li>
                            <li><a href="https://lumihost.net/tickets/tickets.php">Support</a></li>
                            <li><a href="newslettersubscription.html">Subscribe To Newsletter</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="footer-col py-4">
                        <h5>Login<span class="main">.</span></h5>
                        <ul>
                            <li><a href="https://gamepanel.lumihost.net">Game Panel</a></li>
                            <li><a href="https://webpanel.lumihost.net:2222">Web Panel</a></li>
                            <li><a href="https://billing.lumihost.net">Billing Panel</a></li>
                            <li><a href="/staff.php">Staff Center</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="footer-col py-4">
                        <h5>Legal<span class="main">.</span></h5>
                        <ul>
                            <li><a href="/tos.php">Terms of Service</a></li>
                            <li><a href="/pp.php">Privacy Policy</a></li>
                            <li><a href="/cookies.php">Cookie Policy</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="copyright">
            <div class="container">
                <h6 class="mb-0">Copyright © 2025 Lumi Host. | All Rights Reserved |</h6>
            </div>
        </div>
    </footer>

    <script>
        document.querySelector('.accept-selected-cookies').addEventListener('click', function() {
            if (document.getElementById('performanceCookies').checked) {
                setCookie('performanceCookies', true, 60);
            }
            if (document.getElementById('functionalCookies').checked) {
                setCookie('functionalCookies', true, 60);
            }
            if (document.getElementById('targetingCookies').checked) {
                setCookie('targetingCookies', true, 60);
            }
            setCookie('acceptCookies', true, 60);
            document.querySelector('.cookie-alert').classList.remove('show');
        });

        document.querySelector('.accept-cookies').addEventListener('click', function() {
            setCookie('performanceCookies', true, 60);
            setCookie('functionalCookies', true, 60);
            setCookie('targetingCookies', true, 60);
            setCookie('acceptCookies', true, 60);
            document.querySelector('.cookie-alert').classList.remove('show');
        });

        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            var expires = "expires=" + d.toUTCString();
            document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
        }

        function getCookie(cname) {
            var name = cname + "=";
            var decodedCookie = decodeURIComponent(document.cookie);
            var ca = decodedCookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) === 0) {
                    return c.substring(name.length, c.length);
                }
            }
            return "";
        }

        if (!getCookie("acceptCookies")) {
            document.querySelector('.cookie-alert').classList.add('show');
        }
    </script>
    <script src="../assets/js/jquery.slim.min.js"></script>
    <script src="../assets/js/popper.min.js"></script>
    <script src="../assets/js/bootstrap.min.js"></script>
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script src="../assets/js/aos.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="https://unpkg.com/aos@2.3.0/dist/aos.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var swiper = new Swiper('.swiper-container', {
                loop: true,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                autoplay: {
                    delay: 5000,
                },
            });
        });

        AOS.init({
            duration: 1200,
        });

        // Back to Top Button
        const backToTopButton = document.getElementById('back-to-top');

        window.onscroll = function() {
            scrollFunction();
        };

        function scrollFunction() {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                backToTopButton.style.display = "block";
            } else {
                backToTopButton.style.display = "none";
            }
        }

        backToTopButton.addEventListener('click', function() {
            $('html, body').animate({scrollTop: 0}, 'slow');
            // Animation of server powering on
            backToTopButton.innerHTML = '<i class="fas fa-server"></i>';
            setTimeout(() => {
                backToTopButton.innerHTML = '<i class="fas fa-arrow-up"></i>';
            }, 4000); // Reset icon after 4 seconds
        });
    </script>
</body>
</html>