<?php
declare(strict_types=1);
if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Unauthorized."]);
    exit;
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = [];
}
$donationId = (int)($data['donation_id'] ?? $_GET['donation_id'] ?? 0);

if (!$donationId) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: Donation ID required."]);
    exit;
}

$roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$roleStmt->execute([$currentUserId]);
$dbRole = strtolower((string)$roleStmt->fetchColumn());

if ($dbRole === 'admin') {
    $stmt = $pdo->prepare("DELETE FROM food_donations WHERE donation_id = ?");
    $stmt->execute([$donationId]);
} else {
    $stmt = $pdo->prepare("DELETE FROM food_donations WHERE donation_id = ? AND donor_id = ? AND status = 'Available'");
    $stmt->execute([$donationId, $currentUserId]);
}

if ($stmt->rowCount() > 0) {
    recordAuditLog($pdo, (int)$currentUserId, 'DONATION_DELETED', "Deleted donation listing #{$donationId}", 'warning');
    echo json_encode(["success" => true, "message" => "Donation removed successfully."]);
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_REQ_02: Donation not found, already requested, or you lack permission."]);
}