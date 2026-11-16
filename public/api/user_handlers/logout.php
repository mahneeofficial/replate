<?php
declare(strict_types=1);
$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if ($userId) {
    recordAuditLog($pdo, (int)$userId, 'USER_LOGOUT', "User logged out", 'info');
}

if (isset($_COOKIE['replate_remember'])) {
    $parts = explode(':', $_COOKIE['replate_remember']);
    if (count($parts) === 2) {
        try {
            $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?");
            $stmt->execute([$parts[0]]);
        } catch (Throwable $e) {
            error_log("Failed to clear auth token: " . $e->getMessage());
        }
    }
    setcookie('replate_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

echo json_encode(["success" => true, "message" => "Logged out successfully."]);