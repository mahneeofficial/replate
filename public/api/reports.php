<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in reports.php: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Internal Server Error"]);
    exit;
});

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    echo json_encode(["success" => false, "error" => "ERR_AUTH_01: Unauthorized access. Please log in."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $userRole = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? '';
        
        if (empty($userRole)) {
            $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
            $roleStmt->execute([$userId]);
            $userRole = (string)$roleStmt->fetchColumn();
        }

        $isAdmin = (strtolower((string)$userRole) === 'admin');

        if ($isAdmin) {
            $stmt = $pdo->query("
                SELECT r.report_id, r.reporter_id, r.target_type, r.target_id, r.reason, r.status, r.created_at, r.updated_at,
                       u.name AS reporter_name, u.email AS reporter_email
                FROM reports r
                LEFT JOIN users u ON r.reporter_id = u.user_id
                ORDER BY r.created_at DESC
                LIMIT 100
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT report_id, target_type, target_id, reason, status, created_at, updated_at 
                FROM reports 
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
        if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
            Security::enforceRateLimit('submit_report', 5, 300);
        }

        $rawInput = file_get_contents("php://input");
        $data = !empty($rawInput) ? json_decode($rawInput, true) : null;

        if (!is_array($data)) {
            $data = $_POST;
        }

        $rawTargetType = trim((string)($data['target_type'] ?? ''));
        $rawReason     = trim((string)($data['reason'] ?? ''));

        $targetType = (class_exists('Security') && method_exists('Security', 'sanitize')) ? Security::sanitize($rawTargetType) : $rawTargetType;
        $reason     = (class_exists('Security') && method_exists('Security', 'sanitize')) ? Security::sanitize($rawReason) : $rawReason;
        $targetId   = (int)($data['target_id'] ?? 0);

        $allowedTargets = ['user', 'donation', 'request', 'comment'];
        if (empty($targetType) || !in_array(strtolower($targetType), $allowedTargets, true) || $targetId <= 0 || empty($reason)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_01: Valid target type (user, donation, request, comment), target ID, and reason are required."]);
            exit;
        }

        if (mb_strlen($reason) > 1000) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_02: Reason must not exceed 1000 characters."]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO reports (reporter_id, target_type, target_id, reason, status)
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$userId, strtolower($targetType), $targetId, $reason]);
        $reportId = (int)$pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            "success" => true, 
            "message" => "Report submitted successfully.",
            "report_id" => $reportId
        ]);
        exit;
    }

    if ($method === 'PUT') {
        $userRole = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? '';
        
        if (empty($userRole)) {
            $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
            $roleStmt->execute([$userId]);
            $userRole = (string)$roleStmt->fetchColumn();
        }

        if (strtolower((string)$userRole) !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_02: Forbidden. Admin privileges required."]);
            exit;
        }

        $rawInput = file_get_contents("php://input");
        $data = !empty($rawInput) ? json_decode($rawInput, true) : [];

        $reportId = (int)($data['report_id'] ?? 0);
        $status = trim((string)($data['status'] ?? ''));

        $allowedStatuses = ['Pending', 'Reviewed', 'Dismissed', 'Resolved'];
        if ($reportId <= 0 || !in_array($status, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_01: Valid report_id and status (Pending, Reviewed, Dismissed, Resolved) are required."]);
            exit;
        }

        $updateStmt = $pdo->prepare("UPDATE reports SET status = ? WHERE report_id = ?");
        $updateStmt->execute([$status, $reportId]);

        if ($updateStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "ERR_SYS_04: Report not found or status unchanged."]);
            exit;
        }

        echo json_encode([
            "success" => true,
            "message" => "Report status updated successfully to '{$status}'."
        ]);
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