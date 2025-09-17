<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../middleware/auth.php';
require_once '../config/database.php';

// Verificar autenticación de administrador
requireAdmin();

try {
    $db = DatabaseConnection::getInstance();
    $database = $db->getDatabase();
    $tasksCollection = $database->selectCollection('tasks');
    $usersCollection = $database->selectCollection('users');
    $companiesCollection = $database->selectCollection('companies');

    $currentDate = new DateTime();

    // Buscar tareas críticas:
    // 1. Tareas vencidas
    // 2. Tareas que vencen en las próximas 24 horas
    // 3. Tareas en progreso con alta prioridad
    $cursor = $tasksCollection->find([
        '$or' => [
            // Tareas vencidas
            [
                'due_date' => ['$lt' => $currentDate],
                'status' => ['$nin' => ['completed', 'cancelled']]
            ],
            // Tareas que vencen en 24 horas
            [
                'due_date' => [
                    '$gte' => $currentDate,
                    '$lte' => (clone $currentDate)->add(new DateInterval('P1D'))
                ],
                'status' => ['$nin' => ['completed', 'cancelled']]
            ],
            // Tareas de alta prioridad en progreso
            [
                'priority' => 'high',
                'status' => 'in_progress'
            ]
        ]
    ], [
        'sort' => ['due_date' => 1],
        'limit' => 15
    ]);
    
    $criticalTasks = iterator_to_array($cursor);

    $enrichedTasks = [];

    foreach ($criticalTasks as $task) {
        $enrichedTask = [
            'id' => (string)$task['_id'],
            'title' => $task['title'] ?? 'Sin título',
            'description' => $task['description'] ?? '',
            'status' => $task['status'] ?? 'not_started',
            'priority' => $task['priority'] ?? 'medium',
            'due_date' => isset($task['due_date']) ? $task['due_date']->toDateTime()->format('Y-m-d H:i:s') : null,
            'created_at' => isset($task['created_at']) ? $task['created_at']->toDateTime()->format('Y-m-d H:i:s') : null,
            'assigned_user' => null,
            'company_name' => 'Sin empresa',
            'is_overdue' => false
        ];

        // Verificar si está vencida
        if (isset($task['due_date']) && $task['due_date']->toDateTime() < $currentDate) {
            $enrichedTask['is_overdue'] = true;
            $enrichedTask['status'] = 'overdue';
        }

        // Obtener información del usuario asignado
        if (isset($task['assigned_to'])) {
            $user = $usersCollection->findOne(['_id' => $task['assigned_to']]);
            if ($user) {
                $enrichedTask['assigned_user'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
                $enrichedTask['assigned_user_email'] = $user['email'] ?? '';
            }
        }

        // Obtener información de la empresa
        if (isset($task['company_id'])) {
            $company = $companiesCollection->findOne(['_id' => $task['company_id']]);
            if ($company) {
                $enrichedTask['company_name'] = $company['name'] ?? 'Sin nombre';
            }
        }

        // Calcular tiempo restante
        if (isset($task['due_date'])) {
            $dueDate = $task['due_date']->toDateTime();
            $interval = $currentDate->diff($dueDate);
            
            if ($dueDate < $currentDate) {
                $enrichedTask['time_remaining'] = 'Vencida hace ' . $interval->format('%d días %h horas');
            } else {
                $enrichedTask['time_remaining'] = $interval->format('%d días %h horas restantes');
            }
        }

        $enrichedTasks[] = $enrichedTask;
    }

    // Ordenar por criticidad (vencidas primero, luego por fecha de vencimiento)
    usort($enrichedTasks, function($a, $b) {
        if ($a['is_overdue'] && !$b['is_overdue']) return -1;
        if (!$a['is_overdue'] && $b['is_overdue']) return 1;
        
        if ($a['due_date'] && $b['due_date']) {
            return strcmp($a['due_date'], $b['due_date']);
        }
        
        return 0;
    });

    echo json_encode([
        'success' => true,
        'tasks' => $enrichedTasks,
        'total' => count($enrichedTasks),
        'overdue_count' => count(array_filter($enrichedTasks, function($task) {
            return $task['is_overdue'];
        })),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener tareas críticas',
        'error' => $e->getMessage()
    ]);
}
?>