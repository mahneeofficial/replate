<?php
declare(strict_types=1);
$stmt = $pdo->prepare("
    SELECT r.request_id, r.donation_id, r.quantity_requested, r.status, r.created_at,
           d.food_name, d.pickup_location, 
           COALESCE(u.org_name, u.name, u.email, 'Community Donor') AS raw_donor_name,
           s.discretion_mode
    FROM donation_requests r
    JOIN food_donations d ON r.donation_id = d.donation_id
    LEFT JOIN users u ON d.donor_id = u.user_id
    LEFT JOIN user_settings s ON u.user_id = s.user_id
    WHERE r.recipient_id = ?
    ORDER BY r.created_at DESC
    LIMIT 100
");
$stmt->execute([$currentUserId]);
$rawRequests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$requests = array_map(function($item) {
    if (!empty($item['discretion_mode']) && (int)$item['discretion_mode'] === 1) {
        $item['donor_name'] = 'Anonymous Verified Donor';
    } else {
        $item['donor_name'] = $item['raw_donor_name'] ?? 'Community Donor';
    }
    unset($item['raw_donor_name'], $item['discretion_mode']);
    return $item;
}, $rawRequests);

echo json_encode(["success" => true, "requests" => $requests]);