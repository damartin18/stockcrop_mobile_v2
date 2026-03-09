<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = 'maglev.proxy.rlwy.net';
$user = 'root';             // or Railway MYSQLUSER
$pass = 'your_real_password';
$db   = 'railway';          // or Railway MYSQLDATABASE
$port = 59589;              // from Railway

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('DB connect error: ' . $e->getMessage());
    die('Database connection error.');
}
