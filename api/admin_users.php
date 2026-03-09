<?php
// =============================================
// StockCrop API - Admin: Get Users
// GET: ?type=customers  → list all customers
// GET: ?type=farmers    → list all farmers
// GET: ?type=farmers&status=pending → pending farmers only
// =============================================
require_once 'config.php';

$type   = isset($_GET['type']) ? trim($_GET['type']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$conn = getDBConnection();

if ($type === 'customers') {
    $result = $conn->query("
        SELECT id, first_name, last_name, email, phone, address1, parish, created_at
        FROM customers ORDER BY created_at DESC
    ");

    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }

    echo json_encode(['success' => true, 'customers' => $customers, 'total' => count($customers)]);

} elseif ($type === 'farmers') {
    $sql = "SELECT id, first_name, last_name, email, phone, farm_name, parish, rada_number, verification_status, created_at FROM farmers";

    if (!empty($status)) {
        $sql .= " WHERE verification_status = '" . $conn->real_escape_string($status) . "'";
    }

    $sql   .= " ORDER BY created_at DESC";
    $result = $conn->query($sql);

    $farmers = [];
    while ($row = $result->fetch_assoc()) {
        $farmers[] = $row;
    }

    echo json_encode(['success' => true, 'farmers' => $farmers, 'total' => count($farmers)]);

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type must be "customers" or "farmers"']);
}

$conn->close();
?>
