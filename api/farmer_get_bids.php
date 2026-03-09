<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

error_reporting(0);
ini_set('display_errors', 0);

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

$bidId      = isset($input['bidId']) ? intval($input['bidId']) : 0;
$farmerId   = isset($input['farmerId']) ? intval($input['farmerId']) : 0;
$action     = isset($input['action']) ? strtolower(trim($input['action'])) : '';
$offerPrice = isset($input['offerPrice']) ? floatval($input['offerPrice']) : 0;
$message    = isset($input['message']) ? trim($input['message']) : '';

if ($bidId <= 0 || $farmerId <= 0 || !in_array($action, ['accept', 'reject', 'counter'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request: bidId, farmerId, and action (accept/reject/counter) required']);
    exit;
}

if ($action === 'counter' && $offerPrice <= 0) {
    echo json_encode(['success' => false, 'message' => 'Counter offer requires an offerPrice']);
    exit;
}

// Verify bid belongs to farmer and it's their turn
$stmt = $conn->prepare("SELECT * FROM bids WHERE id = ? AND farmerId = ? AND status = 'negotiating' AND currentTurn = 'farmer'");
$stmt->bind_param("ii", $bidId, $farmerId);
$stmt->execute();
$bid = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bid) {
    echo json_encode(['success' => false, 'message' => 'Bid not found, not your turn, or already closed']);
    exit;
}

$conn->begin_transaction();

try {
    if ($action === 'accept') {
        // Farmer accepts the customer's offer
        $acceptedPrice = floatval($bid['currentOffer']);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $conn->prepare("UPDATE bids SET status = 'accepted', acceptedPrice = ?, cartExpiresAt = ? WHERE id = ?");
        $stmt->bind_param("dsi", $acceptedPrice, $expiresAt, $bidId);
        $stmt->execute();
        $stmt->close();

        // Log accept
        $stmt = $conn->prepare("INSERT INTO bid_offers (bidId, offeredBy, offerPrice, message, action) VALUES (?, 'farmer', ?, ?, 'accept')");
        $stmt->bind_param("ids", $bidId, $acceptedPrice, $message);
        $stmt->execute();
        $stmt->close();

        // Add to customer's cart at the accepted price
        $customerId = intval($bid['customerId']);
        $productId = intval($bid['productId']);

        $existingCart = $conn->prepare("SELECT id FROM cart WHERE customer_id = ? AND product_id = ?");
        $existingCart->bind_param("ii", $customerId, $productId);
        $existingCart->execute();
        $cartRow = $existingCart->get_result()->fetch_assoc();
        $existingCart->close();

        if ($cartRow) {
            $cartStmt = $conn->prepare("UPDATE cart SET quantity = ?, bid_price = ?, bid_id = ?, bid_expires_at = ? WHERE id = ?");
            $cartStmt->bind_param("idisi", $bid['quantity'], $acceptedPrice, $bidId, $expiresAt, $cartRow['id']);
        } else {
            $cartStmt = $conn->prepare(
                "INSERT INTO cart (customer_id, product_id, quantity, bid_price, bid_id, bid_expires_at) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $cartStmt->bind_param("iiidis", $customerId, $productId, $bid['quantity'], $acceptedPrice, $bidId, $expiresAt);
        }
        $cartStmt->execute();
        $cartStmt->close();

        // Notify customer
        $notifMsg = "Your bid of $" . number_format($acceptedPrice, 2) . " was accepted! Check your cart — you have 1 hour to checkout.";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type, reference_id) VALUES (?, 'customer', 'Bid Accepted!', ?, 'bid_update', ?)");
        $stmt->bind_param("isi", $customerId, $notifMsg, $bidId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Bid accepted. Item added to customer cart with 1-hour expiry.']);

    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE bids SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $bidId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO bid_offers (bidId, offeredBy, offerPrice, message, action) VALUES (?, 'farmer', 0, ?, 'reject')");
        $stmt->bind_param("is", $bidId, $message);
        $stmt->execute();
        $stmt->close();

        // Notify customer
        $customerId = intval($bid['customerId']);
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type, reference_id) VALUES (?, 'customer', 'Bid Rejected', 'The farmer rejected your bid', 'bid_update', ?)");
        $stmt->bind_param("ii", $customerId, $bidId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Bid rejected']);

    } elseif ($action === 'counter') {
        $stmt = $conn->prepare("UPDATE bids SET currentOffer = ?, currentTurn = 'customer' WHERE id = ?");
        $stmt->bind_param("di", $offerPrice, $bidId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO bid_offers (bidId, offeredBy, offerPrice, message, action) VALUES (?, 'farmer', ?, ?, 'counter')");
        $stmt->bind_param("ids", $bidId, $offerPrice, $message);
        $stmt->execute();
        $stmt->close();

        // Notify customer
        $customerId = intval($bid['customerId']);
        $notifMsg = "Farmer countered with $" . number_format($offerPrice, 2);
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type, reference_id) VALUES (?, 'customer', 'Counter Offer', ?, 'bid_update', ?)");
        $stmt->bind_param("isi", $customerId, $notifMsg, $bidId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Counter offer sent to customer']);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to process response']);
}

$conn->close();
?>