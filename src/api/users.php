<?php
// Configuración de CORS y headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Session is already started in index.php

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$action = $_GET['action'] ?? '';

// Datos de prueba para usuarios
$sampleUsers = [
    [
        'id' => '1',
        'username' => 'aaron',
        'email' => 'aaron@example.com',
        'role' => 'admin',
        'first_name' => 'Aaron',
        'last_name' => 'Rodriguez',
        'status' => 'active',
        'company_id' => '1',
        'created_at' => '2025-01-10 09:00:00',
        'last_login' => '2025-01-17 08:29:00'
    ],
    [
        'id' => '2',
        'username' => 'designer',
        'email' => 'designer@example.com',
        'role' => 'user',
        'first_name' => 'Maria',
        'last_name' => 'Garcia',
        'status' => 'active',
        'company_id' => '1',
        'created_at' => '2025-01-12 14:30:00',
        'last_login' => '2025-01-16 16:45:00'
    ],
    [
        'id' => '3',
        'username' => 'tester',
        'email' => 'tester@example.com',
        'role' => 'user',
        'first_name' => 'Carlos',
        'last_name' => 'Martinez',
        'status' => 'active',
        'company_id' => '1',
        'created_at' => '2025-01-13 11:15:00',
        'last_login' => '2025-01-15 10:20:00'
    ],
    [
        'id' => '4',
        'username' => 'manager',
        'email' => 'manager@example.com',
        'role' => 'manager',
        'first_name' => 'Ana',
        'last_name' => 'Lopez',
        'status' => 'active',
        'company_id' => '1',
        'created_at' => '2025-01-11 08:00:00',
        'last_login' => '2025-01-16 18:30:00'
    ],
    [
        'id' => '5',
        'username' => 'inactive_user',
        'email' => 'inactive@example.com',
        'role' => 'user',
        'first_name' => 'Pedro',
        'last_name' => 'Sanchez',
        'status' => 'inactive',
        'company_id' => '1',
        'created_at' => '2025-01-08 12:00:00',
        'last_login' => '2025-01-10 15:00:00'
    ]
];

try {
    switch ($action) {
        case 'list':
            // Listar todos los usuarios
            $status = $_GET['status'] ?? '';
            $role = $_GET['role'] ?? '';
            $filteredUsers = $sampleUsers;
            
            if ($status) {
                $filteredUsers = array_filter($filteredUsers, function($user) use ($status) {
                    return $user['status'] === $status;
                });
            }
            
            if ($role) {
                $filteredUsers = array_filter($filteredUsers, function($user) use ($role) {
                    return $user['role'] === $role;
                });
            }
            
            $filteredUsers = array_values($filteredUsers);
            
            echo json_encode([
                'success' => true,
                'data' => $filteredUsers
            ]);
            break;
            
        case 'create':
            // Crear nuevo usuario
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['username']) || !isset($input['email'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Username y email son requeridos']);
                exit();
            }
            
            $newUser = [
                'id' => (string)(count($sampleUsers) + 1),
                'username' => $input['username'],
                'email' => $input['email'],
                'role' => $input['role'] ?? 'user',
                'first_name' => $input['first_name'] ?? '',
                'last_name' => $input['last_name'] ?? '',
                'status' => $input['status'] ?? 'active',
                'company_id' => $input['company_id'] ?? '1',
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => null
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => $newUser
            ]);
            break;
            
        case 'get':
            // Obtener usuario específico
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de usuario requerido']);
                exit();
            }
            
            $user = null;
            foreach ($sampleUsers as $u) {
                if ($u['id'] === $id) {
                    $user = $u;
                    break;
                }
            }
            
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'data' => $user
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado']);
            }
            break;
            
        case 'update':
            // Actualizar usuario
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de usuario requerido']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos de usuario requeridos']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente'
            ]);
            break;
            
        case 'delete':
            // Eliminar usuario
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de usuario requerido']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ]);
            break;
            
        case 'stats':
            // Estadísticas de usuarios
            $totalUsers = count($sampleUsers);
            $activeUsers = count(array_filter($sampleUsers, function($u) {
                return $u['status'] === 'active';
            }));
            $inactiveUsers = count(array_filter($sampleUsers, function($u) {
                return $u['status'] === 'inactive';
            }));
            $adminUsers = count(array_filter($sampleUsers, function($u) {
                return $u['role'] === 'admin';
            }));
            $managerUsers = count(array_filter($sampleUsers, function($u) {
                return $u['role'] === 'manager';
            }));
            $regularUsers = count(array_filter($sampleUsers, function($u) {
                return $u['role'] === 'user';
            }));
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'inactive_users' => $inactiveUsers,
                    'admin_users' => $adminUsers,
                    'manager_users' => $managerUsers,
                    'regular_users' => $regularUsers,
                    'activity_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Error in users.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>