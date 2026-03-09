<?php
// =============================================
// StockCrop API - Get Products (Marketplace)
// GET: ?category=1&search=tomato&farmer=1
// =============================================
require_once 'config.php';

$conn = getDBConnection();

$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$farmerId   = isset($_GET['farmer']) ? intval($_GET['farmer']) : 0;

$sql = "
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
        c.id AS categoryId,
        c.categoryName,
        f.first_name AS farmerFirstName,
        f.last_name  AS farmerLastName,
        f.parish     AS farmerParish
    FROM products p
    JOIN categories c ON p.categoryId = c.id
    JOIN farmers f    ON p.farmerId   = f.id
    WHERE p.isAvailable = 1
      AND p.stockQuantity > 0
";

$params     = [];
$paramTypes = '';

if ($categoryId > 0) {
    $sql        .= " AND p.categoryId = ?";
    $paramTypes .= 'i';
    $params[]    = $categoryId;
}

if (!empty($search)) {
    $sql        .= " AND (p.productName LIKE ? OR p.description LIKE ?)";
    $paramTypes .= 'ss';
    $searchTerm  = '%' . $search . '%';
    $params[]    = $searchTerm;
    $params[]    = $searchTerm;
}

if ($farmerId > 0) {
    $sql        .= " AND p.farmerId = ?";
    $paramTypes .= 'i';
    $params[]    = $farmerId;
}

$sql .= " ORDER BY p.productName ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}

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
        'imagePath'     => $row['imagePath'],
        'farmerId'      => intval($row['farmerId']),
        'categoryId'    => intval($row['categoryId']),
        'categoryName'  => $row['categoryName'],
        'farmerName'    => $row['farmerFirstName'] . ' ' . $row['farmerLastName'],
        'farmerParish'  => $row['farmerParish'],
    ];
}
$stmt->close();

// Get categories for filter
$catResult  = $conn->query("SELECT id, categoryName FROM categories ORDER BY categoryName ASC");
$categories = [];
while ($cat = $catResult->fetch_assoc()) {
    $categories[] = $cat;
}

$conn->close();

echo json_encode([
    'success'    => true,
    'products'   => $products,
    'categories' => $categories,
    'total'      => count($products),
]);
?>
