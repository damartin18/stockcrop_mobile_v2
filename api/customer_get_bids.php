<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$customerId = isset($_GET['customerId']) ? intval($_GET['customerId']) : 0;

if ($customerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit;
}

// Auto-expire bids where cart time has passed
$conn->query("UPDATE bids SET status = 'expired' WHERE status = 'accepted' AND cartExpiresAt IS NOT NULL AND cartExpiresAt < NOW()");

// Get all negotiations for this customer
$stmt = $conn->prepare(
    "SELECT b.id, b.productId, b.farmerId, b.quantity, b.originalPrice, b.currentOffer,
            b.currentTurn, b.status, b.acceptedPrice, b.cartExpiresAt, b.created_at, b.updated_at,
            p.productName, p.imagePath, p.unitOfSale,
            CONCAT(f.first_name, ' ', f.last_name) AS farmerName, f.farm_name AS farmName
     FROM bids b
     JOIN products p ON b.productId = p.id
     JOIN farmers f ON b.farmerId = f.id
     WHERE b.customerId = ?
     ORDER BY b.updated_at DESC"
);
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$bids = [];
while ($row = $result->fetch_assoc()) {
    $bidId = intval($row['id']);

    // Get offer history for this bid
    $offerStmt = $conn->prepare(
        "SELECT id, offeredBy, offerPrice, message, action, created_at
         FROM bid_offers WHERE bidId = ? ORDER BY created_at ASC"
    );
    $offerStmt->bind_param("i", $bidId);
    $offerStmt->execute();
    $offerResult = $offerStmt->get_result();

    $offers = [];
    while ($offerRow = $offerResult->fetch_assoc()) {
        $offers[] = $offerRow;
    }
    $offerStmt->close();

    $row['offers'] = $offers;
    $bids[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'bids' => $bids]);
?>