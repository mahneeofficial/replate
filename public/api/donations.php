<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in donations.php: " . $e->getMessage());
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

/**
 * Helper function to record audit logs
 */
function recordAuditLog(PDO $pdo, ?int $userId, string $eventType, string $details, string $logType = 'info'): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, event_type, action_details, log_type, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $eventType, $details, $logType, $ip]);
    } catch (Throwable $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'metrics') {
            try {
                $metricsStmt = $pdo->query("
                    SELECT 
                        (SELECT COUNT(*) FROM food_donations WHERE status IN ('Collected', 'Completed')) as total_claims,
                        (SELECT COUNT(*) FROM donation_requests WHERE status = 'Pending') as active_requests,
                        (SELECT COALESCE(SUM(CAST(quantity AS DECIMAL(10,2))), 0) FROM food_donations WHERE status IN ('Collected', 'Completed')) as total_weight
                ");
                $metricsData = $metricsStmt->fetch(PDO::FETCH_ASSOC);

                $co2Saved = round(((float)($metricsData['total_weight'] ?? 0)) * 2.5, 1);

                echo json_encode([
                    "success" => true,
                    "metrics" => [
                        "total_claims" => (int)($metricsData['total_claims'] ?? 0),
                        "active_requests" => (int)($metricsData['active_requests'] ?? 0),
                        "co2_saved" => $co2Saved
                    ]
                ]);
                exit;
            } catch (Throwable $e) {
                error_log("Metrics error: " . $e->getMessage());
                echo json_encode(["success" => false, "error" => "Failed to load metrics."]);
                exit;
            }
        }

        if ($action === 'my_listings') {
            if (!$currentUserId) {
                http_response_code(401);
                echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Unauthorized session."]);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT d.donation_id, d.food_name, d.quantity, d.unit, d.expiry_date, d.status, d.pickup_location,
                           COALESCE(c.name, 'General Surplus') AS category_name
                    FROM food_donations d
                    LEFT JOIN food_categories c ON d.category_id = c.category_id
                    WHERE d.donor_id = ?
                    ORDER BY d.donation_id DESC
                    LIMIT 100
                ");
                $stmt->execute([$currentUserId]);
                $donations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                error_log("Error fetching my_listings: " . $e->getMessage());
                $donations = [];
            }

            echo json_encode(["success" => true, "donations" => $donations]);
            exit;
        }

        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($page - 1) * $limit;

        try {
            $stmt = $pdo->prepare("
                SELECT d.donation_id, d.food_name, d.quantity, d.unit, d.expiry_date, d.pickup_location, d.status,
                       COALESCE(u.org_name, u.name, u.email, 'Community Donor') AS raw_donor_name, 
                       COALESCE(c.name, 'General Surplus') AS category_name,
                       s.discretion_mode
                FROM food_donations d
                LEFT JOIN food_categories c ON d.category_id = c.category_id
                LEFT JOIN users u ON d.donor_id = u.user_id
                LEFT JOIN user_settings s ON u.user_id = s.user_id
                WHERE d.status = 'Available'
                ORDER BY d.created_at DESC, d.donation_id DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rawDonations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log("Error listing donations: " . $e->getMessage());
            $rawDonations = [];
        }

        $donations = array_map(function($item) {
            if (!empty($item['discretion_mode']) && (int)$item['discretion_mode'] === 1) {
                $item['donor_name'] = 'Anonymous Verified Donor';
            } else {
                $item['donor_name'] = $item['raw_donor_name'] ?? 'Community Donor';
            }
            unset($item['raw_donor_name'], $item['discretion_mode']);
            return $item;
        }, $rawDonations);

        echo json_encode([
            "success" => true,
            "page" => $page,
            "limit" => $limit,
            "donations" => $donations
        ]);
        exit;
    }

    if ($method === 'POST') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Please log in to post a donation."]);
            exit;
        }

        $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $roleStmt->execute([$currentUserId]);
        $dbRole = strtolower((string)$roleStmt->fetchColumn());

        if (!in_array($dbRole, ['donor', 'admin'], true)) {
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_06: Only donors and administrators can post food donations."]);
            exit;
        }

        if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
            Security::enforceRateLimit('post_donation', 10, 300);
        }

        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $cleanString = function(string $val): string {
            if (class_exists('Security')) {
                if (method_exists('Security', 'cleanInput')) return Security::cleanInput($val);
                if (method_exists('Security', 'sanitize')) return Security::sanitize($val);
            }
            return strip_tags(trim($val));
        };

        $foodName = $cleanString((string)($data['food_name'] ?? $data['title'] ?? ''));
        $quantityRaw = trim((string)($data['quantity'] ?? ''));
        $unit = $cleanString((string)($data['unit'] ?? 'kg'));
        $categoryId = max(1, (int)($data['category_id'] ?? 1));
        $location = $cleanString((string)($data['pickup_location'] ?? $data['location'] ?? ''));
        $expiryDateRaw = trim((string)($data['expiry_date'] ?? ''));

        if (empty($foodName) || empty($quantityRaw) || empty($expiryDateRaw) || empty($location)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_01: All fields are required."]);
            exit;
        }

        if (!is_numeric($quantityRaw) || (float)$quantityRaw <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_01: Quantity must be a valid positive number."]);
            exit;
        }

        $parsedExpiry = strtotime($expiryDateRaw);
        if ($parsedExpiry === false || strtotime(date('Y-m-d', $parsedExpiry)) < strtotime(date('Y-m-d'))) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_02: Expiry date must be today or a future date."]);
            exit;
        }

        $formattedExpiry = date('Y-m-d H:i:s', $parsedExpiry);
        $quantity = (string)(float)$quantityRaw;

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO food_donations (donor_id, category_id, food_name, quantity, unit, expiry_date, pickup_location, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Available')
        ");

        if ($stmt->execute([$currentUserId, $categoryId, $foodName, $quantity, $unit, $formattedExpiry, $location])) {
            $donationId = (int)$pdo->lastInsertId();

            try {
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $notifStmt->execute([
                    $currentUserId, 
                    'Surplus Food Posted', 
                    "Your listing '{$foodName}' is now active.", 
                    'info'
                ]);
            } catch (Throwable $e) {
                error_log("Failed to insert notification: " . $e->getMessage());
            }

            recordAuditLog($pdo, (int)$currentUserId, 'DONATION_CREATED', "Created donation #{$donationId} ({$foodName}, {$quantity} {$unit})", 'info');

            $pdo->commit();
            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Donation posted successfully.",
                "id" => $donationId
            ]);
        } else {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "ERR_SYS_01: Failed to post donation."]);
        }
        exit;
    }

    if ($method === 'DELETE') {
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(["success" => false, "error" => "ERR_AUTH_05: Unauthorized."]);
            exit;
        }

        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        if (!is_array($data)) {
            $data = [];
        }
        $donationId = (int)($data['donation_id'] ?? $_GET['donation_id'] ?? 0);

        if (!$donationId) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_VAL_01: Donation ID required."]);
            exit;
        }

        $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $roleStmt->execute([$currentUserId]);
        $dbRole = strtolower((string)$roleStmt->fetchColumn());

        if ($dbRole === 'admin') {
            $stmt = $pdo->prepare("DELETE FROM food_donations WHERE donation_id = ?");
            $stmt->execute([$donationId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM food_donations WHERE donation_id = ? AND donor_id = ? AND status = 'Available'");
            $stmt->execute([$donationId, $currentUserId]);
        }

        if ($stmt->rowCount() > 0) {
            recordAuditLog($pdo, (int)$currentUserId, 'DONATION_DELETED', "Deleted donation listing #{$donationId}", 'warning');
            echo json_encode(["success" => true, "message" => "Donation removed successfully."]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "ERR_REQ_02: Donation not found, already requested, or you lack permission."]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in donations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "ERR_SYS_01: Database operation failed."
    ]);
}