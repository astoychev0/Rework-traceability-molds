<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['is_admin'] = true;

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'rework_traceability';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database Connection Error: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>