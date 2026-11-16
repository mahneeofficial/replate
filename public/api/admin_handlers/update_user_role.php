<?php
declare(strict_types=1);
try {
    $targetUserId = (int)($inputData['user_id'] ?? 0);
    $role = $cleanString((string)($inputData['role'] ?? ''));

    if (!$targetUserId || empty($role)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: User ID and role are required.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->execute([$role, $targetUserId]);
    echo json_encode(['success' => true, 'message' => "User role updated to {$role}."]);
} catch (Throwable $e) {
    error_log("Error in update_user_role handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}