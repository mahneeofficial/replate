<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception in settings.php: " . $e->getMessage());
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

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "ERR_AUTH_01: Unauthorized access. Please log in."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $userStmt = $pdo->prepare("SELECT user_id, name, org_name, email FROM users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            $stmtInsert = $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
            $stmtInsert->execute([$userId]);
            
            $stmt->execute([$userId]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $responseData = [
            'full_name'        => $user['name'] ?? $user['org_name'] ?? '',
            'email'            => $user['email'] ?? '',
            'phone_number'     => $settings['phone_number'] ?? '',
            'address'          => $settings['pickup_instructions'] ?? '',
            'notify_inapp'     => (bool)($settings['notify_inapp'] ?? 1),
            'notify_whatsapp'  => (bool)($settings['notify_whatsapp'] ?? 1),
            'notify_email'     => (bool)($settings['notify_email'] ?? 1)
        ];

        echo json_encode(['success' => true, 'data' => $responseData]);
        exit;
    }

    if ($method === 'POST') {
        if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
            Security::enforceRateLimit('update_settings', 10, 60);
        }

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? $_POST;

        $fullName    = class_exists('Security') ? Security::sanitize($input['full_name'] ?? '') : trim($input['full_name'] ?? '');
        $email       = class_exists('Security') ? Security::sanitize($input['email'] ?? '') : trim($input['email'] ?? '');
        $phoneNumber = class_exists('Security') ? Security::sanitize($input['phone_number'] ?? '') : trim($input['phone_number'] ?? '');
        $address     = class_exists('Security') ? Security::sanitize($input['address'] ?? '') : trim($input['address'] ?? '');

        $currentPassword = $input['current_password'] ?? '';
        $newPassword     = $input['new_password'] ?? '';

        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ERR_VAL_01: Current password is required to set a new password.']);
                exit;
            }

            if (strlen($newPassword) < 8) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ERR_VAL_03: New password must be at least 8 characters long.']);
                exit;
            }

            $passStmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $passStmt->execute([$userId]);
            $userRecord = $passStmt->fetch(PDO::FETCH_ASSOC);

            if (!$userRecord || !password_verify($currentPassword, $userRecord['password_hash'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ERR_AUTH_02: Current password is incorrect.']);
                exit;
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $updatePass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $updatePass->execute([$hashedPassword, $userId]);
        }

        if (!empty($email)) {
            $checkEmail = $pdo->prepare("SELECT user_id FROM users WHERE LOWER(email) = LOWER(?) AND user_id != ?");
            $checkEmail->execute([$email, $userId]);
            if ($checkEmail->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ERR_VAL_02: Email address is already in use by another account.']);
                exit;
            }
        }

        $updateUser = $pdo->prepare("
            UPDATE users 
            SET name = COALESCE(NULLIF(?, ''), name), 
                email = COALESCE(NULLIF(?, ''), email) 
            WHERE user_id = ?
        ");
        $updateUser->execute([$fullName, $email, $userId]);

        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            if (!empty($fullName)) $_SESSION['user']['name'] = $fullName;
            if (!empty($email)) $_SESSION['user']['email'] = $email;
        }

        $checkSettings = $pdo->prepare("SELECT user_id FROM user_settings WHERE user_id = ?");
        $checkSettings->execute([$userId]);
        if (!$checkSettings->fetch()) {
            $initStmt = $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
            $initStmt->execute([$userId]);
        }

        $sql = "UPDATE user_settings SET 
            phone_number = :phone_number,
            pickup_instructions = :address,
            notify_inapp = :notify_inapp,
            notify_whatsapp = :notify_whatsapp,
            notify_email = :notify_email
            WHERE user_id = :user_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':phone_number'    => $phoneNumber,
            ':address'         => $address,
            ':notify_inapp'    => !empty($input['notify_inapp']) ? 1 : 0,
            ':notify_whatsapp' => !empty($input['notify_whatsapp']) ? 1 : 0,
            ':notify_email'    => !empty($input['notify_email']) ? 1 : 0,
            ':user_id'         => $userId
        ]);

        echo json_encode(['success' => true, 'message' => 'Settings updated successfully.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_03: Method not allowed.']);

} catch (Throwable $e) {
    error_log("Error in settings.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ERR_SYS_01: Database operation failed.']);
    exit;
}