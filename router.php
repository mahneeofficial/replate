<?php
declare(strict_types=1);

$publicDir = realpath(__DIR__ . '/public');
if ($publicDir === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(["error" => "ERR_SYS_00: Public directory not found."]);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Canonicalize target file path to defeat path traversal attacks (/../)
$targetFile = realpath($publicDir . $uri);

// Verify target file path stays strictly inside /public
$isInsidePublic = ($targetFile !== false && str_starts_with($targetFile, $publicDir));

// 1. Redirect explicit .html requests to clean URLs
if (pathinfo($uri, PATHINFO_EXTENSION) === 'html') {
    $cleanPath = preg_replace('/\.html$/i', '', $uri);
    header("Location: " . $cleanPath, true, 301);
    exit;
}

// 2. Serve PHP scripts directly inside /public
if ($isInsidePublic && !is_dir($targetFile) && pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
    require $targetFile;
    exit;
}

// 3. Serve static assets directly from /public
if ($isInsidePublic && !is_dir($targetFile)) {
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    $mimes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'json'  => 'application/json; charset=UTF-8',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf'
    ];

    header("Content-Type: " . ($mimes[$ext] ?? "application/octet-stream"));
    readfile($targetFile);
    exit;
}

// 4. Handle missing favicon cleanly
if ($uri === '/favicon.ico') {
    http_response_code(204);
    exit;
}

// 5. Clean HTML route mapping (e.g., /register -> public/register.html)
$cleanHtmlPath = realpath($publicDir . $uri . '.html');
if ($cleanHtmlPath !== false && str_starts_with($cleanHtmlPath, $publicDir) && !is_dir($cleanHtmlPath)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($cleanHtmlPath);
    exit;
}

// 6. Root path / -> serve public/login.html
if ($uri === '/' || $uri === '') {
    $loginHtml = realpath($publicDir . '/login.html');
    if ($loginHtml !== false && str_starts_with($loginHtml, $publicDir)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($loginHtml);
        exit;
    }
}

// 7. Delegate remaining requests to public/index.php
$indexFile = realpath($publicDir . '/index.php');
if ($indexFile !== false && str_starts_with($indexFile, $publicDir)) {
    require $indexFile;
    exit;
}

// 8. 404 Fallback
http_response_code(404);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(["success" => false, "error" => "ERR_SYS_04: Endpoint or page not found"]);