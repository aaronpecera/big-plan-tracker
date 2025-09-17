<?php
/**
 * Authentication API
 * Handles user login, logout, and session management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Session is already started in index.php

// Load autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables only in development
if (file_exists(__DIR__ . '/../../.env') && getenv('APP_ENV') !== 'production') {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

use App\Config\DatabaseConnection;

try {
    $db = DatabaseConnection::getInstance();
    $database = $db->getDatabase();
    $usersCollection = $database->selectCollection('users');
    $activitiesCollection = $database->selectCollection('activities');
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'login';
    
    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                handleLogin($usersCollection, $activitiesCollection);
            } elseif ($action === 'logout') {
                handleLogout($activitiesCollection);
            } else {
                throw new Exception('Acción inválida');
            }
            break;
            
        case 'GET':
            if ($action === 'status') {
                handleStatus($usersCollection);
            } else {
                throw new Exception('Acción inválida');
            }
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($response);
}

function handleLogin($usersCollection, $activitiesCollection) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Usuario y contraseña son requeridos');
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    // Find user
    $user = $usersCollection->findOne([
        '$or' => [
            ['username' => $username],
            ['email' => $username]
        ],
        'status' => 'active'
    ]);
    
    if (!$user || !password_verify($password, $user['password'])) {
        // Log failed login attempt
        logActivity($activitiesCollection, null, 'login_failed', 'Failed login attempt', 'user', null, [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        throw new Exception('Credenciales inválidas');
    }
    
    // Update last login
    $usersCollection->updateOne(
        ['_id' => $user['_id']],
        ['$set' => ['last_login' => new MongoDB\BSON\UTCDateTime()]]
    );
    
    // Create session
    $_SESSION['user_id'] = (string)$user['_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['company_id'] = (string)$user['company_id'];
    $_SESSION['permissions'] = $user['permissions'] ?? [];
    
    // Log successful login
    logActivity($activitiesCollection, $user['_id'], 'login_success', 'Usuario inició sesión exitosamente', 'user', $user['_id'], [
        'username' => $user['username'],
        'role' => $user['role']
    ]);
    
    $response = [
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'user' => [
            'id' => (string)$user['_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
            'company_id' => (string)$user['company_id'],
            'permissions' => $user['permissions'] ?? []
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function handleLogout($activitiesCollection) {
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    
    if ($userId) {
        // Log logout
        logActivity($activitiesCollection, $userId, 'logout', 'Usuario cerró sesión', 'user', $userId, [
            'username' => $username
        ]);
    }
    
    // Destroy session
    session_destroy();
    
    $response = [
        'success' => true,
        'message' => 'Cierre de sesión exitoso',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function handleStatus($usersCollection) {
    if (!isset($_SESSION['user_id'])) {
        $response = [
            'success' => false,
            'authenticated' => false,
            'message' => 'No autenticado'
        ];
        
        http_response_code(401);
        echo json_encode($response);
        return;
    }
    
    // Get current user data
    $user = $usersCollection->findOne([
        '_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id']),
        'status' => 'active'
    ]);
    
    if (!$user) {
        session_destroy();
        
        $response = [
            'success' => false,
            'authenticated' => false,
            'message' => 'Usuario no encontrado o inactivo'
        ];
        
        http_response_code(401);
        echo json_encode($response);
        return;
    }
    
    $response = [
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => (string)$user['_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
            'company_id' => (string)$user['company_id'],
            'permissions' => $user['permissions'] ?? []
        ],
        'session' => [
            'started' => $_SESSION['user_id'] ? true : false,
            'role' => $_SESSION['role'] ?? null
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function logActivity($activitiesCollection, $userId, $action, $description, $entityType, $entityId, $metadata = []) {
    try {
        $activityData = [
            'user_id' => $userId ? new MongoDB\BSON\ObjectId($userId) : null,
            'action' => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id' => $entityId ? new MongoDB\BSON\ObjectId($entityId) : null,
            'metadata' => array_merge($metadata, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]),
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $activitiesCollection->insertOne($activityData);
    } catch (Exception $e) {
        // Log activity errors silently to avoid breaking the main flow
        error_log("Activity logging error: " . $e->getMessage());
    }
}
?>