<?php
// =============================================
// StockCrop API - Farmer Delete Product
// DELETE: ?productId=1&farmerId=1
// =============================================
require_once 'config.php';

$productId = isset($_GET['productId']) ? intval($_GET['productId']) : 0;
$farmerId  = isset($_GET['farmerId']) ? intval($_GET['farmerId']) : 0;

if ($productId <= 0 || $farmerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID and Farmer ID are required']);
    exit();
}

$conn = getDBConnection();

// Verify product belongs to farmer
$check = $conn->prepare("SELECT id FROM products WHERE id = ? AND farmerId = ?");
$check->bind_param("ii", $productId, $farmerId);
$check->execute();

if ($check->get_result()->num_rows === 0) {
    $check->close();
    $conn->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Product not found or not authorized']);
    exit();
}
$check->close();

$stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND farmerId = ?");
$stmt->bind_param("ii", $productId, $farmerId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
}

$stmt->close();
$conn->close();
?>
