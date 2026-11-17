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
    SELECT d.donor_id, d.food_name, d.status, d.quantity, d.unit, d.pickup_location,
           u.email AS donor_email, COALESCE(u.org_name, u.name, 'Donor') AS donor_name,
           COALESCE(s.notify_inapp, 1) AS notify_inapp,
           COALESCE(s.notify_email, 1) AS notify_email
    FROM food_donations d
    JOIN users u ON d.donor_id = u.user_id
    LEFT JOIN user_settings s ON u.user_id = s.user_id
    WHERE d.donation_id = ? 
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

// 1. In-App Notification (only if Donor enabled In-App Activity Alerts)
if ((int)$donation['notify_inapp'] === 1) {
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
}

recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_CREATED', "Submitted claim request #{$requestId} for donation #{$donationId}", 'info');

$pdo->commit();

// 2. Email Confirmation (if Donor enabled Free Email Confirmations)
if ((int)$donation['notify_email'] === 1 && !empty($donation['donor_email'])) {
    $mailConfig = __DIR__ . '/../../../config/mail.php';
    if (file_exists($mailConfig)) {
        require_once $mailConfig;
        if (function_exists('sendEmail')) {
            $safeDonor = htmlspecialchars((string)$donation['donor_name'], ENT_QUOTES, 'UTF-8');
            $safeFood  = htmlspecialchars((string)$donation['food_name'], ENT_QUOTES, 'UTF-8');
            $safeQty   = htmlspecialchars((string)($donation['quantity'] . ' ' . ($donation['unit'] ?? '')), ENT_QUOTES, 'UTF-8');
            $html = "
                <p>Hello <strong>{$safeDonor}</strong>,</p>
                <p>A verified recipient organization has just submitted a claim request for your surplus listing:</p>
                <ul>
                    <li><strong>Food Item:</strong> {$safeFood}</li>
                    <li><strong>Quantity:</strong> {$safeQty}</li>
                    <li><strong>Request ID:</strong> #{$requestId}</li>
                </ul>
                <p>Log in to your RePlate dashboard and open <strong>Requests &amp; Claims</strong> to approve or decline this request.</p>
            ";
            @sendEmail($donation['donor_email'], "RePlate — New Claim Request for {$donation['food_name']}", $html);
        }
    }
}

http_response_code(201);
echo json_encode(["success" => true, "message" => "Donation requested successfully."]);