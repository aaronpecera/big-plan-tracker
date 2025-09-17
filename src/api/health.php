<?php
/**
 * Health Check API Endpoint
 * Tests MongoDB connection and system status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Load autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables only in development
if (file_exists(__DIR__ . '/../../.env') && getenv('APP_ENV') !== 'production') {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

use App\Config\DatabaseConnection;

try {
    // Test database connection
    $db = DatabaseConnection::getInstance();
    $isConnected = $db->testConnection();
    
    if ($isConnected) {
        // Get database info
        $database = $db->getDatabase();
        $collections = iterator_to_array($database->listCollections());
        
        $response = [
            'success' => true,
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => [
                'connected' => true,
                'type' => 'MongoDB',
                'collections_count' => count($collections),
                'collections' => array_map(function($collection) {
                    return $collection->getName();
                }, $collections)
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
        
    } else {
        throw new Exception('Falló la conexión a la base de datos');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'status' => 'unhealthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'database' => [
            'connected' => false,
            'type' => 'MongoDB',
            'error' => $e->getMessage()
        ],
        'environment' => [
            'php_version' => PHP_VERSION,
            'app_env' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development',
            'mongodb_extension' => extension_loaded('mongodb') ? 'loaded' : 'missing'
        ],
        'debug' => [
            'mongodb_uri_set' => !empty($_ENV['MONGODB_URI'] ?? getenv('MONGODB_URI')),
            'mongodb_database_set' => !empty($_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE')),
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ];
    
    http_response_code(503);
    echo json_encode($response, JSON_PRETTY_PRINT);
}
?>