<?php
// Main router file for PHP built-in server

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

// API requests
if (strpos($uri, '/api/') === 0) {
    // Forward to API
    $_GET['path'] = substr($uri, 5); // Remove '/api/'
    require __DIR__ . '/backend/api/index.php';
    exit;
}

// Static files
if (file_exists(__DIR__ . '/frontend/public' . $uri) && is_file(__DIR__ . '/frontend/public' . $uri)) {
    return false; // Serve static file
}

// Default: serve index.html
readfile(__DIR__ . '/frontend/public/index.html');
