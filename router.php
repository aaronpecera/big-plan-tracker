<?php
// Router script for PHP development server
// This ensures all requests go through index.php for proper routing

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the request is for a static file that exists, serve it directly
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false; // Let the server handle static files
}

// Otherwise, route through index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require_once __DIR__ . '/public/index.php';