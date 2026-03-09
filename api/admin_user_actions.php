<?php
// =============================================
// StockCrop API - Admin Settings: User Actions
// POST: { "action": "addUser", "email": "...", "password": "...", "roleId": 1|2|3 }
// POST: { "action": "changePassword", "userId": 1, "roleId": 2, "newPassword": "..." }
// =============================================
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = getDBConnection();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// ─── Helper: get table name from roleId ────────────────────────────────
function getTableName($roleId) {
    switch ((int) $roleId) {
        case 1: return 'admins';
        case 2: return 'farmers';
        case 3: return 'customers';
        default: return null;
    }
}

// ─── ADD USER ──────────────────────────────────────────────────────────
if ($action === 'addUser') {
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $roleId   = (int) ($input['roleId'] ?? 3);

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        $conn->close();
        exit;
    }

    $table = getTableName($roleId);
    if (!$table) {
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        $conn->close();
        exit;
    }

    // Check if email already exists in that table
    $check = $conn->prepare("SELECT id FROM $table WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        $check->close();
        $conn->close();
        exit;
    }
    $check->close();

    // Also check the other two tables to avoid duplicate emails across roles
    $allTables = ['admins', 'customers', 'farmers'];
    foreach ($allTables as $t) {
        if ($t === $table) continue;
        $dup = $conn->prepare("SELECT id FROM $t WHERE email = ?");
        $dup->bind_param("s", $email);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => "Email already exists in $t"]);
            $dup->close();
            $conn->close();
            exit;
        }
        $dup->close();
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    if ($roleId === 1) {
        // Admin
        $stmt = $conn->prepare("INSERT INTO admins (first_name, last_name, email, password, role_id, created_at) VALUES ('', '', ?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $email, $hashed, $roleId);
    } elseif ($roleId === 2) {
        // Farmer
        $stmt = $conn->prepare("INSERT INTO farmers (first_name, last_name, email, password, role_id, verification_status, created_at) VALUES ('', '', ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("ssi", $email, $hashed, $roleId);
    } else {
        // Customer
        $stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, email, password, role_id, created_at) VALUES ('', '', ?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $email, $hashed, $roleId);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User added successfully', 'userId' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add user: ' . $stmt->error]);
    }

    $stmt->close();
}

// ─── CHANGE PASSWORD ───────────────────────────────────────────────────
elseif ($action === 'changePassword') {
    $userId      = (int) ($input['userId'] ?? 0);
    $roleId      = (int) ($input['roleId'] ?? 0);
    $newPassword = $input['newPassword'] ?? '';

    if ($userId <= 0 || empty($newPassword) || $roleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'User ID, role, and new password required']);
        $conn->close();
        exit;
    }

    $table = getTableName($roleId);
    if (!$table) {
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        $conn->close();
        exit;
    }

    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $userId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Password updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found or password unchanged']);
    }

    $stmt->close();
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action. Use addUser or changePassword']);
}

$conn->close();
?>