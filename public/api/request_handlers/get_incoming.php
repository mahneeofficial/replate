<?php
declare(strict_types=1);

$stmt = $pdo->prepare("
    SELECT r.request_id, r.donation_id, r.quantity_requested, r.status, r.created_at,
           d.food_name, d.pickup_location,
           COALESCE(u.org_name, u.name, u.email, 'Anonymous User') AS recipient_name, 
           u.email AS recipient_email,
           rs.phone_number AS recipient_phone,
           COALESCE(ds.notify_whatsapp, 1) AS donor_notify_whatsapp
    FROM donation_requests r
    JOIN food_donations d ON r.donation_id = d.donation_id
    JOIN users u ON r.recipient_id = u.user_id
    LEFT JOIN user_settings rs ON u.user_id = rs.user_id
    LEFT JOIN user_settings ds ON d.donor_id = ds.user_id
    WHERE d.donor_id = ?
    ORDER BY r.created_at DESC
    LIMIT 100
");
$stmt->execute([$currentUserId]);
$rawRequests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$requests = array_map(function (array $item): array {
    $whatsappUrl = null;
    $status = strtolower((string)($item['status'] ?? 'pending'));

    if ($status === 'approved' && (int)($item['donor_notify_whatsapp'] ?? 1) === 1 && !empty($item['recipient_phone'])) {
        $digits = preg_replace('/\D+/', '', (string)$item['recipient_phone']);
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254' . substr($digits, 1);
        }
        if (strlen($digits) >= 9) {
            $foodName = $item['food_name'] ?? 'Food Surplus';
            $location = $item['pickup_location'] ?? 'agreed pickup point';
            $text = rawurlencode("Hello {$item['recipient_name']}, your RePlate claim for '{$foodName}' is approved! Pickup location: {$location}.");
            $whatsappUrl = "https://wa.me/{$digits}?text={$text}";
        }
    }

    $item['whatsapp_url'] = $whatsappUrl;
    unset($item['recipient_phone'], $item['donor_notify_whatsapp']);
    return $item;
}, $rawRequests);

echo json_encode(["success" => true, "requests" => $requests]);