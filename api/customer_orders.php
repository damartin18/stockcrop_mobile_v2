<?php
require_once 'config.php';

header('Content-Type: application/json');

$conn = getDBConnection();
$customerId = isset($_GET['customerId']) ? intval($_GET['customerId']) : 0;

if ($customerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit();
}

$stmt = $conn->prepare("
    SELECT id, customerId, orderDate, totalAmount, shippingFee, 
           deliveryMethod, deliveryAddress, recipientPhone, 
           paymentMethod, status, notes, created_at, updated_at
    FROM orders
    WHERE customerId = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'id'              => intval($row['id']),
        'customerId'      => intval($row['customerId']),
        'orderDate'       => $row['orderDate'],
        'totalAmount'     => floatval($row['totalAmount']),
        'shippingFee'     => floatval($row['shippingFee']),
        'deliveryMethod'  => $row['deliveryMethod'],
        'deliveryAddress' => $row['deliveryAddress'],
        'recipientPhone'  => $row['recipientPhone'],
        'paymentMethod'   => $row['paymentMethod'],
        'status'          => $row['status'],
        'notes'           => $row['notes'],
        'created_at'      => $row['created_at'],
        'updated_at'      => $row['updated_at'],
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