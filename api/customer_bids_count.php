<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

error_reporting(0);
ini_set('display_errors', 0);

$host = "localhost";
$user = "root";
$password = "";
$database = "stockcrop_mobile_v2";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}
$conn->set_charset("utf8mb4");

$customerId = isset($_GET['customerId']) ? intval($_GET['customerId']) : 0;

if ($customerId <= 0) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

// Count bids where it's the customer's turn to respond
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS count FROM bids WHERE customerId = ? AND status = 'negotiating' AND currentTurn = 'customer'"
);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'count' => intval($row['count'])]);
?>