<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../middleware/auth.php';
require_once '../config/database.php';

// Verificar autenticación de usuario
requireUser();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = DatabaseConnection::getInstance();
    $database = $db->getDatabase();
    $extensionRequestsCollection = $database->selectCollection('extension_requests');
    $tasksCollection = $database->selectCollection('tasks');
    $usersCollection = $database->selectCollection('users');

    $currentUser = $_SESSION['user_data'];
    $userId = $currentUser['id'];

    switch ($method) {
        case 'GET':
            // Obtener solicitudes de extensión del usuario
            $cursor = $extensionRequestsCollection->find([
                'user_id' => $userId
            ], [
                'sort' => ['created_at' => -1]
            ]);
            $requests = iterator_to_array($cursor);

            $enrichedRequests = [];
            foreach ($requests as $request) {
                $enrichedRequest = [
                    'id' => (string)$request['_id'],
                    'task_id' => $request['task_id'],
                    'current_due_date' => isset($request['current_due_date']) ? $request['current_due_date']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'requested_due_date' => isset($request['requested_due_date']) ? $request['requested_due_date']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'reason' => $request['reason'] ?? '',
                    'status' => $request['status'] ?? 'pending',
                    'admin_response' => $request['admin_response'] ?? null,
                    'created_at' => isset($request['created_at']) ? $request['created_at']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'reviewed_at' => isset($request['reviewed_at']) ? $request['reviewed_at']->toDateTime()->format('Y-m-d H:i:s') : null
                ];

                // Obtener información de la tarea
                if (isset($request['task_id'])) {
                    $task = $tasksCollection->findOne(['_id' => $request['task_id']]);
                    if ($task) {
                        $enrichedRequest['task_title'] = $task['title'] ?? 'Sin título';
                        $enrichedRequest['task_description'] = $task['description'] ?? '';
                    }
                }

                $enrichedRequests[] = $enrichedRequest;
            }

            echo json_encode([
                'success' => true,
                'requests' => $enrichedRequests,
                'total' => count($enrichedRequests)
            ]);
            break;

        case 'POST':
            // Crear nueva solicitud de extensión
            $input = json_decode(file_get_contents('php://input'), true);
            
            $taskId = $input['task_id'] ?? '';
            $requestedDueDate = $input['requested_due_date'] ?? '';
            $reason = $input['reason'] ?? '';

            if (!$taskId || !$requestedDueDate || !$reason) {
                throw new Exception('Todos los campos son requeridos');
            }

            // Verificar que la tarea existe y pertenece al usuario
            $task = $tasksCollection->findOne([
                '_id' => $taskId,
                'assigned_to' => $userId
            ]);

            if (!$task) {
                throw new Exception('Tarea no encontrada o no asignada al usuario');
            }

            // Verificar que no hay una solicitud pendiente para esta tarea
            $existingRequest = $extensionRequestsCollection->findOne([
                'task_id' => $taskId,
                'user_id' => $userId,
                'status' => 'pending'
            ]);

            if ($existingRequest) {
                throw new Exception('Ya existe una solicitud pendiente para esta tarea');
            }

            // Validar que la nueva fecha sea posterior a la actual
            $currentDueDate = $task['due_date']->toDateTime();
            $newDueDate = new DateTime($requestedDueDate);

            if ($newDueDate <= $currentDueDate) {
                throw new Exception('La nueva fecha debe ser posterior a la fecha actual de vencimiento');
            }

            // Crear la solicitud
            $extensionRequest = [
                'task_id' => $taskId,
                'user_id' => $userId,
                'current_due_date' => $task['due_date'],
                'requested_due_date' => $newDueDate,
                'reason' => $reason,
                'status' => 'pending',
                'created_at' => new DateTime(),
                'admin_response' => null,
                'reviewed_at' => null,
                'reviewed_by' => null
            ];

            $result = $extensionRequestsCollection->insertOne($extensionRequest);

            // Registrar actividad
            $activitiesCollection = $database->selectCollection('activities');
            $activitiesCollection->insertOne([
                'user_id' => $userId,
                'task_id' => $taskId,
                'type' => 'extension_request',
                'description' => "Solicitud de extensión para la tarea: {$task['title']}",
                'timestamp' => new DateTime(),
                'metadata' => [
                    'current_due_date' => $currentDueDate->format('Y-m-d H:i:s'),
                    'requested_due_date' => $newDueDate->format('Y-m-d H:i:s'),
                    'reason' => $reason
                ]
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Solicitud de extensión enviada correctamente',
                'request_id' => (string)$result->getInsertedId()
            ]);
            break;

        case 'PUT':
            // Cancelar solicitud de extensión (solo si está pendiente)
            $input = json_decode(file_get_contents('php://input'), true);
            $requestId = $input['request_id'] ?? '';

            if (!$requestId) {
                throw new Exception('ID de solicitud requerido');
            }

            $request = $extensionRequestsCollection->findOne([
                '_id' => $requestId,
                'user_id' => $userId,
                'status' => 'pending'
            ]);

            if (!$request) {
                throw new Exception('Solicitud no encontrada o no se puede cancelar');
            }

            $extensionRequestsCollection->updateOne(
                ['_id' => $requestId],
                [
                    '$set' => [
                        'status' => 'cancelled',
                        'reviewed_at' => new DateTime()
                    ]
                ]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Solicitud cancelada correctamente'
            ]);
            break;

        default:
            throw new Exception('Método no permitido');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>