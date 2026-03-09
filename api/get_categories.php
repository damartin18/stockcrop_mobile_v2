<?php
// =============================================
// StockCrop API - Get Categories
// GET: (no params)
// =============================================
require_once 'config.php';

$conn   = getDBConnection();
$result = $conn->query("SELECT id, categoryName FROM categories ORDER BY categoryName ASC");

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'id'           => intval($row['id']),
        'categoryName' => $row['categoryName'],
    ];
}

$conn->close();

echo json_encode([
    'success'    => true,
    'categories' => $categories,
    'total'      => count($categories),
]);
?>
