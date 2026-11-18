<?php
declare(strict_types=1);
try {
    $targetUserId = (int)($inputData['user_id'] ?? 0);

    if (!$targetUserId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: User ID is required.']);
        exit;
    }

    if ((int)$currentUserId === $targetUserId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_AUTH_04: You cannot delete your own active admin account.']);
        exit;
    }

    $checkStmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
    $checkStmt->execute([$targetUserId]);
    $targetUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_02: User account not found.']);
        exit;
    }

    $pdo->beginTransaction();

    // Clean up auth tokens if table exists
    try {
        $tokStmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
        $tokStmt->execute([$targetUserId]);
    } catch (Throwable $e) {}

    $delStmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $delStmt->execute([$targetUserId]);

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, event_type, action_details, log_type, ip_address) VALUES (?, 'ADMIN_USER_REMOVED', ?, 'warning', ?)");
        $logStmt->execute([(int)$currentUserId, "Permanently removed user #{$targetUserId} ({$targetUser['email']})", $ip]);
    } catch (Throwable $e) {}

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => "User account ({$targetUser['email']}) has been permanently removed."]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in delete_user handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to delete user account.']);
}