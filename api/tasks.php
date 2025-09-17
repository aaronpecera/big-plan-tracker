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

// Datos de prueba para tareas
$sampleTasks = [
    [
        'id' => '1',
        'title' => 'Implementar dashboard de administración',
        'description' => 'Crear un dashboard completo para administrar usuarios y tareas',
        'status' => 'in_progress',
        'priority' => 'high',
        'assigned_to' => 'aaron',
        'project_id' => '1',
        'created_at' => '2025-01-15 10:00:00',
        'due_date' => '2025-01-20 18:00:00'
    ],
    [
        'id' => '2',
        'title' => 'Configurar base de datos MongoDB',
        'description' => 'Establecer conexión y configurar colecciones',
        'status' => 'completed',
        'priority' => 'high',
        'assigned_to' => 'aaron',
        'project_id' => '1',
        'created_at' => '2025-01-14 09:00:00',
        'due_date' => '2025-01-16 17:00:00'
    ],
    [
        'id' => '3',
        'title' => 'Diseñar interfaz de usuario',
        'description' => 'Crear mockups y prototipos de la interfaz',
        'status' => 'pending',
        'priority' => 'medium',
        'assigned_to' => 'designer',
        'project_id' => '2',
        'created_at' => '2025-01-16 14:00:00',
        'due_date' => '2025-01-25 12:00:00'
    ],
    [
        'id' => '4',
        'title' => 'Implementar autenticación',
        'description' => 'Sistema de login y gestión de sesiones',
        'status' => 'completed',
        'priority' => 'high',
        'assigned_to' => 'aaron',
        'project_id' => '1',
        'created_at' => '2025-01-12 11:00:00',
        'due_date' => '2025-01-14 16:00:00'
    ],
    [
        'id' => '5',
        'title' => 'Pruebas de integración',
        'description' => 'Realizar pruebas completas del sistema',
        'status' => 'pending',
        'priority' => 'medium',
        'assigned_to' => 'tester',
        'project_id' => '1',
        'created_at' => '2025-01-17 08:00:00',
        'due_date' => '2025-01-30 17:00:00'
    ]
];

try {
    switch ($action) {
        case 'list':
            // Listar todas las tareas
            echo json_encode([
                'success' => true,
                'data' => $sampleTasks
            ]);
            break;
            
        case 'create':
            // Crear nueva tarea
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Título de tarea requerido']);
                exit();
            }
            
            $newTask = [
                'id' => (string)(count($sampleTasks) + 1),
                'title' => $input['title'],
                'description' => $input['description'] ?? '',
                'status' => $input['status'] ?? 'pending',
                'priority' => $input['priority'] ?? 'medium',
                'assigned_to' => $input['assigned_to'] ?? $_SESSION['username'],
                'project_id' => $input['project_id'] ?? '1',
                'created_at' => date('Y-m-d H:i:s'),
                'due_date' => $input['due_date'] ?? null
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Tarea creada exitosamente',
                'data' => $newTask
            ]);
            break;
            
        case 'get':
            // Obtener tarea específica
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de tarea requerido']);
                exit();
            }
            
            $task = null;
            foreach ($sampleTasks as $t) {
                if ($t['id'] === $id) {
                    $task = $t;
                    break;
                }
            }
            
            if ($task) {
                echo json_encode([
                    'success' => true,
                    'data' => $task
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Tarea no encontrada']);
            }
            break;
            
        case 'update':
            // Actualizar tarea
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de tarea requerido']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos de tarea requeridos']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Tarea actualizada exitosamente'
            ]);
            break;
            
        case 'delete':
            // Eliminar tarea
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de tarea requerido']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Tarea eliminada exitosamente'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Error in tasks.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>