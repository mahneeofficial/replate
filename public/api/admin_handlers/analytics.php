<?php
declare(strict_types=1);
try {
    $rescued = 0.0;
    $active = 0;
    $completed = 0;

    if (hasTableColumn($pdo, 'food_donations', 'quantity')) {
        $stmt1 = $pdo->query("SELECT COALESCE(SUM(CAST(quantity AS DECIMAL(10,2))), 0) AS total_rescued FROM food_donations WHERE status IN ('Collected', 'Completed')");
        $rescued = (float)($stmt1->fetch(PDO::FETCH_ASSOC)['total_rescued'] ?? 0);

        $stmt2 = $pdo->query("SELECT COUNT(*) AS active_count FROM food_donations WHERE status = 'Available'");
        $active = (int)($stmt2->fetch(PDO::FETCH_ASSOC)['active_count'] ?? 0);
    }

    try {
        $stmt3 = $pdo->query("SELECT COUNT(*) AS completed_claims FROM donation_requests WHERE status IN ('Approved', 'Completed')");
        $completed = (int)($stmt3->fetch(PDO::FETCH_ASSOC)['completed_claims'] ?? 0);
    } catch (Throwable $e) {
        $completed = 0;
    }

    $co2_avoided = round($rescued * 2.5, 1);

    echo json_encode([
        'success' => true,
        'analytics' => [
            'total_rescued' => number_format($rescued) . ' kg',
            'active_listings' => $active,
            'completed_claims' => $completed,
            'co2_avoided' => number_format($co2_avoided) . ' kg'
        ]
    ]);
} catch (Throwable $e) {
    error_log("Error in analytics handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Failed to fetch analytics.']);
}