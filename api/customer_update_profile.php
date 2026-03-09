<?php
// customer_update_profile.php
// Expects JSON body:
// { customerId, firstName, lastName, phone, address1, address2, parish }

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

require_once 'config.php';
$conn = getDBConnection();

$input = json_decode(file_get_contents("php://input"), true);

$customerId = isset($input['customerId']) ? intval($input['customerId']) : 0;
$firstName  = isset($input['firstName']) ? trim($input['firstName']) : '';
$lastName   = isset($input['lastName']) ? trim($input['lastName']) : '';
$phone      = isset($input['phone']) ? trim($input['phone']) : '';
$address1   = isset($input['address1']) ? trim($input['address1']) : '';
$address2   = isset($input['address2']) ? trim($input['address2']) : '';
$parish     = isset($input['parish']) ? trim($input['parish']) : '';

if ($customerId <= 0) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Invalid customerId"]);
  exit();
}

if ($firstName === '' || $lastName === '') {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "First name and last name are required"]);
  exit();
}

// ✅ IMPORTANT: update the correct table name below.
// If your table is `customers`, keep it.
// If it is `users` or something else, change it.
$stmt = $conn->prepare("
  UPDATE customers
  SET first_name = ?, last_name = ?, phone = ?, address1 = ?, address2 = ?, parish = ?
  WHERE id = ?
");

if (!$stmt) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "Prepare failed", "error" => $conn->error]);
  exit();
}

$stmt->bind_param("ssssssi", $firstName, $lastName, $phone, $address1, $address2, $parish, $customerId);

if ($stmt->execute()) {
  echo json_encode([
    "success" => true,
    "message" => "Profile updated",
    "customer" => [
      "id" => $customerId,
      "first_name" => $firstName,
      "last_name" => $lastName,
      "phone" => $phone,
      "address1" => $address1,
      "address2" => $address2,
      "parish" => $parish
    ]
  ]);
} else {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "Update failed", "error" => $stmt->error]);
}

$stmt->close();
$conn->close();