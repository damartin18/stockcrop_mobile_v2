<?php
// =============================================
// StockCrop API - Farmer Products
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

$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.productName,
        p.description,
        p.price,
        p.unitOfSale,
        p.stockQuantity,
        p.isAvailable,
        p.imagePath,
        c.categoryName
    FROM products p
    JOIN categories c ON p.categoryId = c.id
    WHERE p.farmerId = ?
    ORDER BY p.id DESC
");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$result   = $stmt->get_result();
$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = [
        'id'            => intval($row['id']),
        'productName'   => $row['productName'],
        'description'   => $row['description'],
        'price'         => floatval($row['price']),
        'unitOfSale'    => $row['unitOfSale'],
        'stockQuantity' => intval($row['stockQuantity']),
        'isAvailable'   => (bool)$row['isAvailable'],
        'imagePath'     => $row['imagePath'] ?? '',
        'categoryName'  => $row['categoryName'],
    ];
}
$stmt->close();
$conn->close();

echo json_encode([
    'success'  => true,
    'products' => $products,
    'total'    => count($products),
]);
?>
