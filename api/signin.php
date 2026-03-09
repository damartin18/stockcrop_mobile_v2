<?php
// =============================================
// StockCrop API - Sign In (Login)
// POST: { "email": "...", "password": "..." }
// =============================================
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

$email    = trim(strtolower($input['email']));
$password = $input['password'];
$conn     = getDBConnection();

// Try customers table first
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, phone, address1, parish, role_id FROM customers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$user     = null;
$userType = null;

if ($result->num_rows > 0) {
    $user     = $result->fetch_assoc();
    $userType = 'customer';
} else {
    $stmt->close();

    // Try farmers table
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, phone, farm_name, parish, rada_number, role_id, verification_status FROM farmers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user     = $result->fetch_assoc();
        $userType = 'farmer';
    } else {
        $stmt->close();

        // Try admins table
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role_id FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user     = $result->fetch_assoc();
            $userType = 'admin';
        }
    }
}

$stmt->close();
$conn->close();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit();
}

// Verify password
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit();
}

// Check farmer verification
if ($userType === 'farmer' && $user['verification_status'] !== 'verified') {
    http_response_code(403);
    echo json_encode([
        'success'             => false,
        'message'             => 'Your farmer account is pending verification.',
        'verification_status' => $user['verification_status']
    ]);
    exit();
}

// Remove password from response
unset($user['password']);

echo json_encode([
    'success'   => true,
    'message'   => 'Login successful',
    'user'      => $user,
    'user_type' => $userType
]);
?>
