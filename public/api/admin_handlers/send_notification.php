<?php
declare(strict_types=1);
try {
    $userId = (int)($inputData['user_id'] ?? 0);
    $target = $cleanString((string)($inputData['target'] ?? 'all'));
    $title = $cleanString((string)($inputData['title'] ?? ''));
    $message = $cleanString((string)($inputData['message'] ?? ''));
    $type = $cleanString((string)($inputData['type'] ?? 'info'));

    if (empty($title) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: Title and message are required.']);
        exit;
    }

    if ($userId > 0) {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $type]);
    } else {
        $query = "SELECT user_id FROM users";
        if ($target === 'donors') {
            $query .= " WHERE LOWER(role) = 'donor'";
        } elseif ($target === 'recipients') {
            $query .= " WHERE LOWER(role) = 'recipient'";
        }

        $users = $pdo->query($query)->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($users)) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            foreach ($users as $uId) {
                $stmt->execute([$uId, $title, $message, $type]);
            }
            $pdo->commit();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Notification sent successfully.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in send_notification handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}