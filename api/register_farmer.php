<?php
// =============================================
// StockCrop API - Register Farmer
// POST: { "first_name", "last_name", "email", "password", "phone", "farm_name", "parish", "rada_number" }
// =============================================
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['first_name']) || !isset($input['last_name']) || !isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'First name, last name, email, and password are required']);
    exit();
}

$firstName  = trim($input['first_name']);
$lastName   = trim($input['last_name']);
$email      = trim(strtolower($input['email']));
$password   = password_hash($input['password'], PASSWORD_DEFAULT);
$phone      = isset($input['phone']) ? trim($input['phone']) : '';
$farmName   = isset($input['farm_name']) ? trim($input['farm_name']) : '';
$parish     = isset($input['parish']) ? trim($input['parish']) : '';
$radaNumber = isset($input['rada_number']) ? trim($input['rada_number']) : '';

$conn = getDBConnection();

// Check if email exists in customers or farmers
$check = $conn->prepare("SELECT id FROM customers WHERE email = ? UNION SELECT id FROM farmers WHERE email = ?");
$check->bind_param("ss", $email, $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $check->close();
    $conn->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit();
}
$check->close();

$stmt = $conn->prepare("INSERT INTO farmers (first_name, last_name, email, password, phone, farm_name, parish, rada_number, role_id, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2, 'pending')");
$stmt->bind_param("ssssssss", $firstName, $lastName, $email, $password, $phone, $farmName, $parish, $radaNumber);

if ($stmt->execute()) {
    echo json_encode([
        'success'   => true,
        'message'   => 'Farmer registration submitted. Awaiting verification.',
        'farmer_id' => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
