<?php
// =============================================
// StockCrop API - Update Order Item Status
// POST: { itemId, orderId, farmerId, status }
// =============================================
require_once 'config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$itemId   = isset($input['itemId']) ? intval($input['itemId']) : 0;
$orderId  = isset($input['orderId']) ? intval($input['orderId']) : 0;
$farmerId = isset($input['farmerId']) ? intval($input['farmerId']) : 0;
$status   = isset($input['status']) ? trim($input['status']) : '';

$allowed = ['Pending', 'Processing', 'Ready for Pickup', 'Shipped', 'Delivered'];

if ($itemId <= 0 || $orderId <= 0 || $farmerId <= 0 || !in_array($status, $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Required: itemId, orderId, farmerId, status (Pending/Processing/Ready for Pickup/Shipped/Delivered)']);
    exit();
}

$conn = getDBConnection();

// Verify item belongs to this farmer and order
$stmt = $conn->prepare("SELECT id, status FROM order_items WHERE id = ? AND orderId = ? AND farmerId = ?");
$stmt->bind_param("iii", $itemId, $orderId, $farmerId);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found or does not belong to you']);
    exit();
}

// Update item status
$stmt = $conn->prepare("UPDATE order_items SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $itemId);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update item status']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Auto-update overall order status based on all items in the order
$allItemsStmt = $conn->prepare("SELECT status FROM order_items WHERE orderId = ?");
$allItemsStmt->bind_param("i", $orderId);
$allItemsStmt->execute();
$allResult = $allItemsStmt->get_result();

$itemStatuses = [];
while ($row = $allResult->fetch_assoc()) {
    $itemStatuses[] = $row['status'];
}
$allItemsStmt->close();

// Determine overall order status
$orderStatus = null;
if (!empty($itemStatuses)) {
    $allDelivered = true;
    $allShipped = true;
    $allReadyForPickup = true;
    $allProcessing = true;
    $anyProcessing = false;
    $anyShipped = false;
    $anyReadyForPickup = false;
    $anyDelivered = false;

    foreach ($itemStatuses as $s) {
        if ($s !== 'Delivered') $allDelivered = false;
        if ($s !== 'Shipped' && $s !== 'Delivered') $allShipped = false;
        if ($s !== 'Ready for Pickup' && $s !== 'Shipped' && $s !== 'Delivered') $allReadyForPickup = false;
        if ($s !== 'Processing' && $s !== 'Ready for Pickup' && $s !== 'Shipped' && $s !== 'Delivered') $allProcessing = false;

        if ($s === 'Processing') $anyProcessing = true;
        if ($s === 'Ready for Pickup') $anyReadyForPickup = true;
        if ($s === 'Shipped') $anyShipped = true;
        if ($s === 'Delivered') $anyDelivered = true;
    }

    if ($allDelivered) {
        $orderStatus = 'delivered';
    } elseif ($allShipped) {
        $orderStatus = 'shipped';
    } elseif ($anyShipped || $anyDelivered) {
        $orderStatus = 'shipped';
    } elseif ($anyReadyForPickup) {
        $orderStatus = 'processing';
    } elseif ($anyProcessing) {
        $orderStatus = 'processing';
    } elseif ($allProcessing) {
        $orderStatus = 'processing';
    }

    if ($orderStatus !== null) {
        $updateOrder = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $updateOrder->bind_param("si", $orderStatus, $orderId);
        $updateOrder->execute();
        $updateOrder->close();
    }
}

// Notify customer about the item status change
$productStmt = $conn->prepare("SELECT p.productName FROM order_items oi JOIN products p ON oi.productId = p.id WHERE oi.id = ?");
$productStmt->bind_param("i", $itemId);
$productStmt->execute();
$productRow = $productStmt->get_result()->fetch_assoc();
$productStmt->close();

$productName = $productRow ? $productRow['productName'] : 'Product';

// Get customer ID from order
$custStmt = $conn->prepare("SELECT customerId FROM orders WHERE id = ?");
$custStmt->bind_param("i", $orderId);
$custStmt->execute();
$custRow = $custStmt->get_result()->fetch_assoc();
$custStmt->close();

if ($custRow) {
    $customerId = intval($custRow['customerId']);
    $notifMsg = "\"$productName\" in Order #$orderId has been updated to: $status";
    $notifStmt = $conn->prepare("INSERT INTO notifications (userId, userType, title, message) VALUES (?, 'customer', 'Order Update', ?)");
    $notifStmt->bind_param("is", $customerId, $notifMsg);
    $notifStmt->execute();
    $notifStmt->close();
}

$conn->close();

echo json_encode([
    'success' => true,
    'message' => "Item status updated to $status",
    'itemStatus' => $status,
    'orderStatus' => $orderStatus,
]);
?>