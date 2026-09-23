<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in requests.php: " . $e->getMessage());
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

function recordAuditLog(PDO $pdo, ?int $userId, string $eventType, string $details, string $logType = 'info'): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, event_type, action_details, log_type, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $eventType, $details, $logType, $ip]);
    } catch (Throwable $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

$currentUserId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_01: Please log in to perform this action."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'my_requests';

        if ($action === 'incoming') {
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
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT r.request_id, r.donation_id, r.quantity_requested, r.status, r.created_at,
                   d.food_name, d.pickup_location, 
                   COALESCE(u.org_name, u.name, u.email, 'Community Donor') AS raw_donor_name,
                   s.discretion_mode
            FROM donation_requests r
            JOIN food_donations d ON r.donation_id = d.donation_id
            LEFT JOIN users u ON d.donor_id = u.user_id
            LEFT JOIN user_settings s ON u.user_id = s.user_id
            WHERE r.recipient_id = ?
            ORDER BY r.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$currentUserId]);
        $rawRequests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $requests = array_map(function($item) {
            if (!empty($item['discretion_mode']) && (int)$item['discretion_mode'] === 1) {
                $item['donor_name'] = 'Anonymous Verified Donor';
            } else {
                $item['donor_name'] = $item['raw_donor_name'] ?? 'Community Donor';
            }
            unset($item['raw_donor_name'], $item['discretion_mode']);
            return $item;
        }, $rawRequests);

        echo json_encode(["success" => true, "requests" => $requests]);
        exit;
    }

    if ($method === 'POST') {
        $rawInput = file_get_contents("php://input");
        $data = !empty($rawInput) ? json_decode($rawInput, true) : $_POST;

        if (!is_array($data)) {
            $data = $_POST;
        }

        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
                Security::enforceRateLimit('create_request', 15, 300);
            }

            $donationId = (int)($data['donation_id'] ?? 0);

            if (!$donationId) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_VAL_01: Donation ID is required."]);
                exit;
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT donor_id, food_name, status, quantity 
                FROM food_donations 
                WHERE donation_id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$donationId]);
            $donation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$donation || $donation['status'] !== 'Available') {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_REQ_01: Donation is no longer available."]);
                exit;
            }

            if ((int)$donation['donor_id'] === (int)$currentUserId) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_REQ_03: You cannot request your own donation."]);
                exit;
            }

            $stmtCheck = $pdo->prepare("
                SELECT request_id 
                FROM donation_requests 
                WHERE donation_id = ? AND recipient_id = ? AND status NOT IN ('Rejected', 'Cancelled')
            ");
            $stmtCheck->execute([$donationId, $currentUserId]);
            if ($stmtCheck->fetch()) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_REQ_04: You already have an active request for this item."]);
                exit;
            }

            $stmtReq = $pdo->prepare("
                INSERT INTO donation_requests (donation_id, recipient_id, quantity_requested, status) 
                VALUES (?, ?, ?, 'Pending')
            ");
            $stmtReq->execute([$donationId, $currentUserId, $donation['quantity']]);
            $requestId = (int)$pdo->lastInsertId();

            try {
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $notifStmt->execute([
                    $donation['donor_id'], 
                    'New Food Request', 
                    "A recipient requested your donation '{$donation['food_name']}'.", 
                    'request'
                ]);
            } catch (Throwable $e) {
                error_log("Failed to insert notification: " . $e->getMessage());
            }

            recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_CREATED', "Submitted claim request #{$requestId} for donation #{$donationId}", 'info');

            $pdo->commit();
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Donation requested successfully."]);
            exit;
        }

        if ($action === 'approve' || $action === 'reject') {
            if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
                Security::enforceRateLimit('manage_request', 20, 300);
            }

            $requestId = (int)($data['request_id'] ?? 0);

            if (!$requestId) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_VAL_02: Request ID is required."]);
                exit;
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT r.request_id, r.recipient_id, r.donation_id, r.status AS request_status, d.food_name, d.donor_id 
                FROM donation_requests r 
                JOIN food_donations d ON r.donation_id = d.donation_id 
                WHERE r.request_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$requestId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "ERR_REQ_02: Request not found."]);
                exit;
            }

            if ((int)$req['donor_id'] !== (int)$currentUserId) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode(["success" => false, "error" => "ERR_AUTH_02: You do not have permission to manage this request."]);
                exit;
            }

            if ($req['request_status'] !== 'Pending') {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_REQ_05: Request has already been processed."]);
                exit;
            }

            $recipientId = (int)$req['recipient_id'];
            $donationId = (int)$req['donation_id'];
            $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';
            $notifTitle = ($action === 'approve') ? 'Request Approved' : 'Request Rejected';
            $notifType = ($action === 'approve') ? 'request' : 'warning';
            $notifMsg = ($action === 'approve') 
                ? "Your request for '{$req['food_name']}' was approved!" 
                : "Your request for '{$req['food_name']}' was rejected.";

            $stmt = $pdo->prepare("UPDATE donation_requests SET status = ? WHERE request_id = ?");
            $stmt->execute([$newStatus, $requestId]);

            if ($action === 'approve') {
                $stmtDon = $pdo->prepare("UPDATE food_donations SET status = 'Collected' WHERE donation_id = ?");
                $stmtDon->execute([$donationId]);

                $stmtPendingOthers = $pdo->prepare("SELECT recipient_id FROM donation_requests WHERE donation_id = ? AND request_id != ? AND status = 'Pending'");
                $stmtPendingOthers->execute([$donationId, $requestId]);
                $otherRecipients = $stmtPendingOthers->fetchAll(PDO::FETCH_COLUMN) ?: [];

                $stmtOther = $pdo->prepare("UPDATE donation_requests SET status = 'Rejected' WHERE donation_id = ? AND request_id != ? AND status = 'Pending'");
                $stmtOther->execute([$donationId, $requestId]);

                foreach ($otherRecipients as $otherRecipientId) {
                    try {
                        $notifStmtOther = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                        $notifStmtOther->execute([
                            $otherRecipientId,
                            'Item No Longer Available',
                            "The donation '{$req['food_name']}' you requested was claimed by another user.",
                            'warning'
                        ]);
                    } catch (Throwable $e) {
                        error_log("Failed to insert notification: " . $e->getMessage());
                    }
                }

                recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_APPROVED', "Approved request #{$requestId} for donation #{$donationId}", 'info');
            } else {
                $stmtDon = $pdo->prepare("UPDATE food_donations SET status = 'Available' WHERE donation_id = ?");
                $stmtDon->execute([$donationId]);

                recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_REJECTED', "Rejected request #{$requestId} for donation #{$donationId}", 'warning');
            }

            try {
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $notifStmt->execute([$recipientId, $notifTitle, $notifMsg, $notifType]);
            } catch (Throwable $e) {
                error_log("Failed to insert notification: " . $e->getMessage());
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Request {$newStatus} successfully."]);
            exit;
        }

        if ($action === 'cancel') {
            $requestId = (int)($data['request_id'] ?? 0);

            if (!$requestId) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_VAL_02: Request ID is required."]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE donation_requests SET status = 'Cancelled' WHERE request_id = ? AND recipient_id = ? AND status = 'Pending'");
            $stmt->execute([$requestId, $currentUserId]);

            if ($stmt->rowCount() > 0) {
                recordAuditLog($pdo, (int)$currentUserId, 'REQUEST_CANCELLED', "Cancelled request #{$requestId}", 'info');
                echo json_encode(['success' => true, 'message' => "Request cancelled successfully."]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "ERR_REQ_06: Request could not be cancelled. It may already be processed."]);
            }
            exit;
        }

        http_response_code(400);
        echo json_encode(["success" => false, "error" => "ERR_VAL_00: Invalid request action."]);
        exit;
    }

    http_response_code(405);
    echo json_encode(["success" => false, "error" => "ERR_SYS_03: Method not allowed."]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in requests.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "ERR_SYS_01: Database operation failed."
    ]);
}