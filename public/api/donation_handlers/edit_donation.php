<?php
declare(strict_types=1);
if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Please log in to edit a donation."]);
    exit;
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$cleanString = function (string $val): string {
    if (class_exists('Security')) {
        if (method_exists('Security', 'cleanInput')) return Security::cleanInput($val);
        if (method_exists('Security', 'sanitize')) return Security::sanitize($val);
    }
    return strip_tags(trim($val));
};

$donationId = (int)($data['donation_id'] ?? 0);
$foodName = $cleanString((string)($data['food_name'] ?? ''));
$quantityRaw = trim((string)($data['quantity'] ?? ''));
$unit = $cleanString((string)($data['unit'] ?? 'kg'));
$categoryId = max(1, (int)($data['category_id'] ?? 1));
$location = $cleanString((string)($data['pickup_location'] ?? ''));
$expiryDateRaw = trim((string)($data['expiry_date'] ?? ''));

if (!$donationId || empty($foodName) || empty($quantityRaw) || empty($expiryDateRaw) || empty($location)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: All fields are required to update a listing."]);
    exit;
}

if (!is_numeric($quantityRaw) || (float)$quantityRaw <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: Quantity must be a valid positive number."]);
    exit;
}

$parsedExpiry = strtotime($expiryDateRaw);
if ($parsedExpiry === false) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_02: Invalid expiry date provided."]);
    exit;
}

$roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$roleStmt->execute([$currentUserId]);
$dbRole = strtolower((string)$roleStmt->fetchColumn());

$checkStmt = $pdo->prepare("SELECT donor_id, status FROM food_donations WHERE donation_id = ? LIMIT 1");
$checkStmt->execute([$donationId]);
$existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "ERR_VAL_02: Donation listing not found."]);
    exit;
}

if ($dbRole !== 'admin' && (int)$existing['donor_id'] !== (int)$currentUserId) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_06: You can only edit your own donation listings."]);
    exit;
}

$formattedExpiry = date('Y-m-d H:i:s', $parsedExpiry);
$quantity = (string)(float)$quantityRaw;

$updStmt = $pdo->prepare("
    UPDATE food_donations
    SET category_id = ?, food_name = ?, quantity = ?, unit = ?, expiry_date = ?, pickup_location = ?
    WHERE donation_id = ?
");
$updStmt->execute([$categoryId, $foodName, $quantity, $unit, $formattedExpiry, $location, $donationId]);

recordAuditLog($pdo, (int)$currentUserId, 'DONATION_UPDATED', "Updated donation #{$donationId} ({$foodName}, {$quantity} {$unit})", 'info');

echo json_encode([
    "success" => true,
    "message" => "Food donation listing updated successfully."
]);