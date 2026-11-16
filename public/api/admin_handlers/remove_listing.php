<?php
declare(strict_types=1);
try {
    $donationId = (int)($inputData['donation_id'] ?? 0);

    if (!$donationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: Donation ID is required.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM food_donations WHERE donation_id = ?");
    $stmt->execute([$donationId]);
    echo json_encode(['success' => true, 'message' => 'Listing removed successfully.']);
} catch (Throwable $e) {
    error_log("Error in remove_listing handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}