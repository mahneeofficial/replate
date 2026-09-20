<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in donations.php: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(["error" => "ERR_SYS_01: Internal Server Error"]);
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
    echo json_encode(["error" => "ERR_SYS_00: Database configuration missing."]);
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
    echo json_encode(["error" => "ERR_SYS_00: Invalid database connection."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'metrics') {
            $totalClaims = 0;
            $activeRequests = 0;
            $co2Saved = 0.0;

            try {
                $stmtTotal = $pdo->query("SELECT COUNT(*) FROM food_donations WHERE status = 'Collected'");
                $totalClaims = (int)$stmtTotal->fetchColumn();
            } catch (Throwable $e) {
                error_log("Metrics error (totalClaims): " . $e->getMessage());
            }

            try {
                $stmtActive = $pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status = 'Pending'");
                $activeRequests = (int)$stmtActive->fetchColumn();
            } catch (Throwable $e) {
                error_log("Metrics error (activeRequests): " . $e->getMessage());
            }

            try {
                $stmtWeight = $pdo->query("SELECT COALESCE(SUM(CAST(quantity AS DECIMAL(10,2))), 0) FROM food_donations WHERE status = 'Collected'");
                $co2Saved = round(((float)$stmtWeight->fetchColumn()) * 2.5, 1);
            } catch (Throwable $e) {
                error_log("Metrics error (co2Saved): " . $e->getMessage());
            }

            echo json_encode([
                "success" => true,
                "metrics" => [
                    "total_claims" => $totalClaims,
                    "active_requests" => $activeRequests,
                    "co2_saved" => $co2Saved
                ]
            ]);
            exit;
        }

        if ($action === 'my_listings') {
            if (!$currentUserId) {
                http_response_code(401);
                echo json_encode(["error" => "ERR_AUTH_05: Unauthorized session."]);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT d.donation_id, d.food_name, d.quantity, d.unit, d.expiry_date, d.status, 
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

        // Default Action: List Available Donations (Paginated)
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
                ORDER BY d.donation_id DESC
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
            echo json_encode(["error" => "ERR_AUTH_05: Please log in to post a donation."]);
            exit;
        }

        $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $roleStmt->execute([$currentUserId]);
        $dbRole = strtolower((string)$roleStmt->fetchColumn());

        if (!in_array($dbRole, ['donor', 'admin'], true)) {
            http_response_code(403);
            echo json_encode(["error" => "ERR_AUTH_06: Only donors and administrators can post food donations."]);
            exit;
        }

        if (class_exists('Security')) {
            Security::enforceRateLimit('post_donation', 10, 300);
        }

        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);

        if (!is_array($data)) {
            $data = $_POST;
        }

        $foodName = class_exists('Security') ? Security::cleanInput($data['food_name'] ?? $data['title'] ?? '') : trim($data['food_name'] ?? $data['title'] ?? '');
        $quantityRaw = trim((string)($data['quantity'] ?? ''));
        $unit = class_exists('Security') ? Security::cleanInput($data['unit'] ?? 'kg') : trim($data['unit'] ?? 'kg');
        $categoryId = (int)($data['category_id'] ?? 1);
        if ($categoryId <= 0) { $categoryId = 1; }
        $location = class_exists('Security') ? Security::cleanInput($data['pickup_location'] ?? $data['location'] ?? '') : trim($data['pickup_location'] ?? $data['location'] ?? '');
        $expiryDateRaw = trim($data['expiry_date'] ?? '');

        if (empty($foodName) || empty($quantityRaw) || empty($expiryDateRaw) || empty($location)) {
            http_response_code(400);
            echo json_encode(["error" => "ERR_VAL_01: All fields are required."]);
            exit;
        }

        if (!is_numeric($quantityRaw) || (float)$quantityRaw <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "ERR_VAL_01: Quantity must be a valid positive number."]);
            exit;
        }

        $parsedExpiry = strtotime($expiryDateRaw);
        if ($parsedExpiry === false || $parsedExpiry < strtotime('today')) {
            http_response_code(400);
            echo json_encode(["error" => "ERR_VAL_02: Expiry date must be today or a future date."]);
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
            $donationId = $pdo->lastInsertId();

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
            echo json_encode(["error" => "ERR_SYS_01: Failed to post donation."]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(["error" => "Method not allowed."]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in donations.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "error" => "ERR_SYS_01: Database operation failed."
    ]);
}