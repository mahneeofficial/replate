<?php
declare(strict_types=1);

$stmt = $pdo->prepare("
    SELECT r.request_id, r.donation_id, r.quantity_requested, r.status, r.created_at,
           d.food_name, d.pickup_location, 
           COALESCE(u.org_name, u.name, u.email, 'Community Donor') AS raw_donor_name,
           s.discretion_mode,
           s.phone_number AS donor_phone,
           COALESCE(rs.notify_whatsapp, 1) AS recipient_notify_whatsapp
    FROM donation_requests r
    JOIN food_donations d ON r.donation_id = d.donation_id
    LEFT JOIN users u ON d.donor_id = u.user_id
    LEFT JOIN user_settings s ON u.user_id = s.user_id
    LEFT JOIN user_settings rs ON r.recipient_id = rs.user_id
    WHERE r.recipient_id = ?
    ORDER BY r.created_at DESC
    LIMIT 100
");
$stmt->execute([$currentUserId]);
$rawRequests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$requests = array_map(function (array $item): array {
    if (!empty($item['discretion_mode']) && (int)$item['discretion_mode'] === 1) {
        $item['donor_name'] = 'Anonymous Verified Donor';
    } else {
        $item['donor_name'] = $item['raw_donor_name'] ?? 'Community Donor';
    }

    $whatsappUrl = null;
    $status = strtolower((string)($item['status'] ?? 'pending'));

    if ($status === 'approved' && (int)($item['recipient_notify_whatsapp'] ?? 1) === 1 && !empty($item['donor_phone'])) {
        $digits = preg_replace('/\D+/', '', (string)$item['donor_phone']);
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254' . substr($digits, 1);
        }
        if (strlen($digits) >= 9) {
            $foodName = $item['food_name'] ?? 'Food Surplus';
            $text = rawurlencode("Hello {$item['donor_name']}, our RePlate claim for '{$foodName}' was approved! Coordinating pickup now.");
            $whatsappUrl = "https://wa.me/{$digits}?text={$text}";
        }
    }

    $item['whatsapp_url'] = $whatsappUrl;
    unset($item['raw_donor_name'], $item['discretion_mode'], $item['donor_phone'], $item['recipient_notify_whatsapp']);
    return $item;
}, $rawRequests);

echo json_encode(["success" => true, "requests" => $requests]);