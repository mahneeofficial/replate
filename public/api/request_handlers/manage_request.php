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
    SELECT r.request_id, r.recipient_id, r.donation_id, r.status AS request_status,
           d.food_name, d.donor_id, d.pickup_location,
           u.email AS recipient_email, COALESCE(u.org_name, u.name, 'Recipient') AS recipient_name,
           COALESCE(s.notify_inapp, 1) AS recipient_notify_inapp,
           COALESCE(s.notify_email, 1) AS recipient_notify_email,
           COALESCE(s.notify_whatsapp, 1) AS recipient_notify_whatsapp,
           s.phone_number AS recipient_phone,
           COALESCE(ds.notify_whatsapp, 1) AS donor_notify_whatsapp,
           ds.phone_number AS donor_phone
    FROM donation_requests r 
    JOIN food_donations d ON r.donation_id = d.donation_id 
    JOIN users u ON r.recipient_id = u.user_id
    LEFT JOIN user_settings s ON u.user_id = s.user_id
    LEFT JOIN user_settings ds ON d.donor_id = ds.user_id
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

$whatsappUrl = null;

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

    // Build free WhatsApp Quick Handoff link if enabled and recipient phone is saved
    if ((int)$req['donor_notify_whatsapp'] === 1 && !empty($req['recipient_phone'])) {
        $digits = preg_replace('/\D+/', '', (string)$req['recipient_phone']);
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254' . substr($digits, 1);
        }
        if (strlen($digits) >= 9) {
            $waText = rawurlencode("Hello {$req['recipient_name']}, your RePlate claim request for '{$req['food_name']}' has been approved! Pickup location: {$req['pickup_location']}.");
            $whatsappUrl = "https://wa.me/{$digits}?text={$waText}";
        }
    }

    recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_APPROVED', "Approved request #{$requestId} for donation #{$donationId}", 'info');
} else {
    $stmtDon = $pdo->prepare("UPDATE food_donations SET status = 'Available' WHERE donation_id = ?");
    $stmtDon->execute([$donationId]);

    recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_REJECTED', "Rejected request #{$requestId} for donation #{$donationId}", 'warning');
}

// 1. In-App Notification (only if Recipient enabled In-App Activity Alerts)
if ((int)$req['recipient_notify_inapp'] === 1) {
    try {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $notifStmt->execute([$recipientId, $notifTitle, $notifMsg, $notifType]);
    } catch (Throwable $e) {
        error_log("Failed to insert notification: " . $e->getMessage());
    }
}

$pdo->commit();

// 2. Email Confirmation (if Recipient enabled Free Email Confirmations)
if ((int)$req['recipient_notify_email'] === 1 && !empty($req['recipient_email'])) {
    $mailConfig = __DIR__ . '/../../../config/mail.php';
    if (file_exists($mailConfig)) {
        require_once $mailConfig;
        if (function_exists('sendEmail')) {
            $safeRecip = htmlspecialchars((string)$req['recipient_name'], ENT_QUOTES, 'UTF-8');
            $safeFood  = htmlspecialchars((string)$req['food_name'], ENT_QUOTES, 'UTF-8');
            $safeLoc   = htmlspecialchars((string)($req['pickup_location'] ?? 'See RePlate Dashboard'), ENT_QUOTES, 'UTF-8');

            if ($action === 'approve') {
                $donorWaHtml = '';
                if ((int)$req['recipient_notify_whatsapp'] === 1 && !empty($req['donor_phone'])) {
                    $dDigits = preg_replace('/\D+/', '', (string)$req['donor_phone']);
                    if (str_starts_with($dDigits, '0') && strlen($dDigits) === 10) {
                        $dDigits = '254' . substr($dDigits, 1);
                    }
                    if (strlen($dDigits) >= 9) {
                        $msg = rawurlencode("Hello! Our RePlate claim for '{$req['food_name']}' was approved. Coordinating pickup now.");
                        $donorWaHtml = "<p style=\"margin-top:16px;\"><a href=\"https://wa.me/{$dDigits}?text={$msg}\" style=\"display:inline-block;background:#10b981;color:#ffffff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700;\">Chat with Donor on WhatsApp</a></p>";
                    }
                }
                $html = "
                    <p>Hello <strong>{$safeRecip}</strong>,</p>
                    <p>Great news! Your food claim request for <strong>{$safeFood}</strong> has been <strong>Approved</strong>.</p>
                    <p><strong>Pickup Location:</strong> {$safeLoc}</p>
                    {$donorWaHtml}
                ";
                @sendEmail($req['recipient_email'], "RePlate — Claim Request Approved ({$req['food_name']})", $html);
            } else {
                $html = "
                    <p>Hello <strong>{$safeRecip}</strong>,</p>
                    <p>Your claim request for <strong>{$safeFood}</strong> was not approved by the donor. Please check the RePlate dashboard for other available food surplus nearby.</p>
                ";
                @sendEmail($req['recipient_email'], "RePlate — Request Update ({$req['food_name']})", $html);
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => "Request {$newStatus} successfully.",
    'whatsapp_url' => $whatsappUrl
]);