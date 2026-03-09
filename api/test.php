<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = trim((string)(getenv("DB_HOST") ?: ""));
$user = trim((string)(getenv("DB_USER") ?: ""));
$pass = (string)(getenv("DB_PASS") ?: "");
$db   = trim((string)(getenv("DB_NAME") ?: ""));
$port = (int)(getenv("DB_PORT") ?: 3306);

if ($host === "" || $user === "" || $db === "" || $port <= 0) {
    die("Missing DB env vars. Host=$host Port=$port User=$user DB=$db");
}

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>


