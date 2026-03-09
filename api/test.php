<?php
// Load Railway environment variables (if on Railway) or set manually for local testing
$host = $_SERVER['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? 'maglev.proxy.rlwy.net';
$port = $_SERVER['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? 59589;
$user = $_SERVER['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? 'root';
$pass = $_SERVER['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? 'your_password_here';
$db   = $_SERVER['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? 'railway';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db, (int)$port);
    $conn->set_charset('utf8mb4');
    echo "Connected successfully to: " . $conn->host_info . "\n";
} catch (mysqli_sql_exception $e) {
    error_log("DB connect failed: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
}
?>

