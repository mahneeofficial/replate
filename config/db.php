<?php
declare(strict_types=1);

$host    = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$db      = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'replate';
$user    = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$pass    = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
$port    = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES         => false,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
    }

    echo json_encode([
        "error" => "ERR_SYS_00: Database connection failed."
    ]);
    exit;
}