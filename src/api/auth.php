<?php
/**
 * Authentication API - Simplified Version
 * Handles user login with hardcoded credentials
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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hardcoded users for demo
$users = [
    'admin' => [
        'id' => '1',
        'username' => 'admin',
        'password' => 'admin123',
        'email' => 'admin@bigplan.com',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'role' => 'admin',
        'company_id' => '1',
        'permissions' => ['all']
    ],
    'aaron' => [
        'id' => '2',
        'username' => 'aaron',
        'password' => 'Redrover99!@',
        'email' => 'aaron.gorman@grupopecera.com',
        'first_name' => 'Aaron',
        'last_name' => 'Gorman',
        'role' => 'admin',
        'company_id' => '1',
        'permissions' => ['all']
    ],
    'manager' => [
        'id' => '3',
        'username' => 'manager',
        'password' => 'manager123',
        'email' => 'manager@bigplan.com',
        'first_name' => 'Manager',
        'last_name' => 'User',
        'role' => 'manager',
        'company_id' => '1',
        'permissions' => ['read', 'write']
    ],
    'user' => [
        'id' => '4',
        'username' => 'user',
        'password' => 'user123',
        'email' => 'user@bigplan.com',
        'first_name' => 'Regular',
        'last_name' => 'User',
        'role' => 'user',
        'company_id' => '1',
        'permissions' => ['read']
    ]
];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'login';
    
    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                handleLogin($users);
            } elseif ($action === 'logout') {
                handleLogout();
            } else {
                throw new Exception('Acción inválida');
            }
            break;
            
        case 'GET':
            if ($action === 'check') {
                checkSession();
            } else {
                throw new Exception('Acción inválida');
            }
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleLogin($users) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Credenciales requeridas');
    }
    
    $username = trim($input['username']);
    $password = trim($input['password']);
    
    // Verificar credenciales
    $user = null;
    foreach ($users as $userData) {
        if ($userData['username'] === $username && $userData['password'] === $password) {
            $user = $userData;
            break;
        }
    }
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Credenciales inválidas'
        ]);
        return;
    }
    
    // Crear sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_role'] = $user['role']; // Cambiar a user_role para consistencia
    $_SESSION['company_id'] = $user['company_id'];
    $_SESSION['permissions'] = $user['permissions'];
    $_SESSION['logged_in'] = true;
    
    // Generar token simple
    $token = bin2hex(random_bytes(32));
    $_SESSION['auth_token'] = $token;
    
    // Determinar dashboard de redirección
    $dashboardUrl = $user['role'] === 'admin' ? '/views/admin-dashboard.html' : '/views/user-dashboard.html';
    
    echo json_encode([
        'success' => true,
        'message' => 'Login exitoso',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
            'company_id' => $user['company_id'],
            'permissions' => $user['permissions']
        ],
        'token' => $token,
        'dashboard_url' => $dashboardUrl
    ]);
}

function handleLogout() {
    // Clear session
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    $response = [
        'success' => true,
        'message' => 'Sesión cerrada exitosamente',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function checkSession() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        $response = [
            'success' => true,
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } else {
        $response = [
            'success' => false,
            'logged_in' => false,
            'message' => 'No hay sesión activa',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    http_response_code(200);
    echo json_encode($response);
}
?>