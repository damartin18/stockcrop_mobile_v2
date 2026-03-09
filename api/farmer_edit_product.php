<?php
// =============================================
// StockCrop API - Farmer Edit Product
// PUT: { productId, productName, description, price, unitOfSale, stockQuantity, categoryId, imagePath, isAvailable }
// =============================================
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['productId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit();
}

$productId     = intval($input['productId']);
$productName   = isset($input['productName']) ? trim($input['productName']) : '';
$description   = isset($input['description']) ? trim($input['description']) : '';
$price         = isset($input['price']) ? floatval($input['price']) : 0.0;
$unitOfSale    = isset($input['unitOfSale']) ? trim($input['unitOfSale']) : '';
$stockQuantity = isset($input['stockQuantity']) ? intval($input['stockQuantity']) : 0;
$categoryId    = isset($input['categoryId']) ? intval($input['categoryId']) : 0;
$imagePath     = isset($input['imagePath']) ? trim($input['imagePath']) : '';
$isAvailable   = isset($input['isAvailable']) ? intval($input['isAvailable']) : 1;

if ($productId <= 0 || empty($productName) || $price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("
    UPDATE products SET
        productName   = ?,
        description   = ?,
        price         = ?,
        unitOfSale    = ?,
        stockQuantity = ?,
        categoryId    = ?,
        imagePath     = ?,
        isAvailable   = ?
    WHERE id = ?
");
$stmt->bind_param("ssdsiisii", $productName, $description, $price, $unitOfSale, $stockQuantity, $categoryId, $imagePath, $isAvailable, $productId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update product: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
