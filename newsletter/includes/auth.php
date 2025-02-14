<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
?>