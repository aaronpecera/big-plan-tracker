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
    $companiesCollection = $database->selectCollection('companies');

    $userId = new MongoDB\BSON\ObjectId($user['_id']);

    // Obtener todas las tareas del usuario
    $tasks = $tasksCollection->find([
        'assigned_to' => $userId
    ], [
        'sort' => ['created_at' => -1]
    ])->toArray();

    // Enriquecer con información de la empresa
    $enrichedTasks = [];
    foreach ($tasks as $task) {
        $taskArray = [
            '_id' => (string)$task['_id'],
            'title' => $task['title'] ?? '',
            'description' => $task['description'] ?? '',
            'status' => $task['status'] ?? 'pending',
            'priority' => $task['priority'] ?? 'medium',
            'due_date' => isset($task['due_date']) ? $task['due_date']->toDateTime()->format('c') : null,
            'estimated_hours' => $task['estimated_hours'] ?? 0,
            'created_at' => isset($task['created_at']) ? $task['created_at']->toDateTime()->format('c') : null,
            'started_at' => isset($task['started_at']) ? $task['started_at']->toDateTime()->format('c') : null,
            'completed_at' => isset($task['completed_at']) ? $task['completed_at']->toDateTime()->format('c') : null,
            'company_name' => 'Sin empresa'
        ];

        // Obtener información de la empresa
        if (isset($task['company_id'])) {
            $company = $companiesCollection->findOne(['_id' => $task['company_id']]);
            if ($company) {
                $taskArray['company_name'] = $company['name'] ?? 'Sin empresa';
            }
        }

        $enrichedTasks[] = $taskArray;
    }

    echo json_encode([
        'success' => true,
        'tasks' => $enrichedTasks
    ]);

} catch (Exception $e) {
    error_log("Error en user tasks: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>