<?php
// =============================================
// StockCrop API - Register Customer
// POST: { "first_name", "last_name", "email", "password", "phone", "address1", "parish" }
// =============================================
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['first_name']) || !isset($input['last_name']) || !isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'First name, last name, email, and password are required']);
    exit();
}

$firstName = trim($input['first_name']);
$lastName  = trim($input['last_name']);
$email     = trim(strtolower($input['email']));
$password  = password_hash($input['password'], PASSWORD_DEFAULT);
$phone     = isset($input['phone']) ? trim($input['phone']) : '';
$address1  = isset($input['address1']) ? trim($input['address1']) : '';
$parish    = isset($input['parish']) ? trim($input['parish']) : '';

$conn = getDBConnection();

// Check if email already exists
$check = $conn->prepare("SELECT id FROM customers WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $check->close();
    $conn->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit();
}
$check->close();

// Also check farmers table
$check2 = $conn->prepare("SELECT id FROM farmers WHERE email = ?");
$check2->bind_param("s", $email);
$check2->execute();
if ($check2->get_result()->num_rows > 0) {
    $check2->close();
    $conn->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered as a farmer']);
    exit();
}
$check2->close();

$stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, email, password, phone, address1, parish, role_id) VALUES (?, ?, ?, ?, ?, ?, ?, 3)");
$stmt->bind_param("sssssss", $firstName, $lastName, $email, $password, $phone, $address1, $parish);

if ($stmt->execute()) {
    echo json_encode([
        'success'     => true,
        'message'     => 'Registration successful',
        'customer_id' => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
