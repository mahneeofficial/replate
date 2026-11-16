<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in admin.php: " . $e->getMessage());
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

function hasTableColumn(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function ensureTableColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (!hasTableColumn($pdo, $table, $column)) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            error_log("Failed to add missing column {$column} to {$table}: " . $e->getMessage());
        }
    }
}

ensureTableColumn($pdo, 'users', 'is_verified', 'TINYINT(1) NOT NULL DEFAULT 1');
ensureTableColumn($pdo, 'users', 'status', "VARCHAR(50) NOT NULL DEFAULT 'Active'");
ensureTableColumn($pdo, 'users', 'name', "VARCHAR(255) NULL DEFAULT ''");
ensureTableColumn($pdo, 'users', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

$userRole = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (empty($userRole) && $currentUserId) {
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $roleStmt->execute([$currentUserId]);
    $userRole = (string)$roleStmt->fetchColumn();
}

if (strtolower((string)$userRole) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'ERR_AUTH_03: Unauthorized. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('admin_action', 30, 300);
}

$rawInput = file_get_contents('php://input');
$inputData = !empty($rawInput) ? json_decode($rawInput, true) : null;
if (!is_array($inputData)) {
    $inputData = $_POST;
}

$action = $_GET['action'] ?? $inputData['action'] ?? '';

$cleanString = function (string $val): string {
    $val = trim($val);
    if (class_exists('Security')) {
        if (method_exists('Security', 'cleanInput')) {
            return Security::cleanInput($val);
        }
        if (method_exists('Security', 'sanitize')) {
            return Security::sanitize($val);
        }
    }
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
};

$handlerFile = __DIR__ . '/admin_handlers/' . basename($action) . '.php';

if (!empty($action) && file_exists($handlerFile)) {
    require $handlerFile;
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_02: Invalid action requested.']);
}