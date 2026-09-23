<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in notifications.php: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Internal Server Error"]);
    exit;
});

$configDb = __DIR__ . '/../../config/db.php';
$configSecurity = __DIR__ . '/../../config/Security.php';

if (!file_exists($configDb)) {
    $configDb = __DIR__ . '/../config/db.php';
    $configSecurity = __DIR__ . '/../config/Security.php';
}

if (!file_exists($configDb)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_00: Database configuration missing."]);
    exit;
}

require_once $configDb;

if (file_exists($configSecurity)) {
    require_once $configSecurity;
    if (class_exists('Security') && method_exists('Security', 'applySecurityHeaders')) {
        Security::applySecurityHeaders();
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_00: Invalid database connection."]);
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_01: Unauthorized access."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;

        $stmt = $pdo->prepare("
            SELECT id, title, message, type, is_read, created_at 
            FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            "success" => true, 
            "status" => "success", 
            "notifications" => $notifications
        ]);
        exit;
    }

    if ($method === 'POST') {
        if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
            Security::enforceRateLimit('notification_action', 30, 60);
        }

        $rawInput = file_get_contents("php://input");
        $data = !empty($rawInput) ? json_decode($rawInput, true) : null;
        if (!is_array($data)) {
            $data = $_POST;
        }
        
        $action = trim((string)($data['action'] ?? ''));
        $notifId = isset($data['notification_id']) ? (int)$data['notification_id'] : (isset($data['id']) ? (int)$data['id'] : null);

        if ($action === 'create' || $action === 'add') {
            $title = trim((string)($data['title'] ?? ''));
            $message = trim((string)($data['message'] ?? ''));
            $type = trim((string)($data['type'] ?? 'info'));
            $targetUserId = isset($data['user_id']) ? (int)$data['user_id'] : $userId;

            if (empty($title) || empty($message)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_VAL_01: Title and message are required."]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$targetUserId, $title, $message, $type]);

            echo json_encode([
                "success" => true, 
                "status" => "success", 
                "notification_id" => (int)$pdo->lastInsertId()
            ]);
            exit;
        } elseif (($action === 'mark_read' || $action === 'read') && $notifId) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notifId, $userId]);
        } elseif ($action === 'mark_all_read') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
        } elseif (($action === 'delete_one' || $action === 'delete' || $action === 'dismiss') && $notifId) {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notifId, $userId]);
        } elseif ($action === 'clear_all') {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$userId]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_01: Invalid action or missing notification ID."]);
            exit;
        }

        echo json_encode(["success" => true, "status" => "success"]);
        exit;
    }

    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);

} catch (Throwable $e) {
    error_log("Error in notifications.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "error" => "ERR_SYS_01: Database operation failed."
    ]);
    exit;
}