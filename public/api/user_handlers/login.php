<?php
declare(strict_types=1);
if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('login', 10, 300);
}

$rawEmail = strtolower(trim((string)($input['email'] ?? '')));
$password = (string)($input['password'] ?? '');
$remember = !empty($input['remember']);

if (!$rawEmail || !$password) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: Email and password are required."]);
    return;
}

$normalizedEmail = (class_exists('Security') && method_exists('Security', 'normalizeEmail'))
    ? Security::normalizeEmail($rawEmail) 
    : $rawEmail;

try {
    $stmt = $pdo->prepare("
        SELECT user_id, name, org_name, email, password_hash, role, status,
               COALESCE(verification_status, 'Pending') AS verification_status
        FROM users 
        WHERE normalized_email = ? OR email = ? 
        LIMIT 1
    ");
    $stmt->execute([$normalizedEmail, $rawEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $dbPasswordHash = $user['password_hash'] ?? $user['password'] ?? '';

    if (!$user || !password_verify($password, $dbPasswordHash)) {
        if ($user) {
            recordAuditLog($pdo, (int)$user['user_id'], 'LOGIN_FAILED', "Failed login attempt for email {$rawEmail}", 'warning');
        }
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "ERR_AUTH_01: Invalid email or password."]);
        return;
    }

    if (isset($user['status']) && strtolower((string)$user['status']) === 'suspended') {
        recordAuditLog($pdo, (int)$user['user_id'], 'LOGIN_BLOCKED', "Suspended user tried to log in", 'warning');
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "ERR_AUTH_03: Account suspended. Contact support."]);
        return;
    }

    $user['role'] = strtolower(trim((string)$user['role']));

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

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['user'] = [
        'id' => $user['user_id'],
        'user_id' => $user['user_id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'verification_status' => $user['verification_status']
    ];

    createSystemNotification(
        $pdo,
        (int)$user['user_id'],
        "New Login Detected",
        "Successful login recorded from " . ($_SERVER['REMOTE_ADDR'] ?? 'your device') . " at " . date('H:i, M j, Y') . ".",
        "security"
    );

    recordAuditLog($pdo, (int)$user['user_id'], 'USER_LOGIN', "User successfully logged in", 'info');

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
        "error" => "ERR_SYS_01: Database error during login."
    ]);
}