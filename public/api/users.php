<?php
declare(strict_types=1);

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in users.php: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Internal Server Error"]);
    exit;
});

header("Access-Control-Allow-Origin: *");
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
            handleRegister($pdo, $input);
            break;
        case 'login':
            handleLogin($pdo, $input);
            break;
        case 'logout':
            handleLogout($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_SYS_02: Invalid or missing POST action."]);
            break;
    }
} elseif ($method === 'GET') {
    if ($action === 'me') {
        handleGetCurrentUser($pdo);
    } elseif ($action === 'logout') {
        handleLogout($pdo);
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_SYS_02: Invalid or missing GET action."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);
}

function handleGetCurrentUser(PDO $pdo): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

    if (!$userId) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Not authenticated."]);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, name, org_name, email, role, status FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode(["success" => true, "user" => $user]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_02: User not found."]);
        }
    } catch (Throwable $e) {
        error_log("Error retrieving user: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "ERR_SYS_01: Database error retrieving user."]);
    }
}

function handleRegister(PDO $pdo, array $data): void {
    if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
        Security::enforceRateLimit('register', 5, 600);
    }

    $rawName = trim($data['name'] ?? $data['full_name'] ?? '');
    $rawOrgName = trim($data['org_name'] ?? $data['organization'] ?? '');
    
    $name = class_exists('Security') && method_exists('Security', 'sanitize') ? Security::sanitize($rawName) : $rawName;
    $orgName = class_exists('Security') && method_exists('Security', 'sanitize') ? Security::sanitize($rawOrgName) : $rawOrgName;
    
    $rawEmail = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $data['password'] ?? '';
    $role = trim($data['role'] ?? 'recipient');

    if (!in_array($role, ['recipient', 'donor', 'admin'], true)) {
        $role = 'recipient';
    }

    if (empty($name) || !$rawEmail || empty($password)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_VAL_01: Name, valid email, and password are required."]);
        return;
    }

    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_VAL_03: Password must be at least 8 characters long."]);
        return;
    }

    if (class_exists('Security') && method_exists('Security', 'isDisposableEmail') && Security::isDisposableEmail($rawEmail)) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "ERR_SEC_01: Disposable or temporary email domains are prohibited."]);
        return;
    }

    $normalizedEmail = (class_exists('Security') && method_exists('Security', 'normalizeEmail'))
        ? Security::normalizeEmail($rawEmail) 
        : strtolower($rawEmail);

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE normalized_email = ? OR email = ? LIMIT 1");
        $stmt->execute([$normalizedEmail, $rawEmail]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(["success" => false, "error" => "ERR_SEC_02: An account with this email address already exists."]);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (name, org_name, email, normalized_email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())");
        $stmt->execute([$name, $orgName ?: null, $rawEmail, $normalizedEmail, $hashedPassword, $role]);
        $userId = (int) $pdo->lastInsertId();

        $stmtSettings = $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmtSettings->execute([$userId]);

        $pdo->commit();

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "User registered successfully.",
            "user_id" => $userId
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in handleRegister: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "ERR_SYS_01: Failed to register user due to a database error."
        ]);
    }
}

function handleLogin(PDO $pdo, array $data): void {
    if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
        Security::enforceRateLimit('login', 10, 300);
    }

    $rawEmail = strtolower(trim($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $remember = !empty($data['remember']);

    if (!$rawEmail || !$password) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_VAL_01: Email and password are required."]);
        return;
    }

    $normalizedEmail = (class_exists('Security') && method_exists('Security', 'normalizeEmail'))
        ? Security::normalizeEmail($rawEmail) 
        : $rawEmail;

    try {
        $stmt = $pdo->prepare("SELECT user_id, name, org_name, email, password_hash, role, status FROM users WHERE normalized_email = ? OR email = ? LIMIT 1");
        $stmt->execute([$normalizedEmail, $rawEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $dbPasswordHash = $user['password_hash'] ?? $user['password'] ?? '';

        if (!$user || !password_verify($password, $dbPasswordHash)) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_01: Invalid email or password."]);
            return;
        }

        if (isset($user['status']) && strtolower($user['status']) === 'suspended') {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_03: Account suspended. Contact support."]);
            return;
        }

        if ($remember) {
            try {
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $hashedValidator = hash('sha256', $validator);
                $expires = date('Y-m-d H:i:s', time() + (86400 * 30));

                $stmtToken = $pdo->prepare("INSERT INTO auth_tokens (selector, hashed_validator, user_id, expires_at) VALUES (?, ?, ?, ?)");
                $stmtToken->execute([$selector, $hashedValidator, $user['user_id'], $expires]);

                setcookie(
                    'replate_remember',
                    $selector . ':' . $validator,
                    [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'domain' => '',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );
            } catch (Throwable $e) {
                error_log("Failed to store auth token: " . $e->getMessage());
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['user'] = [
            'id' => $user['user_id'],
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        unset($user['password_hash'], $user['password']);
        echo json_encode([
            "success" => true,
            "message" => "Login successful.",
            "user" => $user
        ]);
    } catch (Throwable $e) {
        error_log("Database query error during login: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "ERR_SYS_01: Database query error during login."
        ]);
    }
}

function handleLogout(PDO $pdo): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_COOKIE['replate_remember'])) {
        $parts = explode(':', $_COOKIE['replate_remember']);
        if (count($parts) === 2) {
            try {
                $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?");
                $stmt->execute([$parts[0]]);
            } catch (Throwable $e) {
                error_log("Failed to clear auth token: " . $e->getMessage());
            }
        }
        setcookie('replate_remember', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    echo json_encode(["success" => true, "message" => "Logged out successfully."]);
}