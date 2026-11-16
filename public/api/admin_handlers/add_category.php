<?php
declare(strict_types=1);
try {
    $name = $cleanString((string)($inputData['name'] ?? ''));
    $description = $cleanString((string)($inputData['description'] ?? ''));

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: Category name is required.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO food_categories (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);
    echo json_encode(['success' => true, 'message' => 'Food category added successfully.']);
} catch (Throwable $e) {
    error_log("Error in add_category handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}