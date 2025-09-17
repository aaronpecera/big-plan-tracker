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
    $usersCollection = $db->getCollection('users');
    $tasksCollection = $db->getCollection('tasks');
    $companiesCollection = $db->getCollection('companies');
    $activitiesCollection = $db->getCollection('activities');

    // Contar usuarios activos
    $totalUsers = $usersCollection->countDocuments(['status' => 'active']);

    // Contar tareas totales
    $totalTasks = $tasksCollection->countDocuments();

    // Contar empresas
    $totalCompanies = $companiesCollection->countDocuments();

    // Calcular ingresos del mes actual
    $currentMonth = new DateTime();
    $startOfMonth = new DateTime($currentMonth->format('Y-m-01'));
    $endOfMonth = new DateTime($currentMonth->format('Y-m-t 23:59:59'));

    $monthlyRevenue = 0;
    $completedTasks = $tasksCollection->find([
        'status' => 'completed',
        'completed_at' => [
            '$gte' => $startOfMonth,
            '$lte' => $endOfMonth
        ]
    ]);

    foreach ($completedTasks as $task) {
        if (isset($task['cost_calculated']) && $task['cost_calculated']) {
            $monthlyRevenue += floatval($task['cost_calculated']);
        }
    }

    // Estadísticas adicionales
    $tasksInProgress = $tasksCollection->countDocuments(['status' => 'in_progress']);
    $overdueTasks = $tasksCollection->countDocuments([
        'due_date' => ['$lt' => new DateTime()],
        'status' => ['$nin' => ['completed', 'cancelled']]
    ]);

    // Tareas completadas hoy
    $today = new DateTime();
    $startOfDay = new DateTime($today->format('Y-m-d 00:00:00'));
    $endOfDay = new DateTime($today->format('Y-m-d 23:59:59'));

    $tasksCompletedToday = $tasksCollection->countDocuments([
        'status' => 'completed',
        'completed_at' => [
            '$gte' => $startOfDay,
            '$lte' => $endOfDay
        ]
    ]);

    // Productividad semanal (tareas completadas esta semana)
    $startOfWeek = new DateTime('monday this week');
    $endOfWeek = new DateTime('sunday this week 23:59:59');

    $weeklyProductivity = $tasksCollection->countDocuments([
        'status' => 'completed',
        'completed_at' => [
            '$gte' => $startOfWeek,
            '$lte' => $endOfWeek
        ]
    ]);

    // Usuarios más activos (top 5)
    $pipeline = [
        [
            '$match' => [
                'timestamp' => [
                    '$gte' => $startOfMonth,
                    '$lte' => $endOfMonth
                ]
            ]
        ],
        [
            '$group' => [
                '_id' => '$user_id',
                'activity_count' => ['$sum' => 1]
            ]
        ],
        [
            '$sort' => ['activity_count' => -1]
        ],
        [
            '$limit' => 5
        ]
    ];

    $topUsers = $activitiesCollection->aggregate($pipeline)->toArray();

    // Empresas más activas
    $companyPipeline = [
        [
            '$group' => [
                '_id' => '$company_id',
                'task_count' => ['$sum' => 1]
            ]
        ],
        [
            '$sort' => ['task_count' => -1]
        ],
        [
            '$limit' => 5
        ]
    ];

    $topCompanies = $tasksCollection->aggregate($companyPipeline)->toArray();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_users' => $totalUsers,
            'total_tasks' => $totalTasks,
            'total_companies' => $totalCompanies,
            'monthly_revenue' => round($monthlyRevenue, 2),
            'tasks_in_progress' => $tasksInProgress,
            'overdue_tasks' => $overdueTasks,
            'tasks_completed_today' => $tasksCompletedToday,
            'weekly_productivity' => $weeklyProductivity,
            'top_users' => $topUsers,
            'top_companies' => $topCompanies
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas del dashboard',
        'error' => $e->getMessage()
    ]);
}
?>