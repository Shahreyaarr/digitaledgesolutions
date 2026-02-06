<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

// API requests
if (strpos($uri, '/api/') === 0) {
    $_GET['path'] = substr($uri, 5);
    require __DIR__ . '/backend/api/index.php';
    exit;
}

// Static files serve karo
$publicDir = __DIR__ . '/frontend/public';
$staticFile = $publicDir . $uri;

if (file_exists($staticFile) && is_file($staticFile)) {
    $ext = pathinfo($staticFile, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon'
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($staticFile);
    exit;
}

// Check for .html files
if (file_exists($publicDir . $uri . '.html')) {
    readfile($publicDir . $uri . '.html');
    exit;
}

// Default: index.html (SPA routing)
readfile($publicDir . '/index.html');
