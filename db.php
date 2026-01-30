<?php
$host = 'localhost';
$user = 'root';
$pass = 'konstantinos';
$db   = 'plakoto';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $mysqli->connect_error]));
}

$mysqli->set_charset("utf8mb4");
?>