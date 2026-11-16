<?php
declare(strict_types=1);
if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('reset_password', 5, 300);
}

$rawEmail = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$code = trim((string)($input['code'] ?? ''));
$newPassword = (string)($input['new_password'] ?? $input['password'] ?? '');

if (!$rawEmail || !$code || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: Email, verification code, and new password are required."]);
    return;
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_03: New password must be at least 8 characters long."]);
    return;
}

try {
    $stmt = $pdo->prepare("
        SELECT pr.user_id, pr.token_hash, pr.expires_at 
        FROM password_resets pr
        JOIN users u ON u.user_id = pr.user_id
        WHERE u.email = ? OR u.normalized_email = ?
        ORDER BY pr.expires_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$rawEmail, strtolower($rawEmail)]);
    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRecord || strtotime($resetRecord['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_AUTH_06: Invalid or expired verification code."]);
        return;
    }

    if (!password_verify($code, $resetRecord['token_hash'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_AUTH_06: Invalid verification code."]);
        return;
    }

    $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->beginTransaction();

    $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmtUpdate->execute([$newHashedPassword, $resetRecord['user_id']]);

    $stmtDelete = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmtDelete->execute([$resetRecord['user_id']]);

    createSystemNotification(
        $pdo,
        (int)$resetRecord['user_id'],
        "Password Changed",
        "Your account password was successfully updated.",
        "security"
    );

    recordAuditLog($pdo, (int)$resetRecord['user_id'], 'PASSWORD_RESET_SUCCESS', "Password was successfully reset", 'info');

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Password updated successfully. You can now log in with your new password."
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in handleResetPassword: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Failed to update password."]);
}