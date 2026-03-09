<?php
require_once 'config.php';

$conn = getDBConnection();

$response = [
    'success' => true,
    'stats' => [],
    'weeklySales' => [0, 0, 0, 0, 0, 0, 0],
    'recentActivity' => []
];

// Total customers
$result = $conn->query("SELECT COUNT(*) AS total FROM customers");
$totalCustomers = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Total farmers
$result = $conn->query("SELECT COUNT(*) AS total FROM farmers");
$totalFarmers = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Pending farmers
$result = $conn->query("SELECT COUNT(*) AS total FROM farmers WHERE verification_status = 'pending'");
$pendingFarmers = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Total products
$result = $conn->query("SELECT COUNT(*) AS total FROM products");
$totalProducts = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Total orders
$result = $conn->query("SELECT COUNT(*) AS total FROM orders");
$totalOrders = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Pending orders
$result = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE LOWER(status) = 'pending'");
$pendingOrders = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Total revenue
$result = $conn->query("SELECT COALESCE(SUM(totalAmount), 0) AS total FROM orders WHERE LOWER(status) != 'cancelled'");
$totalRevenue = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Total categories
$result = $conn->query("SELECT COUNT(*) AS total FROM categories");
$totalCategories = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

$response['stats'] = [
    'totalCustomers'  => intval($totalCustomers),
    'totalFarmers'    => intval($totalFarmers),
    'pendingFarmers'  => intval($pendingFarmers),
    'totalProducts'   => intval($totalProducts),
    'totalOrders'     => intval($totalOrders),
    'pendingOrders'   => intval($pendingOrders),
    'totalRevenue'    => floatval($totalRevenue),
    'totalCategories' => intval($totalCategories),
];

// Weekly sales - safer query
$weeklyQuery = "
    SELECT DATE(created_at) AS sale_date, COALESCE(SUM(totalAmount), 0) AS total
    FROM orders
    WHERE LOWER(status) != 'cancelled'
      AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
";

$weeklyResult = $conn->query($weeklyQuery);

if ($weeklyResult) {
    $weeklySales = array_fill(0, 7, 0);

    while ($row = $weeklyResult->fetch_assoc()) {
        $saleDate = $row['sale_date'];
        $total = floatval($row['total']);

        $dayDiff = (strtotime(date('Y-m-d')) - strtotime($saleDate)) / 86400;
        $index = 6 - intval($dayDiff);

        if ($index >= 0 && $index < 7) {
            $weeklySales[$index] = $total;
        }
    }

    $response['weeklySales'] = $weeklySales;
}

// Recent activity - orders only for now
$orderActivityQuery = "
    SELECT id, created_at
    FROM orders
    ORDER BY created_at DESC
    LIMIT 5
";

$orderActivityResult = $conn->query($orderActivityQuery);

if ($orderActivityResult) {
    while ($row = $orderActivityResult->fetch_assoc()) {
        $response['recentActivity'][] = [
            'type' => 'order',
            'message' => 'Order #' . $row['id'] . ' Placed',
            'createdAt' => $row['created_at']
        ];
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($response);
?>