<?php
// =============================================
// StockCrop API - Farmer Orders (with items)
// GET: ?farmerId=1
// =============================================
require_once 'config.php';

$farmerId = isset($_GET['farmerId']) ? intval($_GET['farmerId']) : 0;

if ($farmerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid farmer ID']);
    exit();
}

$conn = getDBConnection();

// Get orders that contain this farmer's products
$stmt = $conn->prepare("
    SELECT DISTINCT
        o.id AS orderId,
        o.orderDate,
        o.totalAmount,
        o.deliveryMethod,
        o.deliveryAddress,
        o.recipientPhone,
        o.paymentMethod,
        o.status,
        o.notes,
        c.first_name AS customerFirstName,
        c.last_name  AS customerLastName,
        c.phone AS customerPhone,
        c.email AS customerEmail,
        (SELECT SUM(oi2.lineTotal) 
         FROM order_items oi2 
         WHERE oi2.orderId = o.id AND oi2.farmerId = ?) AS farmerTotal
    FROM orders o
    JOIN order_items oi ON o.id = oi.orderId
    JOIN customers c    ON o.customerId = c.id
    WHERE oi.farmerId = ?
    ORDER BY o.orderDate DESC
");
$stmt->bind_param("ii", $farmerId, $farmerId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orderId = intval($row['orderId']);

    // Get items for this order that belong to this farmer
    $itemStmt = $conn->prepare("
        SELECT 
            oi.id AS itemId,
            oi.productId,
            oi.quantity,
            oi.priceAtPurchase,
            oi.lineTotal,
            p.productName,
            p.imagePath,
            p.unitOfSale
        FROM order_items oi
        JOIN products p ON oi.productId = p.id
        WHERE oi.orderId = ? AND oi.farmerId = ?
        ORDER BY oi.id ASC
    ");
    $itemStmt->bind_param("ii", $orderId, $farmerId);
    $itemStmt->execute();
    $itemResult = $itemStmt->get_result();

    $items = [];
    while ($item = $itemResult->fetch_assoc()) {
        $items[] = [
            'itemId'          => intval($item['itemId']),
            'productId'       => intval($item['productId']),
            'productName'     => $item['productName'],
            'imagePath'       => $item['imagePath'],
            'unitOfSale'      => $item['unitOfSale'],
            'quantity'        => intval($item['quantity']),
            'priceAtPurchase' => floatval($item['priceAtPurchase']),
            'lineTotal'       => floatval($item['lineTotal']),
        ];
    }
    $itemStmt->close();

    $orders[] = [
        'orderId'         => $orderId,
        'orderDate'       => $row['orderDate'],
        'totalAmount'     => floatval($row['totalAmount']),
        'farmerTotal'     => floatval($row['farmerTotal']),
        'deliveryMethod'  => $row['deliveryMethod'],
        'deliveryAddress' => $row['deliveryAddress'],
        'recipientPhone'  => $row['recipientPhone'],
        'paymentMethod'   => $row['paymentMethod'],
        'status'          => $row['status'],
        'notes'           => $row['notes'],
        'customerName'    => $row['customerFirstName'] . ' ' . $row['customerLastName'],
        'customerPhone'   => $row['customerPhone'],
        'customerEmail'   => $row['customerEmail'],
        'items'           => $items,
        'itemCount'       => count($items),
    ];
}
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'orders'  => $orders,
    'total'   => count($orders),
]);
?>