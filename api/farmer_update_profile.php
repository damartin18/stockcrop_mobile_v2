<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/db_connect.php';

// Read JSON (Flutter sends JSON)
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) { $data = $_POST; }

$farmerId   = intval($data['farmerId'] ?? 0);
$firstName  = trim($data['firstName'] ?? '');
$lastName   = trim($data['lastName'] ?? '');
$phone      = trim($data['phone'] ?? '');
$farmName   = trim($data['farmName'] ?? '');
$parish     = trim($data['parish'] ?? '');
$radaNumber = trim($data['radaNumber'] ?? '');

if ($farmerId <= 0) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "farmerId is required"]);
  exit;
}

$stmt = $conn->prepare("
  UPDATE farmers
  SET first_name = ?, last_name = ?, phone = ?, farm_name = ?, parish = ?, rada_number = ?
  WHERE id = ?
");

if (!$stmt) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
  exit;
}

$stmt->bind_param(
  "ssssssi",
  $firstName,
  $lastName,
  $phone,
  $farmName,
  $parish,
  $radaNumber,
  $farmerId
);

$ok = $stmt->execute();

echo json_encode([
  "success" => $ok,
  "message" => $ok ? "Profile updated successfully" : ("Update failed: " . $stmt->error)
]);

$stmt->close();
$conn->close();
