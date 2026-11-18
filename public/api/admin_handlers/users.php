<?php
declare(strict_types=1);
try {
    $nameCol = hasTableColumn($pdo, 'users', 'name') ? 'u.name' : (hasTableColumn($pdo, 'users', 'full_name') ? 'u.full_name AS name' : 'u.email AS name');
    $orgCol = hasTableColumn($pdo, 'users', 'org_name') ? 'u.org_name' : "'' AS org_name";
    $createdCol = hasTableColumn($pdo, 'users', 'created_at') ? 'u.created_at' : 'NOW() AS created_at';
    $statusCol = hasTableColumn($pdo, 'users', 'status') ? 'u.status' : "'Active' AS status";
    $verifyCol = hasTableColumn($pdo, 'users', 'is_verified') ? 'u.is_verified' : '1 AS is_verified';
    $orderClause = hasTableColumn($pdo, 'users', 'created_at') ? 'ORDER BY u.created_at DESC' : 'ORDER BY u.user_id DESC';

    $stmt = $pdo->query("
        SELECT u.user_id, {$nameCol}, {$orgCol}, u.email, u.role, {$statusCol}, {$verifyCol}, {$createdCol},
               s.phone_number, s.pickup_instructions
        FROM users u
        LEFT JOIN user_settings s ON u.user_id = s.user_id
        {$orderClause}
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'users' => $users]);
} catch (Throwable $e) {
    error_log("Error in users handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch users.']);
}