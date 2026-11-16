<?php
declare(strict_types=1);
$stmt = $pdo->prepare("
    SELECT r.request_id, r.donation_id, r.quantity_requested, r.status, r.created_at,
           d.food_name, 
           COALESCE(u.org_name, u.name, u.email, 'Anonymous User') AS recipient_name, 
           u.email AS recipient_email
    FROM donation_requests r
    JOIN food_donations d ON r.donation_id = d.donation_id
    JOIN users u ON r.recipient_id = u.user_id
    WHERE d.donor_id = ?
    ORDER BY r.created_at DESC
    LIMIT 100
");
$stmt->execute([$currentUserId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
echo json_encode(["success" => true, "requests" => $requests]);