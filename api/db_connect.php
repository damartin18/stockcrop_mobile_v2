<?php
$host = 'maglev.proxy.rlwy.net';    // ✅ Your green proxy host
$port = 59289;                      // ✅ Your green proxy port  
$user = 'root';                     // From MYSQLUSER variable
$pass = 'YOUR_MYSQLPASSWORD';       // From Variables tab (reveal it)
$db   = 'railway';                  // From MYSQLDATABASE variable

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "✅ Connected successfully to maglev.proxy.rlwy.net:59289!";
$conn->close();
?>

