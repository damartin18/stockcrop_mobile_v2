<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$password = "";
$database = "stockcrop_mobile_v2";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}
$conn->set_charset("utf8mb4");

$input = json_decode(file_get_contents('php://input'), true);

$productId  = isset($input['productId']) ? intval($input['productId']) : 0;
$customerId = isset($input['customerId']) ? intval($input['customerId']) : 0;
$offerPrice = isset($input['offerPrice']) ? floatval($input['offerPrice']) : 0;
$quantity   = isset($input['quantity']) ? intval($input['quantity']) : 1;
$message    = isset($input['message']) ? trim($input['message']) : '';

if ($productId <= 0 || $customerId <= 0 || $offerPrice <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid bid data: productId, customerId, and offerPrice are required']);
    exit;
}

$stmt = $conn->prepare("SELECT id, farmerId, productName, price, allowBids, minBidPrice FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

if (!$product['allowBids']) {
    echo json_encode(['success' => false, 'message' => 'This product does not accept bids']);
    exit;
}

if ($product['minBidPrice'] && $offerPrice < floatval($product['minBidPrice'])) {
    echo json_encode(['success' => false, 'message' => 'Bid must be at least $' . number_format($product['minBidPrice'], 2)]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM bids WHERE productId = ? AND customerId = ? AND status = 'negotiating'");
$stmt->bind_param("ii", $productId, $customerId);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'You already have an active negotiation on this product']);
    exit;
}
$stmt->close();

$farmerId = intval($product['farmerId']);
$originalPrice = floatval($product['price']);

$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        "INSERT INTO bids (productId, customerId, farmerId, quantity, originalPrice, currentOffer, currentTurn, status)
         VALUES (?, ?, ?, ?, ?, ?, 'farmer', 'negotiating')"
    );
    $stmt->bind_param("iiiidd", $productId, $customerId, $farmerId, $quantity, $originalPrice, $offerPrice);
    $stmt->execute();
    $bidId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO bid_offers (bidId, offeredBy, offerPrice, message, action) VALUES (?, 'customer', ?, ?, 'offer')"
    );
    $stmt->bind_param("ids", $bidId, $offerPrice, $message);
    $stmt->execute();
    $stmt->close();

    $notifMsg = "New bid of $" . number_format($offerPrice, 2) . " on '" . $product['productName'] . "'";
    $stmt = $conn->prepare(
        "INSERT INTO notifications (userId, userType, title, message)
         VALUES (?, 'farmer', 'New Bid Received', ?)"
    );
    $stmt->bind_param("is", $farmerId, $notifMsg);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Bid placed successfully', 'bidId' => $bidId]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to place bid: ' . $e->getMessage()]);
}

$conn->close();
?>