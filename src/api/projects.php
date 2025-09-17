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

// Datos de prueba para proyectos
$sampleProjects = [
    [
        'id' => '1',
        'name' => 'Sistema de Gestión de Tareas',
        'description' => 'Desarrollo de un sistema completo para gestión de tareas y proyectos empresariales',
        'status' => 'active',
        'priority' => 'high',
        'company_id' => '1',
        'manager_id' => '4',
        'start_date' => '2025-01-10',
        'end_date' => '2025-03-15',
        'budget' => 50000.00,
        'progress' => 65,
        'created_at' => '2025-01-10 09:00:00',
        'updated_at' => '2025-01-17 10:30:00'
    ],
    [
        'id' => '2',
        'name' => 'Portal de Clientes',
        'description' => 'Desarrollo de un portal web para que los clientes puedan acceder a sus servicios',
        'status' => 'planning',
        'priority' => 'medium',
        'company_id' => '1',
        'manager_id' => '4',
        'start_date' => '2025-02-01',
        'end_date' => '2025-05-30',
        'budget' => 75000.00,
        'progress' => 15,
        'created_at' => '2025-01-15 14:00:00',
        'updated_at' => '2025-01-16 16:45:00'
    ],
    [
        'id' => '3',
        'name' => 'Aplicación Móvil',
        'description' => 'Desarrollo de aplicación móvil para iOS y Android',
        'status' => 'completed',
        'priority' => 'high',
        'company_id' => '1',
        'manager_id' => '1',
        'start_date' => '2024-10-01',
        'end_date' => '2024-12-31',
        'budget' => 120000.00,
        'progress' => 100,
        'created_at' => '2024-09-25 11:00:00',
        'updated_at' => '2025-01-02 09:15:00'
    ],
    [
        'id' => '4',
        'name' => 'Migración de Base de Datos',
        'description' => 'Migración de sistemas legacy a nueva arquitectura de base de datos',
        'status' => 'on_hold',
        'priority' => 'low',
        'company_id' => '1',
        'manager_id' => '1',
        'start_date' => '2025-03-01',
        'end_date' => '2025-06-30',
        'budget' => 30000.00,
        'progress' => 5,
        'created_at' => '2025-01-12 10:30:00',
        'updated_at' => '2025-01-14 14:20:00'
    ]
];

try {
    switch ($action) {
        case 'list':
            // Listar todos los proyectos
            $status = $_GET['status'] ?? '';
            $priority = $_GET['priority'] ?? '';
            $filteredProjects = $sampleProjects;
            
            if ($status) {
                $filteredProjects = array_filter($filteredProjects, function($project) use ($status) {
                    return $project['status'] === $status;
                });
            }
            
            if ($priority) {
                $filteredProjects = array_filter($filteredProjects, function($project) use ($priority) {
                    return $project['priority'] === $priority;
                });
            }
            
            $filteredProjects = array_values($filteredProjects);
            
            echo json_encode([
                'success' => true,
                'data' => $filteredProjects
            ]);
            break;
            
        case 'create':
            // Crear nuevo proyecto
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Nombre del proyecto es requerido']);
                exit();
            }
            
            $newProject = [
                'id' => (string)(count($sampleProjects) + 1),
                'name' => $input['name'],
                'description' => $input['description'] ?? '',
                'status' => $input['status'] ?? 'planning',
                'priority' => $input['priority'] ?? 'medium',
                'company_id' => $input['company_id'] ?? '1',
                'manager_id' => $input['manager_id'] ?? $_SESSION['user_id'],
                'start_date' => $input['start_date'] ?? date('Y-m-d'),
                'end_date' => $input['end_date'] ?? date('Y-m-d', strtotime('+3 months')),
                'budget' => (float)($input['budget'] ?? 0),
                'progress' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Proyecto creado exitosamente',
                'data' => $newProject
            ]);
            break;
            
        case 'get':
            // Obtener proyecto específico
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de proyecto requerido']);
                exit();
            }
            
            $project = null;
            foreach ($sampleProjects as $p) {
                if ($p['id'] === $id) {
                    $project = $p;
                    break;
                }
            }
            
            if ($project) {
                echo json_encode([
                    'success' => true,
                    'data' => $project
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Proyecto no encontrado']);
            }
            break;
            
        case 'update':
            // Actualizar proyecto
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de proyecto requerido']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos de proyecto requeridos']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Proyecto actualizado exitosamente'
            ]);
            break;
            
        case 'delete':
            // Eliminar proyecto
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de proyecto requerido']);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Proyecto eliminado exitosamente'
            ]);
            break;
            
        case 'stats':
            // Estadísticas de proyectos
            $totalProjects = count($sampleProjects);
            $activeProjects = count(array_filter($sampleProjects, function($p) {
                return $p['status'] === 'active';
            }));
            $completedProjects = count(array_filter($sampleProjects, function($p) {
                return $p['status'] === 'completed';
            }));
            $planningProjects = count(array_filter($sampleProjects, function($p) {
                return $p['status'] === 'planning';
            }));
            $onHoldProjects = count(array_filter($sampleProjects, function($p) {
                return $p['status'] === 'on_hold';
            }));
            
            $totalBudget = array_sum(array_column($sampleProjects, 'budget'));
            $averageProgress = $totalProjects > 0 ? 
                round(array_sum(array_column($sampleProjects, 'progress')) / $totalProjects, 2) : 0;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_projects' => $totalProjects,
                    'active_projects' => $activeProjects,
                    'completed_projects' => $completedProjects,
                    'planning_projects' => $planningProjects,
                    'on_hold_projects' => $onHoldProjects,
                    'total_budget' => $totalBudget,
                    'average_progress' => $averageProgress,
                    'completion_rate' => $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100, 2) : 0
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Error in projects.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>