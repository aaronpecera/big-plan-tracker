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

// Datos de prueba para proyectos
$sampleProjects = [
    [
        'id' => '1',
        'name' => 'Rediseño de Sitio Web Corporativo',
        'description' => 'Proyecto para rediseñar completamente el sitio web de la empresa con un enfoque moderno y responsive.',
        'status' => 'in_progress',
        'priority' => 'high',
        'start_date' => '2025-01-10',
        'end_date' => '2025-03-15',
        'budget' => 25000.00,
        'spent' => 12500.00,
        'progress' => 45,
        'company_id' => '1',
        'manager_id' => '2',
        'team_members' => ['2', '3', '4'],
        'created_at' => '2025-01-10 09:00:00',
        'updated_at' => '2025-01-17 08:30:00'
    ],
    [
        'id' => '2',
        'name' => 'Sistema de Gestión de Inventario',
        'description' => 'Desarrollo de un sistema completo para gestionar el inventario de productos.',
        'status' => 'planning',
        'priority' => 'medium',
        'start_date' => '2025-02-01',
        'end_date' => '2025-05-30',
        'budget' => 45000.00,
        'spent' => 0.00,
        'progress' => 10,
        'company_id' => '1',
        'manager_id' => '2',
        'team_members' => ['2', '4'],
        'created_at' => '2025-01-15 14:00:00',
        'updated_at' => '2025-01-16 10:15:00'
    ],
    [
        'id' => '3',
        'name' => 'Campaña de Marketing Digital',
        'description' => 'Campaña integral de marketing digital para aumentar la presencia online.',
        'status' => 'completed',
        'priority' => 'medium',
        'start_date' => '2024-11-01',
        'end_date' => '2024-12-31',
        'budget' => 15000.00,
        'spent' => 14200.00,
        'progress' => 100,
        'company_id' => '2',
        'manager_id' => '3',
        'team_members' => ['3'],
        'created_at' => '2024-10-25 11:00:00',
        'updated_at' => '2025-01-02 16:00:00'
    ],
    [
        'id' => '4',
        'name' => 'Migración a la Nube',
        'description' => 'Migración de la infraestructura actual a servicios en la nube.',
        'status' => 'on_hold',
        'priority' => 'low',
        'start_date' => '2025-03-01',
        'end_date' => '2025-08-31',
        'budget' => 60000.00,
        'spent' => 5000.00,
        'progress' => 5,
        'company_id' => '1',
        'manager_id' => '2',
        'team_members' => ['2'],
        'created_at' => '2025-01-12 13:30:00',
        'updated_at' => '2025-01-14 09:45:00'
    ]
];

try {
    switch ($action) {
        case 'list':
            // Listar todos los proyectos
            $status = $_GET['status'] ?? '';
            $filteredProjects = $sampleProjects;
            
            if ($status) {
                $filteredProjects = array_filter($sampleProjects, function($project) use ($status) {
                    return $project['status'] === $status;
                });
                $filteredProjects = array_values($filteredProjects);
            }
            
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
                'start_date' => $input['start_date'] ?? date('Y-m-d'),
                'end_date' => $input['end_date'] ?? date('Y-m-d', strtotime('+3 months')),
                'budget' => floatval($input['budget'] ?? 0),
                'spent' => 0.00,
                'progress' => 0,
                'company_id' => $_SESSION['company_id'] ?? '1',
                'manager_id' => $_SESSION['user_id'],
                'team_members' => $input['team_members'] ?? [$_SESSION['user_id']],
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
                return $p['status'] === 'in_progress';
            }));
            $completedProjects = count(array_filter($sampleProjects, function($p) {
                return $p['status'] === 'completed';
            }));
            $totalBudget = array_sum(array_column($sampleProjects, 'budget'));
            $totalSpent = array_sum(array_column($sampleProjects, 'spent'));
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_projects' => $totalProjects,
                    'active_projects' => $activeProjects,
                    'completed_projects' => $completedProjects,
                    'total_budget' => $totalBudget,
                    'total_spent' => $totalSpent,
                    'budget_utilization' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 2) : 0
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