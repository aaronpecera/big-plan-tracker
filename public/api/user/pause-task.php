<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../middleware/auth.php';

try {
    // Verificar autenticación
    $authResult = requireUser();
    $userId = $authResult['user_id'];

    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = $input['task_id'] ?? '';
    
    if (empty($taskId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de tarea requerido']);
        exit;
    }

    $database = DatabaseConnection::getInstance();
    $db = $database->getConnection();
    
    // Verificar que la tarea existe y está asignada al usuario
    $tasksCollection = $db->selectCollection('tasks');
    $task = $tasksCollection->findOne([
        '_id' => $taskId,
        'assigned_to' => $userId
    ]);
    
    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
        exit;
    }
    
    // Actualizar estado de la tarea
    $updateResult = $tasksCollection->updateOne(
        ['_id' => $taskId],
        [
            '$set' => [
                'status' => 'paused',
                'paused_at' => $database->createDateTimeObject(),
                'updated_at' => $database->createDateTimeObject()
            ]
        ]
    );
    
    if ($updateResult->getModifiedCount() > 0) {
        // Registrar actividad
        $activitiesCollection = $db->selectCollection('activities');
        $activitiesCollection->insertOne([
            'user_id' => $userId,
            'task_id' => $taskId,
            'action' => 'task_paused',
            'description' => 'Tarea pausada: ' . $task['title'],
            'timestamp' => $database->createDateTimeObject(),
            'date' => date('Y-m-d H:i:s')
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Tarea pausada exitosamente'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al pausar la tarea']);
    }

} catch (Exception $e) {
    error_log("Error en user/pause-task.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>