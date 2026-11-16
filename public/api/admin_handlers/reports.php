<?php
declare(strict_types=1);
try {
    $reporterNameCol = hasTableColumn($pdo, 'users', 'name') ? 'u.name' : (hasTableColumn($pdo, 'users', 'full_name') ? 'u.full_name' : 'u.email');

    $stmt = $pdo->query("
        SELECT r.*, {$reporterNameCol} AS reporter_name, u.email AS reporter_email
        FROM reports r
        LEFT JOIN users u ON r.reporter_id = u.user_id
        ORDER BY r.created_at DESC
    ");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'reports' => $reports]);
} catch (Throwable $e) {
    error_log("Error in reports handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch reports.']);
}