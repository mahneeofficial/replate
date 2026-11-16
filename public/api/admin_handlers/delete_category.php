<?php
declare(strict_types=1);
try {
    $categoryId = (int)($inputData['category_id'] ?? $inputData['id'] ?? 0);

    if (!$categoryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: Category ID is required.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM food_categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    echo json_encode(['success' => true, 'message' => 'Food category deleted successfully.']);
} catch (Throwable $e) {
    error_log("Error in delete_category handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}