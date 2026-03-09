<?php
// =============================================
// StockCrop API - Farmer Order Details (with item status)
// GET: ?orderId=1&farmerId=1
// =============================================
require_once 'config.php';

header('Content-Type: application/json');

$orderId  = isset($_GET['orderId']) ? intval($_GET['orderId']) : 0;
$farmerId = isset($_GET['farmerId']) ? intval($_GET['farmerId']) : 0;

if ($orderId <= 0 || $farmerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID and Farmer ID required']);
    exit();
}

$conn = getDBConnection();

// Get order info
$stmt = $conn->prepare("
    SELECT o.id, o.orderDate, o.totalAmount, o.shippingFee,
           o.deliveryMethod, o.deliveryAddress, o.recipientPhone,
           o.paymentMethod, o.status, o.notes,
           c.first_name AS customerFirstName,
           c.last_name AS customerLastName,
           c.phone AS customerPhone,
           c.email AS customerEmail,
           (SELECT SUM(oi2.lineTotal) FROM order_items oi2 WHERE oi2.orderId = o.id AND oi2.farmerId = ?) AS farmerTotal
    FROM orders o
    JOIN customers c ON o.customerId = c.id
    WHERE o.id = ?
");
$stmt->bind_param("ii", $farmerId, $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

$order = $result->fetch_assoc();
$stmt->close();

// Get order items for this farmer (now includes status)
$stmt = $conn->prepare("
    SELECT 
        oi.id,
        oi.productId,
        oi.quantity,
        oi.priceAtPurchase,
        oi.lineTotal,
        oi.status AS itemStatus,
        p.productName,
        p.imagePath,
        p.unitOfSale
    FROM order_items oi
    JOIN products p ON oi.productId = p.id
    WHERE oi.orderId = ? AND oi.farmerId = ?
");
$stmt->bind_param("ii", $orderId, $farmerId);
$stmt->execute();
$itemResult = $stmt->get_result();

$items = [];
$farmerSubtotal = 0;

while ($item = $itemResult->fetch_assoc()) {
    $lineTotal = floatval($item['lineTotal']);
    $farmerSubtotal += $lineTotal;
    
    $items[] = [
        'id'              => intval($item['id']),
        'productId'       => intval($item['productId']),
        'productName'     => $item['productName'],
        'imagePath'       => $item['imagePath'] ?? '',
        'unitOfSale'      => $item['unitOfSale'],
        'quantity'        => intval($item['quantity']),
        'priceAtPurchase' => floatval($item['priceAtPurchase']),
        'lineTotal'       => $lineTotal,
        'itemStatus'      => $item['itemStatus'] ?? 'Pending',
    ];
}
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'order' => [
        'orderId'          => intval($order['id']),
        'orderDate'        => $order['orderDate'],
        'totalAmount'      => floatval($order['totalAmount']),
        'shippingFee'      => floatval($order['shippingFee']),
        'farmerTotal'      => floatval($order['farmerTotal']),
        'farmerSubtotal'   => $farmerSubtotal,
        'deliveryMethod'   => $order['deliveryMethod'],
        'deliveryAddress'  => $order['deliveryAddress'] ?? '',
        'recipientPhone'   => $order['recipientPhone'] ?? '',
        'paymentMethod'    => $order['paymentMethod'],
        'status'           => $order['status'],
        'notes'            => $order['notes'] ?? '',
        'customerName'     => $order['customerFirstName'] . ' ' . $order['customerLastName'],
        'customerPhone'    => $order['customerPhone'] ?? '',
        'customerEmail'    => $order['customerEmail'] ?? '',
    ],
    'items' => $items,
]);
?>