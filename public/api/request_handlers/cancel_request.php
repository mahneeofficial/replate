<?php
declare(strict_types=1);
$requestId = (int)($data['request_id'] ?? 0);

if (!$requestId) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_02: Request ID is required."]);
    exit;
}

$stmt = $pdo->prepare("UPDATE donation_requests SET status = 'Cancelled' WHERE request_id = ? AND recipient_id = ? AND status = 'Pending'");
$stmt->execute([$requestId, $currentUserId]);

if ($stmt->rowCount() > 0) {
    recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_CANCELLED', "Cancelled request #{$requestId}", 'info');
    echo json_encode(['success' => true, 'message' => "Request cancelled successfully."]);
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_REQ_06: Request could not be cancelled. It may already be processed."]);
}