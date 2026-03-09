<?php
// =============================================
// StockCrop API - Get Product Details
// GET: ?id=1
// =============================================
require_once 'config.php';

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
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
        p.farmerId,
        p.created_at,
        c.id AS categoryId,
        c.categoryName,
        f.first_name AS farmerFirstName,
        f.last_name  AS farmerLastName,
        f.farm_name  AS farmName,
        f.parish     AS farmerParish,
        f.phone      AS farmerPhone
    FROM products p
    JOIN categories c ON p.categoryId = c.id
    JOIN farmers f    ON p.farmerId   = f.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'product' => [
        'id'            => intval($row['id']),
        'productName'   => $row['productName'],
        'description'   => $row['description'],
        'price'         => floatval($row['price']),
        'unitOfSale'    => $row['unitOfSale'],
        'stockQuantity' => intval($row['stockQuantity']),
        'isAvailable'   => (bool)$row['isAvailable'],
        'imagePath'     => $row['imagePath'],
        'farmerId'      => intval($row['farmerId']),
        'categoryId'    => intval($row['categoryId']),
        'categoryName'  => $row['categoryName'],
        'farmerName'    => $row['farmerFirstName'] . ' ' . $row['farmerLastName'],
        'farmName'      => $row['farmName'],
        'farmerParish'  => $row['farmerParish'],
        'farmerPhone'   => $row['farmerPhone'],
        'createdAt'     => $row['created_at'],
    ]
]);
?>
