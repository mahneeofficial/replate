<?php
declare(strict_types=1);
try {
    $stmt = $pdo->query("
        SELECT c.category_id, c.category_id AS id, c.name, c.description, c.created_at, 
               COUNT(f.donation_id) AS listings_count
        FROM food_categories c
        LEFT JOIN food_donations f ON c.category_id = f.category_id
        GROUP BY c.category_id, c.name, c.description, c.created_at
        ORDER BY c.name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'categories' => $categories]);
} catch (Throwable $e) {
    error_log("Error in admin categories handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch categories.']);
}