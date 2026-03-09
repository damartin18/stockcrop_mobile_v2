<?php
// =============================================
// StockCrop API - Order Details
// GET: ?orderId=1
// =============================================
require_once 'config.php';

$orderId = isset($_GET['orderId']) ? intval($_GET['orderId']) : 0;

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$conn = getDBConnection();

// Get order info
$orderStmt = $conn->prepare("
    SELECT 
        o.*,
        c.first_name AS customerFirstName,
        c.last_name  AS customerLastName,
        c.phone      AS customerPhone,
        c.email      AS customerEmail
    FROM orders o
    JOIN customers c ON o.customerId = c.id
    WHERE o.id = ?
");
$orderStmt->bind_param("i", $orderId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();

if ($orderResult->num_rows === 0) {
    $orderStmt->close();
    $conn->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

$order = $orderResult->fetch_assoc();
$orderStmt->close();

// Get order items
$itemsStmt = $conn->prepare("
    SELECT 
        oi.id,
        oi.productId,
        oi.quantity,
        oi.priceAtPurchase,
        oi.lineTotal,
        p.productName,
        p.imagePath,
        p.unitOfSale,
        f.first_name AS farmerFirstName,
        f.last_name  AS farmerLastName
    FROM order_items oi
    JOIN products p ON oi.productId = p.id
    JOIN farmers f  ON oi.farmerId  = f.id
    WHERE oi.orderId = ?
");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = [
        'id'              => intval($item['id']),
        'productId'       => intval($item['productId']),
        'productName'     => $item['productName'],
        'quantity'        => intval($item['quantity']),
        'priceAtPurchase' => floatval($item['priceAtPurchase']),
        'lineTotal'       => floatval($item['lineTotal']),
        'imagePath'       => $item['imagePath'],
        'unitOfSale'      => $item['unitOfSale'],
        'farmerName'      => $item['farmerFirstName'] . ' ' . $item['farmerLastName'],
    ];
}
$itemsStmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'order'   => [
        'orderId'         => intval($order['id']),
        'orderDate'       => $order['orderDate'],
        'totalAmount'     => floatval($order['totalAmount']),
        'shippingFee'     => floatval($order['shippingFee']),
        'deliveryMethod'  => $order['deliveryMethod'],
        'deliveryAddress' => $order['deliveryAddress'],
        'recipientPhone'  => $order['recipientPhone'],
        'paymentMethod'   => $order['paymentMethod'],
        'status'          => $order['status'],
        'notes'           => $order['notes'],
        'customerName'    => $order['customerFirstName'] . ' ' . $order['customerLastName'],
        'customerPhone'   => $order['customerPhone'],
        'customerEmail'   => $order['customerEmail'],
    ],
    'items' => $items,
]);
?>
