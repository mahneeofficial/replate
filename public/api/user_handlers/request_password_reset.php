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

    // Build dynamic base URL for the 1-Click Copy Code & Reset action
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/api(?:/.*)?$#', '', str_replace('\\', '/', $scriptDir));
    $loginPath = ($basePath === '' || $basePath === '/') ? '/login' : rtrim($basePath, '/') . '/login.html';
    $copyAndResetUrl = "{$scheme}://{$host}{$loginPath}?reset_email=" . rawurlencode($rawEmail) . "&reset_code=" . rawurlencode($code);

    $mailConfig = __DIR__ . '/../../../config/mail.php';
    if (file_exists($mailConfig)) {
        require_once $mailConfig;
        if (function_exists('sendEmail')) {
            $safeName = htmlspecialchars((string)$user['display_name'], ENT_QUOTES, 'UTF-8');
            $safeActionUrl = htmlspecialchars($copyAndResetUrl, ENT_QUOTES, 'UTF-8');
            $requestIp = htmlspecialchars((string)($_SERVER['REMOTE_ADDR'] ?? 'Unknown IP'), ENT_QUOTES, 'UTF-8');
            $requestTime = date('M j, Y \a\t H:i');

            $htmlBody = "
                <p>Hello <strong>{$safeName}</strong>,</p>
                <p>You requested a password reset for your <strong>RePlate</strong> account. Tap the code below to select it, or click <strong>Copy Code &amp; Reset</strong> to apply it automatically:</p>

                <div class=\"code-box\" style=\"user-select: all; -webkit-user-select: all; -moz-user-select: all; cursor: pointer; letter-spacing: 6px;\">{$code}</div>

                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$safeActionUrl}\" style=\"display: inline-block; background-color: #10b981; color: #052e16; font-weight: 700; text-decoration: none; padding: 13px 26px; border-radius: 10px; font-size: 15px;\">
                        &#128203; Copy Code &amp; Reset Password
                    </a>
                </div>

                <table style=\"width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; color: #94a3b8;\">
                    <tr>
                        <td style=\"padding: 6px 0;\"><strong>Expires In:</strong></td>
                        <td style=\"padding: 6px 0; text-align: right;\">15 minutes</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 6px 0;\"><strong>Requested At:</strong></td>
                        <td style=\"padding: 6px 0; text-align: right;\">{$requestTime}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 6px 0;\"><strong>Request IP:</strong></td>
                        <td style=\"padding: 6px 0; text-align: right;\">{$requestIp}</td>
                    </tr>
                </table>

                <p style=\"font-size: 13px; color: #64748b;\">If you did not request this password reset, please ignore this email.</p>
            ";
            @sendEmail($rawEmail, "RePlate — Password Reset Code ({$code})", $htmlBody);
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