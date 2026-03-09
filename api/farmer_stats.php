<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';

$farmerId = isset($_GET['farmerId']) ? intval($_GET['farmerId']) : 0;

if ($farmerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid farmer ID']);
    exit();
}

$conn = getDBConnection();

// Total products
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE farmerId = ?");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$totalProducts = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Active products
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE farmerId = ? AND isAvailable = 1");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$activeProducts = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Total orders containing farmer's products
$stmt = $conn->prepare("SELECT COUNT(DISTINCT orderId) AS total FROM order_items WHERE farmerId = ?");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$totalOrders = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Pending / In-progress orders
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT oi.orderId) AS total
    FROM order_items oi
    JOIN orders o ON oi.orderId = o.id
    WHERE oi.farmerId = ?
      AND LOWER(o.status) IN ('pending','confirmed','processing','ready for pickup','shipped')
");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$pendingOrders = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Total revenue (ALL completed orders, not just delivered)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(oi.lineTotal), 0) AS total
    FROM order_items oi
    JOIN orders o ON oi.orderId = o.id
    WHERE oi.farmerId = ?
      AND LOWER(o.status) NOT IN ('cancelled')
");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$totalRevenue = floatval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Order status breakdown
$orderStatus = [
  'Pending' => 0,
  'Processing' => 0,
  'Ready for Pickup' => 0,
  'Shipped' => 0,
  'Delivered' => 0,
  'Cancelled' => 0,
];

$stmt = $conn->prepare("
    SELECT LOWER(o.status) AS status, COUNT(DISTINCT oi.orderId) AS total
    FROM order_items oi
    JOIN orders o ON oi.orderId = o.id
    WHERE oi.farmerId = ?
    GROUP BY LOWER(o.status)
");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $status = strtolower($row['status']);
    $count = intval($row['total']);
    if ($status == 'pending') $orderStatus['Pending'] = $count;
    if ($status == 'processing') $orderStatus['Processing'] = $count;
    if ($status == 'ready for pickup') $orderStatus['Ready for Pickup'] = $count;
    if ($status == 'shipped') $orderStatus['Shipped'] = $count;
    if ($status == 'delivered') $orderStatus['Delivered'] = $count;
    if ($status == 'cancelled') $orderStatus['Cancelled'] = $count;
}
$stmt->close();

// Low stock products
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total FROM products
    WHERE farmerId = ? AND stockQuantity < 10 AND isAvailable = 1
");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$lowStock = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Revenue trend - last 30 days with zero-fill
$revenueTrend = [];
$days = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = 0.0;
}

$stmt = $conn->prepare("
    SELECT DATE(o.created_at) AS day, COALESCE(SUM(oi.lineTotal), 0) AS amount
    FROM order_items oi
    JOIN orders o ON oi.orderId = o.id
    WHERE oi.farmerId = ?
      AND LOWER(o.status) NOT IN ('cancelled')
      AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(o.created_at)
    ORDER BY day ASC
");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $day = $row['day'];
    if (isset($days[$day])) {
        $days[$day] = floatval($row['amount']);
    }
}
$stmt->close();

foreach ($days as $d => $amt) {
    $revenueTrend[] = ['date' => $d, 'amount' => $amt];
}

$conn->close();

echo json_encode([
    'success' => true,
    'stats' => [
        'totalProducts'  => $totalProducts,
        'activeProducts' => $activeProducts,
        'totalOrders'    => $totalOrders,
        'pendingOrders'  => $pendingOrders,
        'totalRevenue'   => $totalRevenue,
        'lowStock'       => $lowStock,
    ],
    'orderStatus'  => $orderStatus,
    'revenueTrend' => $revenueTrend,
]);
?>