<?php
declare(strict_types=1);
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

try {
    $stmt = $pdo->prepare("
        SELECT d.donation_id, d.food_name, d.quantity, d.unit, d.expiry_date, d.pickup_location, d.status,
               COALESCE(u.org_name, u.name, u.email, 'Community Donor') AS raw_donor_name, 
               COALESCE(c.name, 'General Surplus') AS category_name,
               s.discretion_mode
        FROM food_donations d
        LEFT JOIN food_categories c ON d.category_id = c.category_id
        LEFT JOIN users u ON d.donor_id = u.user_id
        LEFT JOIN user_settings s ON u.user_id = s.user_id
        WHERE d.status = 'Available'
        ORDER BY d.created_at DESC, d.donation_id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rawDonations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log("Error listing donations: " . $e->getMessage());
    $rawDonations = [];
}

$donations = array_map(function($item) {
    if (!empty($item['discretion_mode']) && (int)$item['discretion_mode'] === 1) {
        $item['donor_name'] = 'Anonymous Verified Donor';
    } else {
        $item['donor_name'] = $item['raw_donor_name'] ?? 'Community Donor';
    }
    unset($item['raw_donor_name'], $item['discretion_mode']);
    return $item;
}, $rawDonations);

echo json_encode([
    "success" => true,
    "page" => $page,
    "limit" => $limit,
    "donations" => $donations
]);