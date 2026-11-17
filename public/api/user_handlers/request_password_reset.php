<?php
declare(strict_types=1);
if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('reset_request', 3, 300);
}

$rawEmail = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);

if (!$rawEmail) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: Valid email address is required."]);
    return;
}

$normalizedEmail = (class_exists('Security') && method_exists('Security', 'normalizeEmail'))
    ? Security::normalizeEmail($rawEmail)
    : strtolower($rawEmail);

try {
    $stmt = $pdo->prepare("SELECT user_id, COALESCE(name, org_name, 'User') AS display_name FROM users WHERE normalized_email = ? OR email = ? LIMIT 1");
    $stmt->execute([$normalizedEmail, $rawEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            "success" => true,
            "message" => "If an account exists with that email, a 6-digit verification code has been sent."
        ]);
        return;
    }

    $code = sprintf("%06d", random_int(100000, 999999));
    $hashedCode = password_hash($code, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', time() + 900);

    $stmtReset = $pdo->prepare("
        INSERT INTO password_resets (user_id, email, token_hash, expires_at) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)
    ");
    $stmtReset->execute([$user['user_id'], $rawEmail, $hashedCode, $expiresAt]);

    $mailConfig = __DIR__ . '/../../../config/mail.php';
    if (file_exists($mailConfig)) {
        require_once $mailConfig;
        if (function_exists('sendEmail')) {
            $safeName = htmlspecialchars((string)$user['display_name'], ENT_QUOTES, 'UTF-8');
            $htmlBody = "
                <p>Hello <strong>{$safeName}</strong>,</p>
                <p>You requested a password reset for your RePlate account. Use the One-Time Password (OTP) below to complete your verification:</p>
                <div class=\"code-box\">{$code}</div>
                <p>This verification code expires in <strong>15 minutes</strong>.</p>
                <p style=\"font-size: 13px; color: #64748b;\">If you did not request this password reset, please ignore this email or update your password to protect your account.</p>
            ";
            @sendEmail($rawEmail, "RePlate — Password Reset Code", $htmlBody);
        }
    }

    recordAuditLog($pdo, (int)$user['user_id'], 'PASSWORD_RESET_REQUESTED', "Password reset requested for {$rawEmail}", 'info');

    echo json_encode([
        "success" => true,
        "message" => "Verification code sent to your email address."
    ]);
} catch (Throwable $e) {
    error_log("Error in handleRequestPasswordReset: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Failed to process password reset request."]);
}