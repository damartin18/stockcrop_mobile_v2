<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = getenv("DB_HOST") ?: '';
$user = getenv("DB_USER") ?: '';
$pass = getenv("DB_PASS") ?: '';
$db   = getenv("DB_NAME") ?: '';
$port = (int)(getenv("DB_PORT") ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
