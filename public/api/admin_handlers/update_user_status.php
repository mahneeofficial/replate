<?php
declare(strict_types=1);
try {
    $targetUserId = (int)($inputData['user_id'] ?? 0);
    $status = $cleanString((string)($inputData['status'] ?? ''));

    if (!$targetUserId || empty($status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: User ID and status are required.']);
        exit;
    }

    if ((int)$currentUserId === $targetUserId && strtolower($status) === 'suspended') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_AUTH_04: You cannot suspend your own active admin account.']);
        exit;
    }

    ensureTableColumn($pdo, 'users', 'status', "VARCHAR(50) NOT NULL DEFAULT 'Active'");

    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->execute([$status, $targetUserId]);

    if (strtolower($status) === 'suspended') {
        try {
            $tokStmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
            $tokStmt->execute([$targetUserId]);
        } catch (Throwable $e) {}
    }

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, event_type, action_details, log_type, ip_address) VALUES (?, 'ADMIN_STATUS_CHANGE', ?, 'warning', ?)");
        $logStmt->execute([(int)$currentUserId, "Updated user #{$targetUserId} status to {$status}", $ip]);
    } catch (Throwable $e) {}

    echo json_encode(['success' => true, 'message' => "User account status updated to {$status}."]);
} catch (Throwable $e) {
    error_log("Error in update_user_status handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}