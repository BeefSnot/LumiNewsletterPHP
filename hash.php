<?php
$password = 'Babyblue@1'; // Replace with your desired password
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>