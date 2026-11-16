<?php
declare(strict_types=1);
try {
    $nameCol = hasTableColumn($pdo, 'users', 'name') ? 'name' : (hasTableColumn($pdo, 'users', 'full_name') ? 'full_name' : 'email');

    $stmt = $pdo->query("
        SELECT r.request_id, r.status, r.created_at,
               f.food_name, f.quantity, f.unit,
               u_req.{$nameCol} AS recipient_name, u_donor.{$nameCol} AS donor_name
        FROM donation_requests r
        JOIN food_donations f ON r.donation_id = f.donation_id
        JOIN users u_req ON r.recipient_id = u_req.user_id
        JOIN users u_donor ON f.donor_id = u_donor.user_id
        ORDER BY r.created_at DESC
    ");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'requests' => $requests]);
} catch (Throwable $e) {
    error_log("Error in donation_requests handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch donation requests.']);
}