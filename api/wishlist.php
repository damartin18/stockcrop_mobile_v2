<?php
// =============================================
// StockCrop API - Wishlist
// GET:    ?customerId=1           → get wishlist
// POST:   { customerId, productId } → add to wishlist
// DELETE: ?customerId=1&productId=1 → remove from wishlist
// =============================================
require_once 'config.php';

$conn   = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        $customerId = isset($_GET['customerId']) ? intval($_GET['customerId']) : 0;

        if ($customerId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT 
                w.id AS wishlistId,
                w.addedAt,
                p.id AS productId,
                p.productName,
                p.price,
                p.unitOfSale,
                p.stockQuantity,
                p.isAvailable,
                p.imagePath,
                f.first_name AS farmerFirstName,
                f.last_name  AS farmerLastName
            FROM wishlist w
            JOIN products p ON w.productId = p.id
            JOIN farmers f  ON p.farmerId  = f.id
            WHERE w.customerId = ?
            ORDER BY w.addedAt DESC
        ");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'wishlistId'    => intval($row['wishlistId']),
                'productId'     => intval($row['productId']),
                'productName'   => $row['productName'],
                'price'         => floatval($row['price']),
                'unitOfSale'    => $row['unitOfSale'],
                'stockQuantity' => intval($row['stockQuantity']),
                'isAvailable'   => (bool)$row['isAvailable'],
                'imagePath'     => $row['imagePath'],
                'farmerName'    => $row['farmerFirstName'] . ' ' . $row['farmerLastName'],
                'addedAt'       => $row['addedAt'],
            ];
        }
        $stmt->close();

        echo json_encode(['success' => true, 'items' => $items, 'total' => count($items)]);
        break;

    case 'POST':
        $input      = json_decode(file_get_contents('php://input'), true);
        $customerId = isset($input['customerId']) ? intval($input['customerId']) : 0;
        $productId  = isset($input['productId']) ? intval($input['productId']) : 0;

        if ($customerId <= 0 || $productId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Customer ID and Product ID required']);
            exit();
        }

        $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (customerId, productId) VALUES (?, ?)");
        $stmt->bind_param("ii", $customerId, $productId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist']);
        }
        $stmt->close();
        break;

    case 'DELETE':
        $customerId = isset($_GET['customerId']) ? intval($_GET['customerId']) : 0;
        $productId  = isset($_GET['productId']) ? intval($_GET['productId']) : 0;

        if ($customerId <= 0 || $productId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Customer ID and Product ID required']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM wishlist WHERE customerId = ? AND productId = ?");
        $stmt->bind_param("ii", $customerId, $productId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to remove from wishlist']);
        }
        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>
