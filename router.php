<?php
/**
 * Router for PHP built-in server
 * Usage: php -S localhost:8080 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route all requests to index.php
$_SERVER['REQUEST_URI'] = $uri . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
require __DIR__ . '/index.php';
