<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
$conn = getDBConnection();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql = "SELECT o.*, 
        c.first_name, c.last_name, 
        c.email AS customerEmail, 
        c.phone AS customerPhone,
        (SELECT COUNT(*) FROM order_items WHERE orderId = o.id) AS itemCount
        FROM orders o
        JOIN customers c ON o.customerId = c.id
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($status)) {
    $sql .= " AND o.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (c.first_name LIKE ? 
                   OR c.last_name LIKE ? 
                   OR c.email LIKE ? 
                   OR o.id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'id' => (int)$row['id'],
        'customerId' => (int)$row['customerId'],
        'customerName' => $row['first_name'] . ' ' . $row['last_name'],
        'customerEmail' => $row['customerEmail'],
        'customerPhone' => $row['customerPhone'],
        'orderDate' => $row['orderDate'],
        'totalAmount' => (float)$row['totalAmount'],
        'shippingFee' => (float)$row['shippingFee'],
        'deliveryMethod' => $row['deliveryMethod'],
        'deliveryAddress' => $row['deliveryAddress'],
        'paymentMethod' => $row['paymentMethod'],
        'status' => $row['status'],
        'itemCount' => (int)$row['itemCount'],
        'created_at' => $row['created_at'],
    ];
}

/* ===== STATUS COUNTS ===== */

$countResult = $conn->query(
    "SELECT status, COUNT(*) as cnt FROM orders GROUP BY status"
);

$counts = [
    'pending' => 0,
    'confirmed' => 0,
    'processing' => 0,
    'ready for pickup' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

while ($row = $countResult->fetch_assoc()) {
    $statusKey = strtolower($row['status']);
    if (array_key_exists($statusKey, $counts)) {
        $counts[$statusKey] = (int)$row['cnt'];
    }
}

echo json_encode([
    'success' => true,
    'orders' => $orders,
    'total' => count($orders),
    'counts' => $counts,
]);

$stmt->close();
$conn->close();
?>