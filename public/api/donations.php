<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in donations.php: " . $e->getMessage());
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

$method = $_SERVER['REQUEST_METHOD'];
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        if ($action === 'metrics') {
            require __DIR__ . '/donation_handlers/get_metrics.php';
            exit;
        }
        if ($action === 'my_listings') {
            require __DIR__ . '/donation_handlers/get_my_listings.php';
            exit;
        }
        require __DIR__ . '/donation_handlers/get_available_listings.php';
        exit;
    }

    if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
        require __DIR__ . '/donation_handlers/edit_donation.php';
        exit;
    }

    if ($method === 'POST') {
        require __DIR__ . '/donation_handlers/post_donation.php';
        exit;
    }

    if ($method === 'DELETE') {
        require __DIR__ . '/donation_handlers/delete_donation.php';
        exit;
    }

    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in donations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "ERR_SYS_01: Database operation failed."
    ]);
}