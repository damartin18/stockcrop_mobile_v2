<?php
// =============================================
// StockCrop API - Farmer Stall Card / QR Code
// GET: ?farmerId=1            → returns QR code data + farmer info as JSON
// GET: ?farmerId=1&format=png → returns QR code as PNG image
// =============================================
require_once 'config.php';

$conn = getDBConnection();

$farmerId = isset($_GET['farmerId']) ? intval($_GET['farmerId']) : 0;
$format   = isset($_GET['format']) ? trim($_GET['format']) : 'json';

if ($farmerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Farmer ID required']);
    exit;
}

// Get farmer info
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, phone, farm_name, parish
    FROM farmers WHERE id = ?
");
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Farmer not found']);
    $stmt->close();
    $conn->close();
    exit;
}

$farmer = $result->fetch_assoc();
$stmt->close();

// Get product count
$pStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE farmerId = ? AND isAvailable = 1");
$pStmt->bind_param("i", $farmerId);
$pStmt->execute();
$pResult = $pStmt->get_result();
$productCount = $pResult->fetch_assoc()['cnt'] ?? 0;
$pStmt->close();

// Build profile URL (adjust domain as needed)
$profileUrl = "https://stockcrop.onrender.com/farmerProfile.php?id=" . $farmerId;

// QR Code image URL via external API
$qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($profileUrl);

if ($format === 'png') {
    // Proxy the QR image
    header('Content-Type: image/png');
    header('Access-Control-Allow-Origin: *');
    echo file_get_contents($qrImageUrl);
    exit;
}

// Return JSON with farmer info + QR data
echo json_encode([
    'success'      => true,
    'farmer'       => [
        'id'        => (int) $farmer['id'],
        'firstName' => $farmer['first_name'],
        'lastName'  => $farmer['last_name'],
        'farmName'  => $farmer['farm_name'] ?? '',
        'parish'    => $farmer['parish'] ?? '',
        'phone'     => $farmer['phone'] ?? '',
        'email'     => $farmer['email'] ?? '',
    ],
    'productCount' => (int) $productCount,
    'profileUrl'   => $profileUrl,
    'qrImageUrl'   => $qrImageUrl,
]);

$conn->close();
?>