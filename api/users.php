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

session_start();

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
        'username' => 'admin',
        'email' => 'admin@bigplan.com',
        'first_name' => 'Administrador',
        'last_name' => 'Sistema',
        'role' => 'admin',
        'active' => true,
        'company_id' => '1',
        'created_at' => '2025-01-10 09:00:00',
        'last_login' => '2025-01-17 08:00:00'
    ],
    [
        'id' => '2',
        'username' => 'aaron',
        'email' => 'aaron.gorman@grupopecera.com',
        'first_name' => 'Aaron',
        'last_name' => 'Gorman',
        'role' => 'admin',
        'active' => true,
        'company_id' => '1',
        'created_at' => '2025-01-12 10:30:00',
        'last_login' => '2025-01-17 08:28:00'
    ],
    [
        'id' => '3',
        'username' => 'designer',
        'email' => 'designer@bigplan.com',
        'first_name' => 'María',
        'last_name' => 'Diseñadora',
        'role' => 'user',
        'active' => true,
        'company_id' => '2',
        'created_at' => '2025-01-13 14:00:00',
        'last_login' => '2025-01-16 16:45:00'
    ],
    [
        'id' => '4',
        'username' => 'tester',
        'email' => 'tester@bigplan.com',
        'first_name' => 'Carlos',
        'last_name' => 'Tester',
        'role' => 'user',
        'active' => true,
        'company_id' => '1',
        'created_at' => '2025-01-14 11:00:00',
        'last_login' => '2025-01-15 17:30:00'
    ],
    [
        'id' => '5',
        'username' => 'inactive_user',
        'email' => 'inactive@bigplan.com',
        'first_name' => 'Usuario',
        'last_name' => 'Inactivo',
        'role' => 'user',
        'active' => false,
        'company_id' => '1',
        'created_at' => '2025-01-11 12:00:00',
        'last_login' => '2025-01-12 10:00:00'
    ]
];

try {
    switch ($action) {
        case 'list':
            // Listar todos los usuarios
            echo json_encode([
                'success' => true,
                'data' => $sampleUsers
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
                'first_name' => $input['first_name'] ?? '',
                'last_name' => $input['last_name'] ?? '',
                'role' => $input['role'] ?? 'user',
                'active' => true,
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
            // Eliminar usuario (soft delete)
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