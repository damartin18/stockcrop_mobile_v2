<?php
// =============================================
// StockCrop API - Farmer Add Product
// POST: { farmerId, productName, description, price, unitOfSale, stockQuantity, categoryId, imagePath }
// =============================================
require_once 'config.php';
$conn = getDBConnection();

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['farmerId', 'productName', 'description', 'price', 'unitOfSale', 'stockQuantity', 'categoryId'];
foreach ($required as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$farmerId      = intval($data['farmerId']);
$productName   = trim($data['productName']);
$description   = trim($data['description']);
$price         = floatval($data['price']);
$unitOfSale    = trim($data['unitOfSale']);
$stockQuantity = intval($data['stockQuantity']);
$categoryId    = intval($data['categoryId']);
$imagePath     = isset($data['imagePath']) ? trim($data['imagePath']) : '';

// Verify farmer exists
$check = $conn->prepare("SELECT id FROM farmers WHERE id = ?");
$check->bind_param('i', $farmerId);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Farmer not found']);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// Insert product
$sql = "INSERT INTO products (farmerId, productName, description, price, unitOfSale, stockQuantity, categoryId, imagePath, isAvailable)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('issdssis', $farmerId, $productName, $description, $price, $unitOfSale, $stockQuantity, $categoryId, $imagePath);

if ($stmt->execute()) {
    $newProductId = $stmt->insert_id;
    echo json_encode([
        'success'   => true,
        'message'   => 'Product added successfully',
        'productId' => $newProductId,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add product: ' . $stmt->error,
    ]);
}

$stmt->close();
$conn->close();
?>