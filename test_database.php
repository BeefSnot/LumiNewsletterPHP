<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = '104.194.222.48'; // Replace with the actual hostname or IP address
$db_user = 'lumihost_newsletter';
$db_pass = 'MZTAGAkPgPaS34MECBLn';
$db_name = 'lumihost_newsletter';

$db = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($db->connect_error) {
    die('Connection failed: ' . $db->connect_error);
} else {
    echo 'Connected successfully';
}
?>