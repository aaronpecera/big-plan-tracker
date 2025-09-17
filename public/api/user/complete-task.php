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
    $timeSpent = $input['time_spent'] ?? null;
    
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
    
    // Preparar datos de actualización
    $updateData = [
        'status' => 'completed',
        'completed_at' => $database->createDateTimeObject(),
        'updated_at' => $database->createDateTimeObject()
    ];
    
    // Si se proporcionó tiempo trabajado, actualizarlo
    if ($timeSpent !== null && is_numeric($timeSpent)) {
        $updateData['actual_hours'] = floatval($timeSpent);
    }
    
    // Actualizar estado de la tarea
    $updateResult = $tasksCollection->updateOne(
        ['_id' => $taskId],
        ['$set' => $updateData]
    );
    
    if ($updateResult->getModifiedCount() > 0) {
        // Registrar actividad
        $activitiesCollection = $db->selectCollection('activities');
        $activitiesCollection->insertOne([
            'user_id' => $userId,
            'task_id' => $taskId,
            'action' => 'task_completed',
            'description' => 'Tarea completada: ' . $task['title'],
            'timestamp' => $database->createDateTimeObject(),
            'date' => date('Y-m-d H:i:s'),
            'time_spent' => $timeSpent
        ]);
        
        // Calcular costo si hay tiempo trabajado
        if ($timeSpent !== null) {
            // Obtener información del usuario para calcular costo
            $usersCollection = $db->selectCollection('users');
            $user = $usersCollection->findOne(['_id' => $userId]);
            
            if ($user && isset($user['hourly_rate'])) {
                $cost = floatval($timeSpent) * floatval($user['hourly_rate']);
                
                // Actualizar costo de la tarea
                $tasksCollection->updateOne(
                    ['_id' => $taskId],
                    ['$set' => ['actual_cost' => $cost]]
                );
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Tarea completada exitosamente'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al completar la tarea']);
    }

} catch (Exception $e) {
    error_log("Error en user/complete-task.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>