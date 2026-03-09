<?php
// =============================================
// StockCrop API - Cart Operations
// GET:    ?customerId=1              → get cart items
// POST:   { customerId, productId, quantity }  → add to cart
// PUT:    { cartItemId, quantity }   → update quantity
// DELETE: ?cartItemId=1              → remove item
// DELETE: ?customerId=1&action=clear → clear cart
// =============================================
require_once 'config.php';

$conn   = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ========== GET CART ==========
    case 'GET':
        $customerId = isset($_GET['customerId']) ? intval($_GET['customerId']) : 0;

        if ($customerId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT 
                ci.id AS cartItemId,
                ci.quantity,
                ci.bid_price,
                ci.bid_id,
                ci.bid_expires_at,
                p.id AS productId,
                p.productName,
                p.price,
                p.unitOfSale,
                p.stockQuantity,
                p.imagePath,
                p.farmerId,
                f.first_name AS farmerFirstName,
                f.last_name  AS farmerLastName
            FROM cartItems ci
            JOIN products p ON ci.productId = p.id
            JOIN farmers f  ON p.farmerId   = f.id
            WHERE ci.customerId = ?
            ORDER BY ci.addedAt DESC
        ");
        
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items    = [];
        $subtotal = 0;

        while ($row = $result->fetch_assoc()) {
            $hasBid = !empty($row['bid_price']) && $row['bid_price'] > 0;
            $expired = $hasBid && !empty($row['bid_expires_at']) && strtotime($row['bid_expires_at']) < time();
            $effectivePrice = ($hasBid && !$expired) ? floatval($row['bid_price']) : floatval($row['price']);
            $lineTotal = $effectivePrice * intval($row['quantity']);
            $subtotal += $lineTotal;

            $items[]   = [
                'cartItemId'     => intval($row['cartItemId']),
                'productId'      => intval($row['productId']),
                'productName'    => $row['productName'],
                'price'          => $effectivePrice,
                'originalPrice'  => floatval($row['price']),
                'quantity'       => intval($row['quantity']),
                'lineTotal'      => $lineTotal,
                'unitOfSale'     => $row['unitOfSale'],
                'stockQuantity'  => intval($row['stockQuantity']),
                'imagePath'      => $row['imagePath'],
                'farmerId'       => intval($row['farmerId']),
                'farmerName'     => $row['farmerFirstName'] . ' ' . $row['farmerLastName'],
                'bidPrice'       => $hasBid ? floatval($row['bid_price']) : null,
                'bidId'          => $row['bid_id'] ? intval($row['bid_id']) : null,
                'bidExpiresAt'   => $row['bid_expires_at'],
                'bidExpired'     => $expired,
            ];
        }
        $stmt->close();

        echo json_encode([
            'success'  => true,
            'items'    => $items,
            'subtotal' => $subtotal,
            'count'    => count($items),
        ]);
        break;

    // ========== ADD TO CART ==========
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        $customerId = isset($input['customerId']) ? intval($input['customerId']) : 0;
        $productId  = isset($input['productId']) ? intval($input['productId']) : 0;
        $quantity   = isset($input['quantity']) ? intval($input['quantity']) : 1;

        if ($customerId <= 0 || $productId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Customer ID and Product ID are required']);
            exit();
        }

        // Check stock
        $stockCheck = $conn->prepare("SELECT stockQuantity FROM products WHERE id = ? AND isAvailable = 1");
        $stockCheck->bind_param("i", $productId);
        $stockCheck->execute();
        $stockResult = $stockCheck->get_result();

        if ($stockResult->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Product not available']);
            exit();
        }

        $stock = $stockResult->fetch_assoc()['stockQuantity'];
        $stockCheck->close();

        if ($quantity > $stock) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
            exit();
        }

        // Insert or update quantity
        $stmt = $conn->prepare("
            INSERT INTO cartItems (customerId, productId, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->bind_param("iii", $customerId, $productId, $quantity);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Added to cart']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
        }
        $stmt->close();
        break;

    // ========== UPDATE QUANTITY ==========
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);

        $cartItemId = isset($input['cartItemId']) ? intval($input['cartItemId']) : 0;
        $quantity   = isset($input['quantity']) ? intval($input['quantity']) : 0;

        if ($cartItemId <= 0 || $quantity <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cart item ID and quantity required']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE cartItems SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $cartItemId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cart updated']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
        }
        $stmt->close();
        break;

    // ========== DELETE FROM CART ==========
    case 'DELETE':
        $action     = isset($_GET['action']) ? $_GET['action'] : '';
        $customerId = isset($_GET['customerId']) ? intval($_GET['customerId']) : 0;
        $cartItemId = isset($_GET['cartItemId']) ? intval($_GET['cartItemId']) : 0;

        if ($action === 'clear' && $customerId > 0) {
            $stmt = $conn->prepare("DELETE FROM cartItems WHERE customerId = ?");
            $stmt->bind_param("i", $customerId);
        } elseif ($cartItemId > 0) {
            $stmt = $conn->prepare("DELETE FROM cartItems WHERE id = ?");
            $stmt->bind_param("i", $cartItemId);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cart item ID or customer ID required']);
            exit();
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
        }
        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>
