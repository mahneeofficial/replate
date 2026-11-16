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
    error_log("Unhandled Exception in requests.php: " . $e->getMessage());
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

function recordAuditLog(PDO $pdo, ?int $userId, string $eventType, string $details, string $logType = 'info'): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, event_type, action_details, log_type, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $eventType, $details, $logType, $ip]);
    } catch (Throwable $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_01: Please log in to perform this action."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'my_requests';

        if ($action === 'incoming') {
            require __DIR__ . '/request_handlers/get_incoming.php';
            exit;
        }

        require __DIR__ . '/request_handlers/get_my_requests.php';
        exit;
    }

    if ($method === 'POST') {
        $rawInput = file_get_contents("php://input");
        $data = !empty($rawInput) ? json_decode($rawInput, true) : $_POST;

        if (!is_array($data)) {
            $data = $_POST;
        }

        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            require __DIR__ . '/request_handlers/create_request.php';
            exit;
        }

        if ($action === 'approve' || $action === 'reject') {
            require __DIR__ . '/request_handlers/manage_request.php';
            exit;
        }

        if ($action === 'cancel') {
            require __DIR__ . '/request_handlers/cancel_request.php';
            exit;
        }

        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_VAL_00: Invalid request action."]);
        exit;
    }

    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in requests.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "ERR_SYS_01: Database operation failed."
    ]);
}