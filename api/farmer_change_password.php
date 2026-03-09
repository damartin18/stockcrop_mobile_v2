<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

$farmerId        = isset($input['farmerId'])        ? intval($input['farmerId'])        : 0;
$currentPassword = isset($input['currentPassword']) ? $input['currentPassword']         : '';
$newPassword     = isset($input['newPassword'])     ? $input['newPassword']             : '';

if ($farmerId <= 0 || empty($currentPassword) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
    exit();
}

$conn = getDBConnection();

// Get current password hash
$stmt = $conn->prepare("SELECT password FROM farmers WHERE id = ?");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Farmer not found']);
    $stmt->close();
    $conn->close();
    exit();
}

$row = $result->fetch_assoc();
$stmt->close();

// Verify current password
if (!password_verify($currentPassword, $row['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    $conn->close();
    exit();
}

// Update with new password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt2 = $conn->prepare("UPDATE farmers SET password = ? WHERE id = ?");
$stmt2->bind_param("si", $hashedPassword, $farmerId);

if ($stmt2->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to change password']);
}

$stmt2->close();
$conn->close();
?>