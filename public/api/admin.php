<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in admin.php: " . $e->getMessage());
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

// Verify Admin Role
$userRole = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (empty($userRole) && $currentUserId) {
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $roleStmt->execute([$currentUserId]);
    $userRole = (string)$roleStmt->fetchColumn();
}

if (strtolower((string)$userRole) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'ERR_AUTH_03: Unauthorized. Admin privileges required.']);
    exit;
}

// Rate Limiting for Admin Mutations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('admin_action', 30, 300);
}

// Parse Input
$rawInput = file_get_contents('php://input');
$inputData = !empty($rawInput) ? json_decode($rawInput, true) : null;
if (!is_array($inputData)) {
    $inputData = $_POST;
}

$action = $_GET['action'] ?? $inputData['action'] ?? '';

// Helper function for sanitization compatibility
$cleanString = function (string $val): string {
    $val = trim($val);
    if (class_exists('Security')) {
        if (method_exists('Security', 'cleanInput')) {
            return Security::cleanInput($val);
        }
        if (method_exists('Security', 'sanitize')) {
            return Security::sanitize($val);
        }
    }
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
};

try {
    switch ($action) {
        case 'analytics':
            $stmt1 = $pdo->query("SELECT COALESCE(SUM(CAST(quantity AS DECIMAL(10,2))), 0) AS total_rescued FROM food_donations WHERE status IN ('Collected', 'Completed')");
            $rescued = (float)($stmt1->fetch(PDO::FETCH_ASSOC)['total_rescued'] ?? 0);

            $stmt2 = $pdo->query("SELECT COUNT(*) AS active_count FROM food_donations WHERE status = 'Available'");
            $active = (int)($stmt2->fetch(PDO::FETCH_ASSOC)['active_count'] ?? 0);

            $stmt3 = $pdo->query("SELECT COUNT(*) AS completed_claims FROM donation_requests WHERE status IN ('Approved', 'Completed')");
            $completed = (int)($stmt3->fetch(PDO::FETCH_ASSOC)['completed_claims'] ?? 0);

            $co2_avoided = round($rescued * 2.5, 1);

            echo json_encode([
                'success' => true,
                'analytics' => [
                    'total_rescued' => number_format($rescued) . ' kg',
                    'active_listings' => $active,
                    'completed_claims' => $completed,
                    'co2_avoided' => number_format($co2_avoided) . ' kg'
                ]
            ]);
            break;

        case 'users':
            $stmt = $pdo->query("SELECT user_id, name, email, role, status, is_verified, created_at FROM users ORDER BY created_at DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'verify_account':
            $targetUserId = (int)($inputData['user_id'] ?? 0);
            $verifyStatus = (int)($inputData['is_verified'] ?? 1);

            if (!$targetUserId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: User ID is required.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE user_id = ?");
            $stmt->execute([$verifyStatus, $targetUserId]);

            $statusText = $verifyStatus === 1 ? 'verified' : 'unverified';
            echo json_encode(['success' => true, 'message' => "Account successfully marked as {$statusText}."]);
            break;

        case 'update_user_status':
            $targetUserId = (int)($inputData['user_id'] ?? 0);
            $status = $cleanString((string)($inputData['status'] ?? ''));

            if (!$targetUserId || empty($status)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: User ID and status are required.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->execute([$status, $targetUserId]);
            echo json_encode(['success' => true, 'message' => "User account status updated to {$status}."]);
            break;

        case 'listings':
            $stmt = $pdo->query("
                SELECT f.donation_id, f.food_name, f.quantity, f.unit, f.expiry_date, f.status, 
                       u.name AS donor_name, c.name AS category_name
                FROM food_donations f
                JOIN users u ON f.donor_id = u.user_id
                LEFT JOIN food_categories c ON f.category_id = c.category_id
                ORDER BY f.created_at DESC
            ");
            $listings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'listings' => $listings]);
            break;

        case 'remove_listing':
            $donationId = (int)($inputData['donation_id'] ?? 0);

            if (!$donationId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: Donation ID is required.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM food_donations WHERE donation_id = ?");
            $stmt->execute([$donationId]);
            echo json_encode(['success' => true, 'message' => 'Listing removed successfully.']);
            break;

        case 'donation_requests':
            $stmt = $pdo->query("
                SELECT r.request_id, r.status, r.created_at,
                       f.food_name, f.quantity, f.unit,
                       u_req.name AS recipient_name, u_donor.name AS donor_name
                FROM donation_requests r
                JOIN food_donations f ON r.donation_id = f.donation_id
                JOIN users u_req ON r.recipient_id = u_req.user_id
                JOIN users u_donor ON f.donor_id = u_donor.user_id
                ORDER BY r.created_at DESC
            ");
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'requests' => $requests]);
            break;

        case 'categories':
            $stmt = $pdo->query("
                SELECT c.category_id, c.category_id AS id, c.name, c.description, c.created_at, 
                       COUNT(f.donation_id) AS listings_count
                FROM food_categories c
                LEFT JOIN food_donations f ON c.category_id = f.category_id
                GROUP BY c.category_id, c.name, c.description, c.created_at
                ORDER BY c.name ASC
            ");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;

        case 'add_category':
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
            break;

        case 'delete_category':
            $categoryId = (int)($inputData['category_id'] ?? $inputData['id'] ?? 0);

            if (!$categoryId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: Category ID is required.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM food_categories WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            echo json_encode(['success' => true, 'message' => 'Food category deleted successfully.']);
            break;

        case 'reports':
            $stmt = $pdo->query("
                SELECT r.*, u.name AS reporter_name, u.email AS reporter_email
                FROM reports r
                LEFT JOIN users u ON r.reporter_id = u.user_id
                ORDER BY r.created_at DESC
            ");
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'reports' => $reports]);
            break;

        case 'audit_logs':
            $stmt = $pdo->query("
                SELECT n.id, n.title, n.message, n.type, n.created_at, u.name AS user_name, u.email 
                FROM notifications n
                JOIN users u ON n.user_id = u.user_id
                ORDER BY n.created_at DESC LIMIT 50
            ");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        case 'send_notification':
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
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ERR_SYS_02: Invalid action requested.']);
            break;
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in admin.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
}