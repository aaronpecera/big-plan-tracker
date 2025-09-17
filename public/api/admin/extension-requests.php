<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../middleware/auth.php';
require_once '../config/database.php';

// Verificar autenticación de administrador
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = DatabaseConnection::getInstance();
    $database = $db->getDatabase();
    $extensionRequestsCollection = $database->selectCollection('extension_requests');
    $tasksCollection = $database->selectCollection('tasks');
    $usersCollection = $database->selectCollection('users');
    $activitiesCollection = $database->selectCollection('activities');

    $currentAdmin = $_SESSION['user_data'];

    switch ($method) {
        case 'GET':
            // Obtener todas las solicitudes de extensión
            $status = $_GET['status'] ?? 'all';
            $filter = [];

            if ($status !== 'all') {
                $filter['status'] = $status;
            }

            $cursor = $extensionRequestsCollection->find($filter, [
                'sort' => ['created_at' => -1]
            ]);
            $requests = iterator_to_array($cursor);

            $enrichedRequests = [];
            foreach ($requests as $request) {
                $enrichedRequest = [
                    'id' => (string)$request['_id'],
                    'task_id' => $request['task_id'],
                    'user_id' => $request['user_id'],
                    'current_due_date' => isset($request['current_due_date']) ? $request['current_due_date']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'requested_due_date' => isset($request['requested_due_date']) ? $request['requested_due_date']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'reason' => $request['reason'] ?? '',
                    'status' => $request['status'] ?? 'pending',
                    'admin_response' => $request['admin_response'] ?? null,
                    'created_at' => isset($request['created_at']) ? $request['created_at']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'reviewed_at' => isset($request['reviewed_at']) ? $request['reviewed_at']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'reviewed_by' => $request['reviewed_by'] ?? null
                ];

                // Obtener información de la tarea
                if (isset($request['task_id'])) {
                    $task = $tasksCollection->findOne(['_id' => $request['task_id']]);
                    if ($task) {
                        $enrichedRequest['task_title'] = $task['title'] ?? 'Sin título';
                        $enrichedRequest['task_description'] = $task['description'] ?? '';
                        $enrichedRequest['task_priority'] = $task['priority'] ?? 'medium';
                        $enrichedRequest['task_status'] = $task['status'] ?? 'not_started';
                    }
                }

                // Obtener información del usuario
                if (isset($request['user_id'])) {
                    $user = $usersCollection->findOne(['_id' => $request['user_id']]);
                    if ($user) {
                        $enrichedRequest['user_name'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
                        $enrichedRequest['user_email'] = $user['email'] ?? '';
                    }
                }

                // Calcular días de extensión solicitados
                if (isset($request['current_due_date']) && isset($request['requested_due_date'])) {
                    $currentDate = $request['current_due_date']->toDateTime();
                    $requestedDate = $request['requested_due_date']->toDateTime();
                    $interval = $currentDate->diff($requestedDate);
                    $enrichedRequest['extension_days'] = $interval->days;
                }

                $enrichedRequests[] = $enrichedRequest;
            }

            echo json_encode([
                'success' => true,
                'requests' => $enrichedRequests,
                'total' => count($enrichedRequests),
                'pending_count' => count(array_filter($enrichedRequests, function($r) {
                    return $r['status'] === 'pending';
                }))
            ]);
            break;

        case 'PUT':
            // Aprobar o rechazar solicitud de extensión
            $input = json_decode(file_get_contents('php://input'), true);
            
            $requestId = $input['request_id'] ?? '';
            $action = $input['action'] ?? ''; // 'approve' o 'reject'
            $adminResponse = $input['admin_response'] ?? '';

            if (!$requestId || !$action) {
                throw new Exception('ID de solicitud y acción son requeridos');
            }

            if (!in_array($action, ['approve', 'reject'])) {
                throw new Exception('Acción no válida');
            }

            // Buscar la solicitud
            $request = $extensionRequestsCollection->findOne([
                '_id' => $requestId,
                'status' => 'pending'
            ]);

            if (!$request) {
                throw new Exception('Solicitud no encontrada o ya procesada');
            }

            // Actualizar la solicitud
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            
            $updateData = [
                'status' => $newStatus,
                'admin_response' => $adminResponse,
                'reviewed_at' => new DateTime(),
                'reviewed_by' => $currentAdmin['id']
            ];

            $extensionRequestsCollection->updateOne(
                ['_id' => $requestId],
                ['$set' => $updateData]
            );

            // Si se aprueba, actualizar la fecha de vencimiento de la tarea
            if ($action === 'approve') {
                $tasksCollection->updateOne(
                    ['_id' => $request['task_id']],
                    [
                        '$set' => [
                            'due_date' => $request['requested_due_date'],
                            'extension_granted' => true,
                            'extension_granted_at' => new DateTime(),
                            'extension_granted_by' => $currentAdmin['id']
                        ]
                    ]
                );

                // Registrar actividad de aprobación
                $activitiesCollection->insertOne([
                    'user_id' => $currentAdmin['id'],
                    'task_id' => $request['task_id'],
                    'type' => 'extension_approved',
                    'description' => "Extensión aprobada para la tarea",
                    'timestamp' => new DateTime(),
                    'metadata' => [
                        'original_due_date' => $request['current_due_date']->toDateTime()->format('Y-m-d H:i:s'),
                        'new_due_date' => $request['requested_due_date']->toDateTime()->format('Y-m-d H:i:s'),
                        'admin_response' => $adminResponse,
                        'requested_by' => $request['user_id']
                    ]
                ]);
            } else {
                // Registrar actividad de rechazo
                $activitiesCollection->insertOne([
                    'user_id' => $currentAdmin['id'],
                    'task_id' => $request['task_id'],
                    'type' => 'extension_rejected',
                    'description' => "Extensión rechazada para la tarea",
                    'timestamp' => new DateTime(),
                    'metadata' => [
                        'requested_due_date' => $request['requested_due_date']->toDateTime()->format('Y-m-d H:i:s'),
                        'admin_response' => $adminResponse,
                        'requested_by' => $request['user_id']
                    ]
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => $action === 'approve' ? 'Solicitud aprobada correctamente' : 'Solicitud rechazada',
                'new_status' => $newStatus
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