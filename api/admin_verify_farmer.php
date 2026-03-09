<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

$farmerId = isset($input['farmerId']) ? intval($input['farmerId']) : 0;
$status   = isset($input['verification_status']) ? trim($input['verification_status']) : '';

// Also accept 'status' key
if (empty($status)) {
    $status = isset($input['status']) ? trim($input['status']) : '';
}

$validStatuses = ['verified', 'rejected'];

if ($farmerId <= 0 || !in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid farmer ID and status (verified/rejected) required']);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("UPDATE farmers SET verification_status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $farmerId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $notifStmt = $conn->prepare("INSERT INTO notifications (userId, userType, title, message) VALUES (?, 'farmer', 'Account Update', ?)");
    $notifMsg = $status === 'verified'
        ? 'Congratulations! Your farmer account has been verified.'
        : 'Your farmer account verification was not approved.';
    $notifStmt->bind_param("is", $farmerId, $notifMsg);
    $notifStmt->execute();
    $notifStmt->close();

    echo json_encode(['success' => true, 'message' => 'Farmer status updated to ' . $status]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Farmer not found or status unchanged']);
}

$stmt->close();
$conn->close();
?>