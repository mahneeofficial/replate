<?php
declare(strict_types=1);

$rawPublicDir = realpath(__DIR__ . '/public');
if ($rawPublicDir === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(["error" => "ERR_SYS_00: Public directory not found."]);
    exit;
}

// Normalize directory separators for cross-platform support (Windows vs Linux)
$publicDir = str_replace('\\', '/', $rawPublicDir);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Canonicalize target file path to defeat path traversal attacks (/../)
$rawTargetFile = realpath($publicDir . $uri);
$targetFile = $rawTargetFile !== false ? str_replace('\\', '/', $rawTargetFile) : false;

// Verify target file path stays strictly inside /public (case-insensitive for Windows drive letters)
$isInsidePublic = ($targetFile !== false && str_starts_with(strtolower($targetFile), strtolower($publicDir)));

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
$rawCleanHtmlPath = realpath($rawPublicDir . $uri . '.html');
if ($rawCleanHtmlPath !== false) {
    $cleanHtmlPath = str_replace('\\', '/', $rawCleanHtmlPath);
    if (str_starts_with(strtolower($cleanHtmlPath), strtolower($publicDir)) && !is_dir($cleanHtmlPath)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($cleanHtmlPath);
        exit;
    }
}

// 6. Root path / -> serve public/login.html
if ($uri === '/' || $uri === '') {
    $rawLoginHtml = realpath($rawPublicDir . '/login.html');
    if ($rawLoginHtml !== false) {
        $loginHtml = str_replace('\\', '/', $rawLoginHtml);
        if (str_starts_with(strtolower($loginHtml), strtolower($publicDir))) {
            header('Content-Type: text/html; charset=UTF-8');
            readfile($loginHtml);
            exit;
        }
    }
}

// 7. Delegate remaining requests to public/index.php
$rawIndexFile = realpath($rawPublicDir . '/index.php');
if ($rawIndexFile !== false) {
    $indexFile = str_replace('\\', '/', $rawIndexFile);
    if (str_starts_with(strtolower($indexFile), strtolower($publicDir))) {
        require $indexFile;
        exit;
    }
}

// 8. 404 Fallback
http_response_code(404);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(["success" => false, "error" => "ERR_SYS_04: Endpoint or page not found"]);