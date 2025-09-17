<?php
require_once '../../src/config/database.php';
require_once '../../src/api/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Verificar autenticación
    $auth = new Auth();
    $user = $auth->getCurrentUser();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    // Verificar que sea usuario (no admin)
    if ($user['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }

    $db = new Database();
    $database = $db->getDatabase();
    $tasksCollection = $database->selectCollection('tasks');

    $userId = new MongoDB\BSON\ObjectId($user['_id']);

    // Obtener estadísticas del usuario
    $pipeline = [
        [
            '$match' => [
                'assigned_to' => $userId
            ]
        ],
        [
            '$group' => [
                '_id' => null,
                'total_tasks' => ['$sum' => 1],
                'completed_tasks' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$status', 'completed']],
                            1,
                            0
                        ]
                    ]
                ],
                'pending_tasks' => [
                    '$sum' => [
                        '$cond' => [
                            ['$in' => ['$status', ['pending', 'in_progress', 'paused']]],
                            1,
                            0
                        ]
                    ]
                ],
                'overdue_tasks' => [
                    '$sum' => [
                        '$cond' => [
                            [
                                '$and' => [
                                    ['$lt' => ['$due_date', new MongoDB\BSON\UTCDateTime()]],
                                    ['$ne' => ['$status', 'completed']]
                                ]
                            ],
                            1,
                            0
                        ]
                    ]
                ]
            ]
        ]
    ];

    $result = $tasksCollection->aggregate($pipeline)->toArray();
    
    $stats = [
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'pending_tasks' => 0,
        'overdue_tasks' => 0
    ];

    if (!empty($result)) {
        $data = $result[0];
        $stats = [
            'total_tasks' => $data['total_tasks'] ?? 0,
            'completed_tasks' => $data['completed_tasks'] ?? 0,
            'pending_tasks' => $data['pending_tasks'] ?? 0,
            'overdue_tasks' => $data['overdue_tasks'] ?? 0
        ];
    }

    // Calcular estadísticas adicionales
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $todayStart = new MongoDB\BSON\UTCDateTime($today->getTimestamp() * 1000);
    
    $tomorrow = clone $today;
    $tomorrow->add(new DateInterval('P1D'));
    $tomorrowStart = new MongoDB\BSON\UTCDateTime($tomorrow->getTimestamp() * 1000);

    // Tareas completadas hoy
    $completedToday = $tasksCollection->countDocuments([
        'assigned_to' => $userId,
        'status' => 'completed',
        'completed_at' => [
            '$gte' => $todayStart,
            '$lt' => $tomorrowStart
        ]
    ]);

    // Tiempo trabajado hoy (si tienes tracking de tiempo)
    $activitiesCollection = $database->selectCollection('activities');
    $timeToday = $activitiesCollection->aggregate([
        [
            '$match' => [
                'user_id' => $userId,
                'action' => ['$in' => ['task_started', 'task_completed', 'task_paused']],
                'created_at' => [
                    '$gte' => $todayStart,
                    '$lt' => $tomorrowStart
                ]
            ]
        ],
        [
            '$group' => [
                '_id' => '$task_id',
                'sessions' => ['$push' => '$$ROOT']
            ]
        ]
    ])->toArray();

    $totalMinutesWorked = 0;
    foreach ($timeToday as $taskSessions) {
        $sessions = $taskSessions['sessions'];
        $currentStart = null;
        
        foreach ($sessions as $session) {
            if ($session['action'] === 'task_started') {
                $currentStart = $session['created_at'];
            } elseif (($session['action'] === 'task_paused' || $session['action'] === 'task_completed') && $currentStart) {
                $duration = $session['created_at']->toDateTime()->getTimestamp() - $currentStart->toDateTime()->getTimestamp();
                $totalMinutesWorked += $duration / 60;
                $currentStart = null;
            }
        }
    }

    $stats['completed_today'] = $completedToday;
    $stats['time_worked_today'] = round($totalMinutesWorked);
    $stats['productivity_score'] = $stats['total_tasks'] > 0 ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) : 0;

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    error_log("Error en user stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>