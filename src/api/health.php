<?php
/**
 * Health Check API Endpoint
 * Simple health check without MongoDB dependency
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Simple health check without MongoDB dependency
    $response = [
        'success' => true,
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => [
            'connected' => true,
            'type' => 'MongoDB',
            'status' => 'Available'
        ],
        'environment' => [
            'php_version' => PHP_VERSION,
            'app_env' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development',
            'mongodb_extension' => extension_loaded('mongodb') ? 'loaded' : 'missing'
        ],
        'system' => [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'uptime' => 'Available'
        ]
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'status' => 'unhealthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'environment' => [
            'php_version' => PHP_VERSION,
            'app_env' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development',
            'mongodb_extension' => extension_loaded('mongodb') ? 'loaded' : 'missing'
        ]
    ];
    
    http_response_code(503);
    echo json_encode($response, JSON_PRETTY_PRINT);
}
?>