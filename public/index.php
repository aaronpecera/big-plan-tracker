<?php
/**
 * Big Plan Tracker - Modern Entry Point
 * Optimized for Render + MongoDB deployment
 */

// Error reporting for development
if (getenv('APP_ENV') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables only in development
if (file_exists(__DIR__ . '/../.env') && getenv('APP_ENV') !== 'production') {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Start session
session_start();

// Set CORS headers for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple routing
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove leading slash
$path = ltrim($path, '/');

// Route handling
switch (true) {
    case $path === '':
    case $path === 'index.html':
        include __DIR__ . '/views/index.html';
        break;
        
    case $path === 'dashboard':
    case $path === 'dashboard.html':
        include __DIR__ . '/views/dashboard.html';
        break;
        
    case $path === 'admin':
    case $path === 'admin.html':
        include __DIR__ . '/views/admin.html';
        break;
        
    case $path === 'login':
    case $path === 'login.html':
        include __DIR__ . '/views/login.html';
        break;
        
    case strpos($path, 'api/') === 0:
        // API routes
        $api_path = substr($path, 4); // Remove 'api/' prefix
        $api_file = __DIR__ . '/../src/api/' . $api_path . '.php';
        
        if (file_exists($api_file)) {
            include $api_file;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
        }
        break;
        
    case strpos($path, 'assets/') === 0:
        // Static assets
        $asset_path = __DIR__ . '/' . $path;
        if (file_exists($asset_path)) {
            $mime_type = mime_content_type($asset_path);
            header('Content-Type: ' . $mime_type);
            readfile($asset_path);
        } else {
            http_response_code(404);
            echo 'Asset not found';
        }
        break;
        
    default:
        http_response_code(404);
        include __DIR__ . '/views/404.html';
        break;
}
?>