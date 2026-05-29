<?php
/**
 * Simple router for PHP built-in web server
 * Run with: php -S localhost:8000 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Route /api/* requests to api/index.php
if (strpos($uri, '/api') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require __DIR__ . '/api/index.php';
    return true;
}

// Route root / to index.html
if ($uri === '/' || $uri === '') {
    $uri = '/index.html';
}

$requested = __DIR__ . '/public' . $uri;

// If static file exists in public/, let PHP built-in server serve it
if (file_exists($requested) && !is_dir($requested)) {
    // Determine mime type to serve files correctly
    $ext = pathinfo($requested, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml'
    ];
    if (isset($mimeTypes[$ext])) {
        header("Content-Type: " . $mimeTypes[$ext]);
    }
    readfile($requested);
    return true;
}

// 404 Not Found
http_response_code(404);
echo "404 Not Found";
