<?php
declare(strict_types=1);
try {
    $userNameCol = hasTableColumn($pdo, 'users', 'name') ? 'u.name' : (hasTableColumn($pdo, 'users', 'full_name') ? 'u.full_name' : 'u.email');

    $stmt = $pdo->query("
        SELECT a.log_id AS id,
               a.event_type AS title,
               a.action_details AS message,
               a.log_type AS type,
               a.created_at,
               COALESCE({$userNameCol}, 'System / Deleted User') AS user_name,
               COALESCE(u.email, 'system@replate.local') AS email
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC
        LIMIT 100
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'logs' => $logs]);
} catch (Throwable $e) {
    error_log("Error in audit_logs handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch audit logs.']);
}