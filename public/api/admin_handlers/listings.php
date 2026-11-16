<?php
declare(strict_types=1);
try {
    $donorNameCol = hasTableColumn($pdo, 'users', 'name') ? 'u.name' : (hasTableColumn($pdo, 'users', 'full_name') ? 'u.full_name' : 'u.email');

    $stmt = $pdo->query("
        SELECT f.donation_id, f.food_name, f.quantity, f.unit, f.expiry_date, f.status, 
               {$donorNameCol} AS donor_name, c.name AS category_name
        FROM food_donations f
        JOIN users u ON f.donor_id = u.user_id
        LEFT JOIN food_categories c ON f.category_id = c.category_id
        ORDER BY f.created_at DESC
    ");
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'listings' => $listings]);
} catch (Throwable $e) {
    error_log("Error in listings handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch listings.']);
}