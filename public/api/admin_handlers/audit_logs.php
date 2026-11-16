<?php
declare(strict_types=1);
try {
    $userNameCol = hasTableColumn($pdo, 'users', 'name') ? 'u.name' : (hasTableColumn($pdo, 'users', 'full_name') ? 'u.full_name' : 'u.email');

    $stmt = $pdo->query("
        SELECT n.id, n.title, n.message, n.type, n.created_at, {$userNameCol} AS user_name, u.email 
        FROM notifications n
        JOIN users u ON n.user_id = u.user_id
        ORDER BY n.created_at DESC LIMIT 50
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'logs' => $logs]);
} catch (Throwable $e) {
    error_log("Error in audit_logs handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch audit logs.']);
}