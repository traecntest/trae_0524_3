<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

$publicPath = __DIR__ . '/public';
$filePath = $publicPath . $uri;

if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false;
}

if ($uri === '/' || $uri === '/index.html') {
    require_once __DIR__ . '/views/index.html';
    return true;
}

if (preg_match('#^/assets/(css|js)/.+#', $uri)) {
    $assetPath = __DIR__ . $uri;
    if (file_exists($assetPath)) {
        $ext = pathinfo($assetPath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'html' => 'text/html',
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($assetPath);
        return true;
    }
}

require_once __DIR__ . '/public/index.php';
