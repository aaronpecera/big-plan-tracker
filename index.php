<?php
/**
 * Big Plan Tracker - Main Entry Point
 * Handles API routing and serves static files
 */

// Enable error reporting for development
if (getenv('APP_ENV') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Set content type
header('Content-Type: application/json');

// Enable CORS for all origins (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables only in development
if (file_exists(__DIR__ . '/.env') && getenv('APP_ENV') !== 'production') {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Get the request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove base path if running in subdirectory
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/') {
    $path = substr($path, strlen($basePath));
}

// Route API requests
if (strpos($path, '/api/') === 0) {
    $apiPath = substr($path, 4); // Remove '/api' prefix
    
    switch ($apiPath) {
        case '/health':
            require_once __DIR__ . '/src/api/health.php';
            break;
            
        case '/auth':
            require_once __DIR__ . '/src/api/auth.php';
            break;
            
        case '/init_database':
            require_once __DIR__ . '/src/api/init_database.php';
            break;
            
        case '/projects':
            require_once __DIR__ . '/src/api/projects.php';
            break;
            
        case '/tasks':
            require_once __DIR__ . '/src/api/tasks.php';
            break;
            
        case '/users':
            require_once __DIR__ . '/src/api/users.php';
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'API endpoint not found',
                'path' => $apiPath
            ]);
            break;
    }
} else {
    // Serve static files
    $filePath = __DIR__ . '/public' . $path;
    
    // Default to index.html for root path
    if ($path === '/' || $path === '') {
        $filePath = __DIR__ . '/public/views/index.html';
    }
    
    // Add .html extension if no extension provided
    if (!pathinfo($filePath, PATHINFO_EXTENSION) && !is_dir($filePath)) {
        $filePath .= '.html';
    }
    
    // Check if file exists
    if (file_exists($filePath) && is_file($filePath)) {
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        readfile($filePath);
    } else {
        // Try to serve from views directory
        $viewPath = __DIR__ . '/public/views' . $path;
        if (!pathinfo($viewPath, PATHINFO_EXTENSION)) {
            $viewPath .= '.html';
        }
        
        if (file_exists($viewPath) && is_file($viewPath)) {
            header('Content-Type: text/html');
            readfile($viewPath);
        } else {
            // 404 - File not found
            http_response_code(404);
            header('Content-Type: text/html');
            echo '<!DOCTYPE html>
<html>
<head>
    <title>404 - Page Not Found</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
        .error { color: #e74c3c; }
    </style>
</head>
<body>
    <h1 class="error">404 - Page Not Found</h1>
    <p>The requested page could not be found.</p>
    <a href="/">Go back to home</a>
</body>
</html>';
        }
    }
}
?>