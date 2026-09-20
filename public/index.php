<?php
declare(strict_types=1);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($requestUri, PHP_URL_PATH) ?? '/';

// Normalize URI (strip trailing slash if not root)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

switch ($uri) {
    // --- Frontend Page Routes ---
    case '':
    case '/':
    case '/login':
        require __DIR__ . '/login.html';
        break;

    case '/register':
        require __DIR__ . '/register.html';
        break;

    case '/dashboard':
        require __DIR__ . '/dashboard.html';
        break;

    case '/admin':
    case '/admin-dashboard':
        require __DIR__ . '/admin-dashboard.html';
        break;

    case '/donations':
        require __DIR__ . '/donations.html';
        break;

    case '/activity':
        require __DIR__ . '/activity.html';
        break;

    case '/settings':
        require __DIR__ . '/settings.html';
        break;

    // --- Backend API Routes ---
    case '/api/admin':
    case '/api/admin.php':
        require __DIR__ . '/api/admin.php';
        break;

    case '/api/donations':
    case '/api/donations.php':
        require __DIR__ . '/api/donations.php';
        break;

    case '/api/users':
    case '/api/users.php':
        require __DIR__ . '/api/users.php';
        break;

    case '/api/requests':
    case '/api/requests.php':
        require __DIR__ . '/api/requests.php';
        break;

    case '/api/notifications':
    case '/notifications':
        require __DIR__ . '/api/notifications.php';
        break;

    case '/api/categories':
    case '/api/categories.php':
        require __DIR__ . '/api/categories.php';
        break;

    case '/api/forgot-password':
    case '/api/forgot_password.php':
        require __DIR__ . '/api/forgot_password.php';
        break;

    case '/api/settings':
    case '/api/settings.php':
        require __DIR__ . '/api/settings.php';
        break;

    default:
        http_response_code(404);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(["success" => false, "error" => "ERR_SYS_04: Endpoint or page not found"]);
        break;
}