<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;
$rawRole = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? $_SESSION['user']['user_type'] ?? 'recipient';
$currentUserRole = strtolower(trim((string)$rawRole));

try {
    if ($currentUserRole === 'donor') {
        // Donor metrics: Total food posts, pending/approved requests on their items, total weight donated
        $metricsStmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM food_donations WHERE donor_id = ?) AS total_claims,
                (SELECT COUNT(*) FROM donation_requests r JOIN food_donations d ON r.donation_id = d.donation_id WHERE d.donor_id = ? AND r.status IN ('Pending', 'Approved')) AS active_requests,
                (SELECT COALESCE(SUM(quantity), 0) FROM food_donations WHERE donor_id = ? AND status IN ('Claimed', 'Collected', 'Completed')) AS total_weight
        ");
        $metricsStmt->execute([$currentUserId, $currentUserId, $currentUserId]);
    } elseif ($currentUserRole === 'recipient') {
        // Recipient metrics: Total claims submitted (excluding Rejected/Cancelled), active (Pending/Approved) claims, total weight claimed
        $metricsStmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM donation_requests WHERE recipient_id = ? AND status NOT IN ('Rejected', 'Cancelled')) AS total_claims,
                (SELECT COUNT(*) FROM donation_requests WHERE recipient_id = ? AND status IN ('Pending', 'Approved')) AS active_requests,
                (SELECT COALESCE(SUM(r.quantity_requested), 0) FROM donation_requests r WHERE r.recipient_id = ? AND r.status IN ('Pending', 'Approved', 'Completed')) AS total_weight
        ");
        $metricsStmt->execute([$currentUserId, $currentUserId, $currentUserId]);
    } else {
        // Admin metrics: Platform-wide overview
        $metricsStmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM food_donations) AS total_claims,
                (SELECT COUNT(*) FROM donation_requests WHERE status IN ('Pending', 'Approved')) AS active_requests,
                (SELECT COALESCE(SUM(quantity), 0) FROM food_donations WHERE status IN ('Claimed', 'Collected', 'Completed')) AS total_weight
        ");
    }

    $metricsData = $metricsStmt->fetch(PDO::FETCH_ASSOC);
    $co2Saved = round(((float)($metricsData['total_weight'] ?? 0)) * 2.5, 1);

    echo json_encode([
        "success" => true,
        "role" => $currentUserRole,
        "metrics" => [
            "total_claims" => (int)($metricsData['total_claims'] ?? 0),
            "active_requests" => (int)($metricsData['active_requests'] ?? 0),
            "co2_saved" => $co2Saved
        ]
    ]);
} catch (Throwable $e) {
    error_log("Metrics error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Failed to load metrics."]);
}