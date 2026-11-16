<?php
declare(strict_types=1);
checkRememberCookie($pdo);

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Not authenticated."]);
    return;
}

try {
    $stmt = $pdo->prepare("
        SELECT user_id, name, org_name, email, role, status,
               COALESCE(verification_status, 'Pending') AS verification_status
        FROM users 
        WHERE user_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user['role'] = strtolower(trim((string)$user['role']));
        $_SESSION['role'] = $user['role'];
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['role'] = $user['role'];
        }
        echo json_encode(["success" => true, "user" => $user]);
    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "ERR_AUTH_02: User not found."]);
    }
} catch (Throwable $e) {
    error_log("Error retrieving user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Database error retrieving user."]);
}