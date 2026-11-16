<?php
declare(strict_types=1);
try {
    $targetUserId = (int)($inputData['user_id'] ?? 0);
    $verifyStatus = (int)($inputData['is_verified'] ?? 1);

    if (!$targetUserId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: User ID is required.']);
        exit;
    }

    ensureTableColumn($pdo, 'users', 'is_verified', 'TINYINT(1) NOT NULL DEFAULT 1');

    $stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE user_id = ?");
    $stmt->execute([$verifyStatus, $targetUserId]);

    $statusText = $verifyStatus === 1 ? 'verified' : 'unverified';
    echo json_encode(['success' => true, 'message' => "Account successfully marked as {$statusText}."]);
} catch (Throwable $e) {
    error_log("Error in verify_account handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}