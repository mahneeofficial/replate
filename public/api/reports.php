<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in reports.php: " . $e->getMessage());
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
        $isAdmin = (($_SESSION['role'] ?? '') === 'admin');

        if ($isAdmin) {
            $stmt = $pdo->query("
                SELECT r.*, u.name AS reporter_name, u.email AS reporter_email
                FROM reports r
                LEFT JOIN users u ON r.reporter_id = u.user_id
                ORDER BY r.created_at DESC
                LIMIT 100
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM reports 
                WHERE reporter_id = ? 
                ORDER BY created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$userId]);
        }

        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(["success" => true, "reports" => $reports]);
        exit;
    }

    if ($method === 'POST') {
        if (class_exists('Security')) {
            Security::enforceRateLimit('submit_report', 5, 300);
        }

        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true) ?? $_POST;

        $targetType = class_exists('Security') ? Security::sanitize($data['target_type'] ?? '') : trim($data['target_type'] ?? '');
        $targetId = (int)($data['target_id'] ?? 0);
        $reason = class_exists('Security') ? Security::sanitize($data['reason'] ?? '') : trim($data['reason'] ?? '');

        if (empty($targetType) || !$targetId || empty($reason)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_01: Target type, target ID, and reason are required."]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO reports (reporter_id, target_type, target_id, reason, status)
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$userId, $targetType, $targetId, $reason]);

        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Report submitted successfully."]);
        exit;
    }

    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);

} catch (Throwable $e) {
    error_log("Error in reports.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Database operation failed."]);
    exit;
}