<?php
declare(strict_types=1);
try {
    $nameCol = hasTableColumn($pdo, 'users', 'name') ? 'name' : (hasTableColumn($pdo, 'users', 'full_name') ? 'full_name AS name' : 'email AS name');
    $createdCol = hasTableColumn($pdo, 'users', 'created_at') ? 'created_at' : 'NOW() AS created_at';
    $statusCol = hasTableColumn($pdo, 'users', 'status') ? 'status' : "'Active' AS status";
    $verifyCol = hasTableColumn($pdo, 'users', 'is_verified') ? 'is_verified' : '1 AS is_verified';

    $orderClause = hasTableColumn($pdo, 'users', 'created_at') ? 'ORDER BY created_at DESC' : 'ORDER BY user_id DESC';

    $stmt = $pdo->query("SELECT user_id, {$nameCol}, email, role, {$statusCol}, {$verifyCol}, {$createdCol} FROM users {$orderClause}");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'users' => $users]);
} catch (Throwable $e) {
    error_log("Error in users handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch users.']);
}