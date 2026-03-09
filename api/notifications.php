<?php
// =============================================
// StockCrop API - Notifications
// GET:  ?userId=1&userType=customer     → get notifications
// PUT:  { notificationId }              → mark as read
// PUT:  { userId, userType, action: "readAll" } → mark all as read
// =============================================
require_once 'config.php';

$conn   = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        $userId   = isset($_GET['userId']) ? intval($_GET['userId']) : 0;
        $userType = isset($_GET['userType']) ? trim($_GET['userType']) : '';

        if ($userId <= 0 || empty($userType)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID and type required']);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT id, title, message, isRead, created_at 
            FROM notifications 
            WHERE userId = ? AND userType = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->bind_param("is", $userId, $userType);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        $unreadCount   = 0;

        while ($row = $result->fetch_assoc()) {
            if (!$row['isRead']) $unreadCount++;
            $notifications[] = [
                'id'        => intval($row['id']),
                'title'     => $row['title'],
                'message'   => $row['message'],
                'isRead'    => (bool)$row['isRead'],
                'createdAt' => $row['created_at'],
            ];
        }
        $stmt->close();

        echo json_encode([
            'success'       => true,
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'total'         => count($notifications),
        ]);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['action']) && $input['action'] === 'readAll') {
            $userId   = isset($input['userId']) ? intval($input['userId']) : 0;
            $userType = isset($input['userType']) ? trim($input['userType']) : '';

            $stmt = $conn->prepare("UPDATE notifications SET isRead = 1 WHERE userId = ? AND userType = ?");
            $stmt->bind_param("is", $userId, $userType);
        } else {
            $notifId = isset($input['notificationId']) ? intval($input['notificationId']) : 0;
            $stmt    = $conn->prepare("UPDATE notifications SET isRead = 1 WHERE id = ?");
            $stmt->bind_param("i", $notifId);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification(s) marked as read']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update']);
        }
        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>
