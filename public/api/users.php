<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in users.php: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Internal Server Error"]);
    exit;
});

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

function recordAuditLog(PDO $pdo, ?int $userId, string $eventType, string $details, string $logType = 'info'): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, event_type, action_details, log_type, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $eventType, $details, $logType, $ip]);
    } catch (Throwable $e) {
        error_log("Failed to write audit log in users.php: " . $e->getMessage());
    }
}

function createSystemNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$userId, $title, $message, $type]);
    } catch (Throwable $e) {
        error_log("Failed to create system notification in users.php: " . $e->getMessage());
    }
}

function checkRememberCookie(PDO $pdo): void {
    if (!empty($_SESSION['user_id']) || !empty($_SESSION['user']['id'])) {
        return;
    }

    if (empty($_COOKIE['replate_remember'])) {
        return;
    }

    $parts = explode(':', $_COOKIE['replate_remember']);
    if (count($parts) !== 2) {
        return;
    }

    [$selector, $validator] = $parts;

    try {
        $stmt = $pdo->prepare("
            SELECT t.user_id, t.hashed_validator, u.name, u.org_name, u.email, u.role, u.status,
                   COALESCE(u.verification_status, 'Pending') AS verification_status
            FROM auth_tokens t
            JOIN users u ON u.user_id = t.user_id
            WHERE t.selector = ? AND t.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$selector]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
            if (isset($token['status']) && strtolower((string)$token['status']) === 'suspended') {
                return;
            }

            session_regenerate_id(true);
            $normalizedRole = strtolower(trim((string)$token['role']));
            $_SESSION['user_id'] = $token['user_id'];
            $_SESSION['role'] = $normalizedRole;
            $_SESSION['name'] = $token['name'];
            $_SESSION['user'] = [
                'id' => $token['user_id'],
                'user_id' => $token['user_id'],
                'name' => $token['name'],
                'email' => $token['email'],
                'role' => $normalizedRole,
                'verification_status' => $token['verification_status']
            ];
        }
    } catch (Throwable $e) {
        error_log("Error in checkRememberCookie: " . $e->getMessage());
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST') {
    $rawInput = file_get_contents("php://input");
    $input = !empty($rawInput) ? json_decode($rawInput, true) : null;

    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = $input['action'] ?? $action;

    switch ($action) {
        case 'register':
            require __DIR__ . '/user_handlers/register.php';
            break;
        case 'login':
            require __DIR__ . '/user_handlers/login.php';
            break;
        case 'logout':
            require __DIR__ . '/user_handlers/logout.php';
            break;
        case 'request_password_reset':
            require __DIR__ . '/user_handlers/request_password_reset.php';
            break;
        case 'reset_password':
            require __DIR__ . '/user_handlers/reset_password.php';
            break;
        default:
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_SYS_02: Invalid or missing POST action."]);
            break;
    }
} elseif ($method === 'GET') {
    if ($action === 'me') {
        require __DIR__ . '/user_handlers/get_current_user.php';
    } elseif ($action === 'logout') {
        require __DIR__ . '/user_handlers/logout.php';
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_SYS_02: Invalid or missing GET action."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);
}