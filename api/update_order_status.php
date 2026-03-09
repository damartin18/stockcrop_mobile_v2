<?php
// =============================================
// StockCrop API - Update Order Status
// PUT: { orderId, status }
// Status options: pending, confirmed, processing, shipped, delivered, cancelled
// =============================================
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

$orderId = isset($input['orderId']) ? intval($input['orderId']) : 0;
$status  = isset($input['status']) ? trim($input['status']) : '';

$validStatuses = ['pending', 'confirmed', 'processing', 'ready for pickup', 'shipped', 'delivered', 'cancelled'];

if ($orderId <= 0 || !in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid order ID and status are required']);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $orderId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    // Add notification for customer
    $orderStmt = $conn->prepare("SELECT customerId FROM orders WHERE id = ?");
    $orderStmt->bind_param("i", $orderId);
    $orderStmt->execute();
    $customerId = $orderStmt->get_result()->fetch_assoc()['customerId'];
    $orderStmt->close();

    $notifStmt = $conn->prepare("INSERT INTO notifications (userId, userType, title, message) VALUES (?, 'customer', 'Order Update', ?)");
    $notifMsg  = 'Your order #' . $orderId . ' status has been updated to: ' . ucfirst($status);
    $notifStmt->bind_param("is", $customerId, $notifMsg);
    $notifStmt->execute();
    $notifStmt->close();

    echo json_encode(['success' => true, 'message' => 'Order status updated to ' . $status]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found or status unchanged']);
}

$stmt->close();
$conn->close();
?>
