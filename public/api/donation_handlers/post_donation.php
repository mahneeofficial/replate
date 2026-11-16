<?php
declare(strict_types=1);
if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Please log in to post a donation."]);
    exit;
}

$roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
$roleStmt->execute([$currentUserId]);
$dbRole = strtolower((string)$roleStmt->fetchColumn());

if (!in_array($dbRole, ['donor', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_06: Only donors and administrators can post food donations."]);
    exit;
}

if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('post_donation', 10, 300);
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$cleanString = function(string $val): string {
    if (class_exists('Security')) {
        if (method_exists('Security', 'cleanInput')) return Security::cleanInput($val);
        if (method_exists('Security', 'sanitize')) return Security::sanitize($val);
    }
    return strip_tags(trim($val));
};

$foodName = $cleanString((string)($data['food_name'] ?? $data['title'] ?? ''));
$quantityRaw = trim((string)($data['quantity'] ?? ''));
$unit = $cleanString((string)($data['unit'] ?? 'kg'));
$categoryId = max(1, (int)($data['category_id'] ?? 1));
$location = $cleanString((string)($data['pickup_location'] ?? $data['location'] ?? ''));
$expiryDateRaw = trim((string)($data['expiry_date'] ?? ''));

if (empty($foodName) || empty($quantityRaw) || empty($expiryDateRaw) || empty($location)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: All fields are required."]);
    exit;
}

if (!is_numeric($quantityRaw) || (float)$quantityRaw <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: Quantity must be a valid positive number."]);
    exit;
}

$parsedExpiry = strtotime($expiryDateRaw);
if ($parsedExpiry === false || strtotime(date('Y-m-d', $parsedExpiry)) < strtotime(date('Y-m-d'))) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_02: Expiry date must be today or a future date."]);
    exit;
}

$formattedExpiry = date('Y-m-d H:i:s', $parsedExpiry);
$quantity = (string)(float)$quantityRaw;

$pdo->beginTransaction();

$stmt = $pdo->prepare("
    INSERT INTO food_donations (donor_id, category_id, food_name, quantity, unit, expiry_date, pickup_location, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Available')
");

if ($stmt->execute([$currentUserId, $categoryId, $foodName, $quantity, $unit, $formattedExpiry, $location])) {
    $donationId = (int)$pdo->lastInsertId();

    try {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $notifStmt->execute([
            $currentUserId, 
            'Surplus Food Posted', 
            "Your listing '{$foodName}' is now active.", 
            'info'
        ]);
    } catch (Throwable $e) {
        error_log("Failed to insert notification: " . $e->getMessage());
    }

    recordAuditLog($pdo, (int)$currentUserId, 'DONATION_CREATED', "Created donation #{$donationId} ({$foodName}, {$quantity} {$unit})", 'info');

    $pdo->commit();
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Donation posted successfully.",
        "id" => $donationId
    ]);
} else {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Failed to post donation."]);
}