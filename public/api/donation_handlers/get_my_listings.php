<?php
declare(strict_types=1);
if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Unauthorized session."]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT d.donation_id, d.food_name, d.quantity, d.unit, d.expiry_date, d.status, d.pickup_location,
               COALESCE(c.name, 'General Surplus') AS category_name
        FROM food_donations d
        LEFT JOIN food_categories c ON d.category_id = c.category_id
        WHERE d.donor_id = ?
        ORDER BY d.donation_id DESC
        LIMIT 100
    ");
    $stmt->execute([$currentUserId]);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log("Error fetching my_listings: " . $e->getMessage());
    $donations = [];
}

echo json_encode(["success" => true, "donations" => $donations]);