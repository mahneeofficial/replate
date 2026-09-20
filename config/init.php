<?php
declare(strict_types=1);

// 1. Global Exception Handler
set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "error" => "ERR_SYS_01: Internal Server Error"]);
    exit;
});

// 2. Start Session Safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Set Default JSON Headers
header("Content-Type: application/json; charset=UTF-8");

// 4. Include Security and Apply Security Headers
$securityPath = __DIR__ . '/Security.php';
if (file_exists($securityPath)) {
    require_once $securityPath;
    if (class_exists('Security') && method_exists('Security', 'applySecurityHeaders')) {
        Security::applySecurityHeaders();
    }
}

// 5. Include Database Connection
$dbPath = __DIR__ . '/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_00: Database configuration missing."]);
    exit;
}

require_once $dbPath;

// 6. Verify Active PDO Connection
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "ERR_SYS_00: Invalid database connection."]);
    exit;
}