<?php
// HARDCODED - Render doesn't get Railway env vars automatically
$host = 'maglev.proxy.rlwy.net';           // Your green proxy ✅
$port = 59289;                             // Your green port ✅
$user = 'root';                            // From MYSQLUSER (confirm)
$pass = 'PASTE_YOUR_MYSQLPASSWORD_HERE';   // Reveal from Railway Variables
$db   = 'railway';                         // From MYSQLDATABASE (confirm)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset('utf8mb4');
    echo "✅ Connected to maglev.proxy.rlwy.net:59289";
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo "❌ DB Error: " . $e->getMessage();
    error_log("DB connect failed: " . $e->getMessage());
    exit;
}
?>


