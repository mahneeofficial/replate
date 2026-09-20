<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in forgot_password.php: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Internal Server Error"]);
    exit;
});

$configDb = __DIR__ . '/../../config/db.php';
$configMail = __DIR__ . '/../../config/mail.php';
$configSecurity = __DIR__ . '/../../config/Security.php';

if (!file_exists($configDb)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Database configuration file missing."]);
    exit;
}

require_once $configDb;

if (file_exists($configMail)) {
    require_once $configMail;
}

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_02: Method not allowed."]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
$action = $input['action'] ?? '';

try {
    if ($action === 'request') {
        if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
            Security::enforceRateLimit('password_reset_request', 3, 600);
        }

        $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_02: Please enter a valid email address."]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_02: No account found with that email address."]);
            exit;
        }

        $otpCode = (string) random_int(100000, 999999);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (15 * 60));

        $stmtDelete = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmtDelete->execute([$user['user_id']]);

        $stmtInsert = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmtInsert->execute([$user['user_id'], $otpCode, $expiresAt]);

        $subject = "RePlate — Password Reset Code";
        $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                <h2>Hello " . htmlspecialchars($user['name']) . ",</h2>
                <p>You requested a password reset for your RePlate account.</p>
                <p>Your 6-digit verification code is:</p>
                <h1 style='color: #10b981; letter-spacing: 4px; font-size: 32px;'>" . $otpCode . "</h1>
                <p>This code expires in 15 minutes.</p>
                <p>If you did not request this, please ignore this email.</p>
            </div>";

        if (function_exists('sendEmail')) {
            if (sendEmail($email, $subject, $body)) {
                echo json_encode([
                    "success" => true, 
                    "message" => "Verification code sent to your email address."
                ]);
                exit;
            } else {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => "ERR_SYS_01: Failed to dispatch email. Please check SMTP configuration."]);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "ERR_SYS_01: Mail service is unavailable."]);
            exit;
        }

    } elseif ($action === 'reset') {
        if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
            Security::enforceRateLimit('password_reset_verify', 5, 600);
        }

        $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $token = trim((string)($input['token'] ?? $input['code'] ?? ''));
        $newPassword = $input['password'] ?? $input['new_password'] ?? '';

        if (!$email) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_02: Email reference missing."]);
            exit;
        }

        if (!$token || strlen($newPassword) < 8) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_03: Valid verification code and minimum 8-character password required."]);
            exit;
        }

        $currentUtc = gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            SELECT pr.id, pr.user_id 
            FROM password_resets pr
            JOIN users u ON u.user_id = pr.user_id
            WHERE LOWER(u.email) = LOWER(?) AND pr.token = ? AND pr.expires_at > ?
            LIMIT 1
        ");
        $stmt->execute([$email, $token, $currentUtc]);
        $resetReq = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetReq) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_01: Invalid or expired verification code."]);
            exit;
        }

        $hashedPass = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $pdo->beginTransaction();

        $updateUser = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $updateUser->execute([$hashedPass, $resetReq['user_id']]);

        $deleteReset = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $deleteReset->execute([$resetReq['user_id']]);

        $pdo->commit();

        echo json_encode(["success" => true, "message" => "Password updated successfully."]);
        exit;

    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_SYS_02: Invalid action requested."]);
        exit;
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in forgot_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Database operation failed."]);
    exit;
}