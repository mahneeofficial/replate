<?php
declare(strict_types=1);
if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('create_request', 15, 300);
}

$donationId = (int)($data['donation_id'] ?? 0);

if (!$donationId) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: Donation ID is required."]);
    exit;
}

$pdo->beginTransaction();

$stmt = $pdo->prepare("
    SELECT donor_id, food_name, status, quantity 
    FROM food_donations 
    WHERE donation_id = ? 
    FOR UPDATE
");
$stmt->execute([$donationId]);
$donation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donation || $donation['status'] !== 'Available') {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_REQ_01: Donation is no longer available."]);
    exit;
}

if ((int)$donation['donor_id'] === (int)$currentUserId) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_REQ_03: You cannot request your own donation."]);
    exit;
}

$stmtCheck = $pdo->prepare("
    SELECT request_id 
    FROM donation_requests 
    WHERE donation_id = ? AND recipient_id = ? AND status NOT IN ('Rejected', 'Cancelled')
");
$stmtCheck->execute([$donationId, $currentUserId]);
if ($stmtCheck->fetch()) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_REQ_04: You already have an active request for this item."]);
    exit;
}

$stmtReq = $pdo->prepare("
    INSERT INTO donation_requests (donation_id, recipient_id, quantity_requested, status) 
    VALUES (?, ?, ?, 'Pending')
");
$stmtReq->execute([$donationId, $currentUserId, $donation['quantity']]);
$requestId = (int)$pdo->lastInsertId();

try {
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $notifStmt->execute([
        $donation['donor_id'], 
        'New Food Request', 
        "A recipient requested your donation '{$donation['food_name']}'.", 
        'request'
    ]);
} catch (Throwable $e) {
    error_log("Failed to insert notification: " . $e->getMessage());
}

recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_CREATED', "Submitted claim request #{$requestId} for donation #{$donationId}", 'info');

$pdo->commit();
http_response_code(201);
echo json_encode(["success" => true, "message" => "Donation requested successfully."]);