<?php
declare(strict_types=1);
if (class_exists('Security') && method_exists('Security', 'enforceRateLimit')) {
    Security::enforceRateLimit('register', 5, 600);
}

$rawName = trim((string)($input['name'] ?? $input['full_name'] ?? ''));
$rawOrgName = trim((string)($input['org_name'] ?? $input['organization'] ?? ''));

$name = (class_exists('Security') && method_exists('Security', 'sanitize')) ? Security::sanitize($rawName) : $rawName;
$orgName = (class_exists('Security') && method_exists('Security', 'sanitize')) ? Security::sanitize($rawOrgName) : $rawOrgName;

$rawEmail = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$password = (string)($input['password'] ?? '');
$role = strtolower(trim((string)($input['role'] ?? 'recipient')));

if (!in_array($role, ['recipient', 'donor', 'admin'], true)) {
    $role = 'recipient';
}

if (empty($name) || !$rawEmail || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_01: Name, valid email, and password are required."]);
    return;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ERR_VAL_03: Password must be at least 8 characters long."]);
    return;
}

if (class_exists('Security') && method_exists('Security', 'isDisposableEmail') && Security::isDisposableEmail($rawEmail)) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "ERR_SEC_01: Disposable or temporary email domains are prohibited."]);
    return;
}

$normalizedEmail = (class_exists('Security') && method_exists('Security', 'normalizeEmail'))
    ? Security::normalizeEmail($rawEmail) 
    : strtolower($rawEmail);

try {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE normalized_email = ? OR email = ? LIMIT 1");
    $stmt->execute([$normalizedEmail, $rawEmail]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["success" => false, "error" => "ERR_SEC_02: An account with this email address already exists."]);
        return;
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO users (name, org_name, email, normalized_email, password_hash, role, status, verification_status) 
        VALUES (?, ?, ?, ?, ?, ?, 'Active', 'Pending')
    ");
    $stmt->execute([$name, $orgName ?: null, $rawEmail, $normalizedEmail, $hashedPassword, $role]);
    $userId = (int)$pdo->lastInsertId();

    $stmtSettings = $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
    $stmtSettings->execute([$userId]);

    createSystemNotification(
        $pdo,
        $userId,
        "Welcome to RePlate!",
        "Your account has been created. Explore available donations or submit food requests near you.",
        "welcome"
    );

    recordAuditLog($pdo, $userId, 'USER_REGISTERED', "User account registered with role '{$role}'", 'info');

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "User registered successfully.",
        "user_id" => $userId
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in handleRegister: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "ERR_SYS_01: Failed to register user due to a database error."
    ]);
}