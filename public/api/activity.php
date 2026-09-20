<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in activity.php: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Internal Server Error"]);
    exit;
});

$configDb = __DIR__ . '/../../config/db.php';
$configSecurity = __DIR__ . '/../../config/Security.php';

if (!file_exists($configDb)) {
    $configDb = __DIR__ . '/../config/db.php';
    $configSecurity = __DIR__ . '/../config/Security.php';
}

if (!file_exists($configDb)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_00: Database configuration missing."]);
    exit;
}

require_once $configDb;

if (file_exists($configSecurity)) {
    require_once $configSecurity;
    if (class_exists('Security') && method_exists('Security', 'applySecurityHeaders')) {
        Security::applySecurityHeaders();
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_00: Invalid database connection."]);
    exit;
}

$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Session expired or unauthenticated."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;

    try {
        $stmt = $pdo->prepare("
            (
                SELECT 
                    'donation_posted' AS activity_type,
                    food_name AS title,
                    CONCAT('You listed ', quantity, ' ', IFNULL(unit, 'units'), ' of ', food_name) AS description,
                    created_at,
                    status
                FROM food_donations 
                WHERE donor_id = :uid1
            )
            UNION ALL
            (
                SELECT 
                    'request_made' AS activity_type,
                    d.food_name AS title,
                    CONCAT('You requested ', d.food_name) AS description,
                    r.created_at,
                    r.status
                FROM donation_requests r
                JOIN food_donations d ON r.donation_id = d.donation_id
                WHERE r.recipient_id = :uid2
            )
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':uid1', $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            "success" => true,
            "activities" => $activities
        ]);
        exit;

    } catch (Throwable $e) {
        error_log("Error in activity.php GET: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "ERR_SYS_01: Failed to fetch activity history."]);
        exit;
    }
}

http_response_code(405);
echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);