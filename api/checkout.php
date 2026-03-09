<?php
// =============================================
// StockCrop API - Checkout (Place Order)
// POST JSON:
// {
//   customerId,
//   deliveryMethod,
//   deliveryAddress,
//   recipientPhone,
//   paymentMethod,
//   shippingFee,
//   notes
// }
// =============================================
require_once 'config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No input received']);
    exit();
}

$customerId      = isset($input['customerId']) ? intval($input['customerId']) : 0;
$deliveryMethod  = isset($input['deliveryMethod']) ? trim($input['deliveryMethod']) : '';
$deliveryAddress = isset($input['deliveryAddress']) ? trim($input['deliveryAddress']) : '';
$recipientPhone  = isset($input['recipientPhone']) ? trim($input['recipientPhone']) : '';
$paymentMethod   = isset($input['paymentMethod']) ? trim($input['paymentMethod']) : '';
$shippingFee     = isset($input['shippingFee']) ? floatval($input['shippingFee']) : 0.0;
$notes           = isset($input['notes']) ? trim($input['notes']) : '';

if ($customerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit();
}

// Optional safety: require address if Delivery
if (strtolower($deliveryMethod) === 'delivery' && $deliveryAddress === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Delivery address is required for Delivery']);
    exit();
}

$conn = getDBConnection();

// Get cart items
$cartStmt = $conn->prepare("
    SELECT ci.productId, ci.quantity, ci.bid_price, ci.bid_expires_at, p.price, p.farmerId, p.stockQuantity, p.productName
    FROM cartItems ci
    JOIN products p ON ci.productId = p.id
    WHERE ci.customerId = ?
");
$cartStmt->bind_param("i", $customerId);
$cartStmt->execute();
$cartResult = $cartStmt->get_result();

if ($cartResult->num_rows === 0) {
    $cartStmt->close();
    $conn->close();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit();
}

$cartItems   = [];
$subtotal    = 0.0;

while ($item = $cartResult->fetch_assoc()) {
    $qty   = intval($item['quantity']);
    $stock = intval($item['stockQuantity']);

    // Verify stock
    if ($qty > $stock) {
        $cartStmt->close();
        $conn->close();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Not enough stock for "' . $item['productName'] . '". Available: ' . $stock
        ]);
        exit();
    }

   // Use bid price if available and not expired
    $hasBid = !empty($item['bid_price']) && floatval($item['bid_price']) > 0;
    $bidExpired = $hasBid && !empty($item['bid_expires_at']) && strtotime($item['bid_expires_at']) < time();
    $price = ($hasBid && !$bidExpired) ? floatval($item['bid_price']) : floatval($item['price']);
    $lineTotal = $price * $qty;

    $subtotal += $lineTotal;

    $cartItems[] = [
        'productId' => intval($item['productId']),
        'farmerId'  => intval($item['farmerId']),
        'quantity'  => $qty,
        'price'     => $price,
        'lineTotal' => $lineTotal,
    ];
}
$cartStmt->close();

$totalAmount = $subtotal + $shippingFee;

// Start transaction
$conn->begin_transaction();

try {
    // Create order (✅ includes shippingFee)
    $orderStmt = $conn->prepare("
        INSERT INTO orders
          (customerId, orderDate, totalAmount, shippingFee, deliveryMethod, deliveryAddress, recipientPhone, paymentMethod, status, notes)
        VALUES
          (?, NOW(), ?, ?, ?, ?, ?, ?, 'pending', ?)
    ");
    if (!$orderStmt) throw new Exception("Prepare failed (orders): " . $conn->error);

    $orderStmt->bind_param(
        "iddsssss",
        $customerId,
        $totalAmount,
        $shippingFee,
        $deliveryMethod,
        $deliveryAddress,
        $recipientPhone,
        $paymentMethod,
        $notes
    );

    if (!$orderStmt->execute()) throw new Exception("Execute failed (orders): " . $orderStmt->error);

    $orderId = $conn->insert_id;
    $orderStmt->close();

    // Add order items & update stock
    $itemStmt = $conn->prepare("
        INSERT INTO order_items (orderId, productId, farmerId, quantity, priceAtPurchase, lineTotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    if (!$itemStmt) throw new Exception("Prepare failed (order_items): " . $conn->error);

    $stockStmt = $conn->prepare("
        UPDATE products
        SET stockQuantity = stockQuantity - ?
        WHERE id = ? AND stockQuantity >= ?
    ");
    if (!$stockStmt) throw new Exception("Prepare failed (stock update): " . $conn->error);

    foreach ($cartItems as $it) {
        $itemStmt->bind_param(
            "iiiidd",
            $orderId,
            $it['productId'],
            $it['farmerId'],
            $it['quantity'],
            $it['price'],
            $it['lineTotal']
        );
        if (!$itemStmt->execute()) throw new Exception("Execute failed (order_items): " . $itemStmt->error);

        // Prevent negative stock
        $qty = $it['quantity'];
        $pid = $it['productId'];

        $stockStmt->bind_param("iii", $qty, $pid, $qty);
        if (!$stockStmt->execute()) throw new Exception("Execute failed (stock update): " . $stockStmt->error);

        if ($stockStmt->affected_rows <= 0) {
            throw new Exception("Stock update failed: insufficient stock for productId $pid");
        }
    }

    $itemStmt->close();
    $stockStmt->close();

    // Clear cart
    $clearCart = $conn->prepare("DELETE FROM cartItems WHERE customerId = ?");
    if (!$clearCart) throw new Exception("Prepare failed (clear cart): " . $conn->error);

    $clearCart->bind_param("i", $customerId);
    if (!$clearCart->execute()) throw new Exception("Execute failed (clear cart): " . $clearCart->error);
    $clearCart->close();

    // Notification for customer
    $notifStmt = $conn->prepare("
        INSERT INTO notifications (userId, userType, title, message)
        VALUES (?, 'customer', 'Order Placed', ?)
    ");
    if ($notifStmt) {
        $notifMsg = 'Your order #' . $orderId . ' has been placed successfully. Total: JMD $' . number_format($totalAmount, 2);
        $notifStmt->bind_param("is", $customerId, $notifMsg);
        $notifStmt->execute();
        $notifStmt->close();
    }

    $conn->commit();

    echo json_encode([
        'success'     => true,
        'message'     => 'Order placed successfully',
        'orderId'     => $orderId,
        'subtotal'    => $subtotal,
        'shippingFee' => $shippingFee,
        'totalAmount' => $totalAmount,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Order failed: ' . $e->getMessage()]);
}

$conn->close();
?>