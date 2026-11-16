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

    ensureTableColumn($pdo, 'users', 'status', "VARCHAR(50) NOT NULL DEFAULT 'Active'");

    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->execute([$status, $targetUserId]);
    echo json_encode(['success' => true, 'message' => "User account status updated to {$status}."]);
} catch (Throwable $e) {
    error_log("Error in update_user_status handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}