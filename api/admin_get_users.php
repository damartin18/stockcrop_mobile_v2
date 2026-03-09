<?php
// =============================================
// StockCrop API - Admin Settings: Get All Users
// Pulls from admins, customers, farmers tables
// =============================================
require_once 'config.php';

$conn = getDBConnection();

$sql = "
    SELECT id, email, role_id, created_at, 'Admin' AS role_name, 'admins' AS source_table
    FROM admins

    UNION ALL

    SELECT id, email, role_id, created_at, 'Farmer' AS role_name, 'farmers' AS source_table
    FROM farmers

    UNION ALL

    SELECT id, email, role_id, created_at, 'Customer' AS role_name, 'customers' AS source_table
    FROM customers

    ORDER BY created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    $conn->close();
    exit;
}

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'id'          => (int) $row['id'],
        'email'       => $row['email'],
        'roleId'      => (int) $row['role_id'],
        'roleName'    => $row['role_name'],
        'sourceTable' => $row['source_table'],
        'createdAt'   => $row['created_at'],
    ];
}

echo json_encode(['success' => true, 'users' => $users]);

$conn->close();
?>