<?php
declare(strict_types=1);
if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('manage_request', 20, 300);
}

$requestId = (int)($data['request_id'] ?? 0);

if (!$requestId) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_02: Request ID is required."]);
    exit;
}

$pdo->beginTransaction();

$stmt = $pdo->prepare("
    SELECT r.request_id, r.recipient_id, r.donation_id, r.status AS request_status, d.food_name, d.donor_id 
    FROM donation_requests r 
    JOIN food_donations d ON r.donation_id = d.donation_id 
    WHERE r.request_id = ?
    FOR UPDATE
");
$stmt->execute([$requestId]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "ERR_REQ_02: Request not found."]);
    exit;
}

if ((int)$req['donor_id'] !== (int)$currentUserId) {
    $pdo->rollBack();
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_02: You do not have permission to manage this request."]);
    exit;
}

if ($req['request_status'] !== 'Pending') {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_REQ_05: Request has already been processed."]);
    exit;
}

$recipientId = (int)$req['recipient_id'];
$donationId = (int)$req['donation_id'];
$newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';
$notifTitle = ($action === 'approve') ? 'Request Approved' : 'Request Rejected';
$notifType = ($action === 'approve') ? 'request' : 'warning';
$notifMsg = ($action === 'approve') 
    ? "Your request for '{$req['food_name']}' was approved!" 
    : "Your request for '{$req['food_name']}' was rejected.";

$stmt = $pdo->prepare("UPDATE donation_requests SET status = ? WHERE request_id = ?");
$stmt->execute([$newStatus, $requestId]);

if ($action === 'approve') {
    $stmtDon = $pdo->prepare("UPDATE food_donations SET status = 'Collected' WHERE donation_id = ?");
    $stmtDon->execute([$donationId]);

    $stmtPendingOthers = $pdo->prepare("SELECT recipient_id FROM donation_requests WHERE donation_id = ? AND request_id != ? AND status = 'Pending'");
    $stmtPendingOthers->execute([$donationId, $requestId]);
    $otherRecipients = $stmtPendingOthers->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $stmtOther = $pdo->prepare("UPDATE donation_requests SET status = 'Rejected' WHERE donation_id = ? AND request_id != ? AND status = 'Pending'");
    $stmtOther->execute([$donationId, $requestId]);

    foreach ($otherRecipients as $otherRecipientId) {
        try {
            $notifStmtOther = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $notifStmtOther->execute([
                $otherRecipientId,
                'Item No Longer Available',
                "The donation '{$req['food_name']}' you requested was claimed by another user.",
                'warning'
            ]);
        } catch (Throwable $e) {
            error_log("Failed to insert notification: " . $e->getMessage());
        }
    }

    recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_APPROVED', "Approved request #{$requestId} for donation #{$donationId}", 'info');
} else {
    $stmtDon = $pdo->prepare("UPDATE food_donations SET status = 'Available' WHERE donation_id = ?");
    $stmtDon->execute([$donationId]);

    recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_REJECTED', "Rejected request #{$requestId} for donation #{$donationId}", 'warning');
}

try {
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $notifStmt->execute([$recipientId, $notifTitle, $notifMsg, $notifType]);
} catch (Throwable $e) {
    error_log("Failed to insert notification: " . $e->getMessage());
}

$pdo->commit();
echo json_encode(['success' => true, 'message' => "Request {$newStatus} successfully."]);