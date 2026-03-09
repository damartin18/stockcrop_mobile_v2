<?php

require_once "config.php";

$conn = getDBConnection();

$email = trim($_POST['email'] ?? '');

if (!$email) {
    echo json_encode([
        "success" => false,
        "message" => "Email is required"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email format"
    ]);
    exit;
}

$user = null;
$userType = null;

// Check customers
$stmt = $conn->prepare("SELECT id, email FROM customers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $userType = "customer";
}
$stmt->close();

// Check farmers if not found
if (!$user) {
    $stmt = $conn->prepare("SELECT id, email FROM farmers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userType = "farmer";
    }
    $stmt->close();
}

// Check admins if not found
if (!$user) {
    $stmt = $conn->prepare("SELECT id, email FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userType = "admin";
    }
    $stmt->close();
}

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "Email not found"
    ]);
    $conn->close();
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Password reset request accepted",
    "email" => $user["email"],
    "userType" => $userType
]);

$conn->close();
?>