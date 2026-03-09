<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "stockcrop_mobile_v2";  

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die(json_encode([
        "success" => false,
        "message" => "Database connection failed: " . $conn->connect_error
    ]));
}
?>