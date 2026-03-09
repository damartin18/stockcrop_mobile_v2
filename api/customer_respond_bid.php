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
$customerId = isset($input['customerId']) ? intval($input['customerId']) : 0;
$action     = isset($input['action']) ? strtolower(trim($input['action'])) : '';
$offerPrice = isset($input['offerPrice']) ? floatval($input['offerPrice']) : 0;
$message    = isset($input['message']) ? trim($input['message']) : '';

if ($bidId <= 0 || $customerId <= 0 || !in_array($action, ['accept', 'reject', 'counter'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if ($action === 'counter' && $offerPrice <= 0) {
    echo json_encode(['success' => false, 'message' => 'Counter offer requires an offerPrice']);
    exit;
}

if ($action === 'counter') {
    $countCheck = $conn->prepare("SELECT COUNT(*) AS cnt FROM bid_offers WHERE bidId = ? AND offeredBy = 'customer' AND action = 'counter'");
    $countCheck->bind_param("i", $bidId);
    $countCheck->execute();
    $countRow = $countCheck->get_result()->fetch_assoc();
    $countCheck->close();
    if (intval($countRow['cnt']) >= 1) {
        echo json_encode(['success' => false, 'message' => 'You can only counter once']);
        exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM bids WHERE id = ? AND customerId = ? AND status = 'negotiating' AND currentTurn = 'customer'");
$stmt->bind_param("ii", $bidId, $customerId);
$stmt->execute();
$bid = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bid) {
    echo json_encode(['success' => false, 'message' => 'Bid not found, not your turn, or already closed']);
    exit;
}

$farmerId = intval($bid['farmerId']);
$conn->begin_transaction();

try {
    if ($action === 'accept') {
        $acceptedPrice = floatval($bid['currentOffer']);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $conn->prepare("UPDATE bids SET status = 'accepted', acceptedPrice = ?, cartExpiresAt = ? WHERE id = ?");
        $stmt->bind_param("dsi", $acceptedPrice, $expiresAt, $bidId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO bid_offers (bidId, offeredBy, offerPrice, message, action) VALUES (?, 'customer', ?, ?, 'accept')");
        $stmt->bind_param("ids", $bidId, $acceptedPrice, $message);
        $stmt->execute();
        $stmt->close();

       $existingCart = $conn->prepare("SELECT id FROM cartitems WHERE customerId = ? AND productId = ?");
        $existingCart->bind_param("ii", $customerId, $bid['productId']);
        $existingCart->execute();
        $cartRow = $existingCart->get_result()->fetch_assoc();
        $existingCart->close();

        if ($cartRow) {
            $cartStmt = $conn->prepare("UPDATE cartitems SET quantity = ?, bid_price = ?, bid_id = ?, bid_expires_at = ? WHERE id = ?");
            $cartStmt->bind_param("idisi", $bid['quantity'], $acceptedPrice, $bidId, $expiresAt, $cartRow['id']);
        } else {
            $cartStmt = $conn->prepare("INSERT INTO cartitems (customerId, productId, quantity, bid_price, bid_id, bid_expires_at) VALUES (?, ?, ?, ?, ?, ?)");
            $cartStmt->bind_param("iiidis", $customerId, $bid['productId'], $bid['quantity'], $acceptedPrice, $bidId, $expiresAt);
        }
        $cartStmt->execute();
        $cartStmt->close();

        $notifMsg = "Customer accepted your offer of $" . number_format($acceptedPrice, 2);
        $stmt = $conn->prepare("INSERT INTO notifications (userId, userType, title, message) VALUES (?, 'farmer', 'Bid Accepted', ?)");
        $stmt->bind_param("is", $farmerId, $notifMsg);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Offer accepted! Item added to cart. You have 1 hour to checkout.', 'acceptedPrice' => $acceptedPrice, 'cartExpiresAt' => $expiresAt]);

    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE bids SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $bidId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO bid_offers (bidId, offeredBy, offerPrice, message, action) VALUES (?, 'customer', 0, ?, 'reject')");
        $stmt->bind_param("is", $bidId, $message);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO notifications (userId, userType, title, message) VALUES (?, 'farmer', 'Bid Rejected', 'Customer rejected your counter offer')");
        $stmt->bind_param("i", $farmerId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Bid rejected']);

    } elseif ($action === 'counter') {
        $stmt = $conn->prepare("UPDATE bids SET currentOffer = ?, currentTurn = 'farmer' WHERE id = ?");
        $stmt->bind_param("di", $offerPrice, $bidId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO bid_offers (bidId, offeredBy, offerPrice, message, action) VALUES (?, 'customer', ?, ?, 'counter')");
        $stmt->bind_param("ids", $bidId, $offerPrice, $message);
        $stmt->execute();
        $stmt->close();

        $notifMsg = "Customer countered with $" . number_format($offerPrice, 2);
        $stmt = $conn->prepare("INSERT INTO notifications (userId, userType, title, message) VALUES (?, 'farmer', 'Counter Offer', ?)");
        $stmt->bind_param("is", $farmerId, $notifMsg);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Counter offer sent']);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed: ' . $e->getMessage()]);
}

$conn->close();
?>