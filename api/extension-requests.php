<?php
/**
 * API para el sistema de solicitudes de extensión
 * Maneja solicitudes de extensión de tiempo para tareas
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/mongodb.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$db = MongoDBConfig::getInstance();

try {
    $collection = $db->getCollection('extension_requests');
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            // Crear nueva solicitud de extensión
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Datos inválidos');
            }
            
            // Validar campos requeridos
            $required_fields = ['task_id', 'reason', 'requested_extension_days'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Campo requerido: $field");
                }
            }
            
            // Validar que el usuario sea el propietario de la tarea o tenga permisos
            $tasksCollection = $db->getCollection('tasks');
            $task = $tasksCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($input['task_id'])
            ]);
            
            if (!$task) {
                throw new Exception('Tarea no encontrada');
            }
            
            // Verificar permisos
            if ($task['assigned_to'] !== $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin') {
                throw new Exception('No tienes permisos para solicitar extensión de esta tarea');
            }
            
            // Crear solicitud
            $request_data = [
                'task_id' => new MongoDB\BSON\ObjectId($input['task_id']),
                'user_id' => $_SESSION['user_id'],
                'reason' => $input['reason'],
                'requested_extension_days' => (int)$input['requested_extension_days'],
                'priority' => $input['priority'] ?? 'medium',
                'status' => 'pending',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $collection->insertOne($request_data);
            
            if ($result->getInsertedId()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Solicitud de extensión creada',
                    'id' => (string)$result->getInsertedId()
                ]);
            } else {
                throw new Exception('Error al crear la solicitud');
            }
            break;
            
        case 'GET':
            // Obtener solicitudes de extensión
            $user_id = $_SESSION['user_id'];
            $user_role = $_SESSION['user_role'];
            $status = $_GET['status'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $limit = 20;
            $skip = ($page - 1) * $limit;
            
            $filter = [];
            
            // Filtrar por usuario si no es admin
            if ($user_role !== 'admin') {
                $filter['user_id'] = $user_id;
            }
            
            // Filtrar por estado si se especifica
            if ($status) {
                $filter['status'] = $status;
            }
            
            // Obtener solicitudes
            $requests = $collection->find($filter, [
                'sort' => ['created_at' => -1],
                'skip' => $skip,
                'limit' => $limit
            ])->toArray();
            
            // Contar total
            $total = $collection->countDocuments($filter);
            
            // Enriquecer con información de tareas y usuarios
            $tasksCollection = $db->getCollection('tasks');
            $usersCollection = $db->getCollection('users');
            
            foreach ($requests as &$request) {
                // Obtener información de la tarea
                $task = $tasksCollection->findOne([
                    '_id' => $request['task_id']
                ]);
                $request['task_title'] = $task['title'] ?? 'Tarea no encontrada';
                $request['task_due_date'] = $task['due_date'] ?? null;
                
                // Obtener información del usuario
                $user = $usersCollection->findOne([
                    'username' => $request['user_id']
                ]);
                $request['user_name'] = $user['first_name'] . ' ' . $user['last_name'] ?? 'Usuario desconocido';
            }
            
            // Formatear respuesta
            $formatted_requests = array_map(function($req) {
                return [
                    'id' => (string)$req['_id'],
                    'task_id' => (string)$req['task_id'],
                    'task_title' => $req['task_title'],
                    'task_due_date' => $req['task_due_date'] ? $req['task_due_date']->toDateTime()->format('Y-m-d') : null,
                    'user_id' => $req['user_id'],
                    'user_name' => $req['user_name'],
                    'reason' => $req['reason'],
                    'requested_extension_days' => $req['requested_extension_days'],
                    'priority' => $req['priority'],
                    'status' => $req['status'],
                    'admin_response' => $req['admin_response'] ?? null,
                    'reviewed_by' => $req['reviewed_by'] ?? null,
                    'reviewed_at' => $req['reviewed_at'] ? $req['reviewed_at']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'created_at' => $req['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'updated_at' => $req['updated_at']->toDateTime()->format('Y-m-d H:i:s')
                ];
            }, $requests);
            
            echo json_encode([
                'success' => true,
                'requests' => $formatted_requests,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'PUT':
            // Actualizar solicitud (aprobar/rechazar - solo admins)
            if ($_SESSION['user_role'] !== 'admin') {
                throw new Exception('Solo los administradores pueden revisar solicitudes');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $request_id = $_GET['id'] ?? null;
            
            if (!$request_id || !$input) {
                throw new Exception('ID y datos requeridos');
            }
            
            $valid_statuses = ['approved', 'rejected'];
            if (!isset($input['status']) || !in_array($input['status'], $valid_statuses)) {
                throw new Exception('Estado inválido');
            }
            
            $update_data = [
                'status' => $input['status'],
                'admin_response' => $input['admin_response'] ?? '',
                'reviewed_by' => $_SESSION['user_id'],
                'reviewed_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Si se aprueba, actualizar la fecha de vencimiento de la tarea
            if ($input['status'] === 'approved') {
                $request = $collection->findOne([
                    '_id' => new MongoDB\BSON\ObjectId($request_id)
                ]);
                
                if ($request) {
                    $tasksCollection = $db->getCollection('tasks');
                    $task = $tasksCollection->findOne([
                        '_id' => $request['task_id']
                    ]);
                    
                    if ($task && isset($task['due_date'])) {
                        $current_due_date = $task['due_date']->toDateTime();
                        $new_due_date = $current_due_date->modify('+' . $request['requested_extension_days'] . ' days');
                        
                        $tasksCollection->updateOne(
                            ['_id' => $request['task_id']],
                            ['$set' => [
                                'due_date' => new MongoDB\BSON\UTCDateTime($new_due_date->getTimestamp() * 1000),
                                'updated_at' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );
                    }
                }
            }
            
            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($request_id)],
                ['$set' => $update_data]
            );
            
            if ($result->getModifiedCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Solicitud ' . ($input['status'] === 'approved' ? 'aprobada' : 'rechazada')
                ]);
            } else {
                throw new Exception('Solicitud no encontrada o sin cambios');
            }
            break;
            
        case 'DELETE':
            // Eliminar solicitud (solo el creador o admin)
            $request_id = $_GET['id'] ?? null;
            
            if (!$request_id) {
                throw new Exception('ID requerido');
            }
            
            $filter = ['_id' => new MongoDB\BSON\ObjectId($request_id)];
            
            // Si no es admin, solo puede eliminar sus propias solicitudes
            if ($_SESSION['user_role'] !== 'admin') {
                $filter['user_id'] = $_SESSION['user_id'];
            }
            
            $result = $collection->deleteOne($filter);
            
            if ($result->getDeletedCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Solicitud eliminada'
                ]);
            } else {
                throw new Exception('Solicitud no encontrada');
            }
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Función auxiliar para obtener estadísticas de solicitudes
 */
function getExtensionRequestStats($db) {
    try {
        $collection = $db->getCollection('extension_requests');
        
        $stats = [
            'total' => $collection->countDocuments([]),
            'pending' => $collection->countDocuments(['status' => 'pending']),
            'approved' => $collection->countDocuments(['status' => 'approved']),
            'rejected' => $collection->countDocuments(['status' => 'rejected'])
        ];
        
        // Calcular tiempo promedio de respuesta
        $pipeline = [
            ['$match' => ['status' => ['$in' => ['approved', 'rejected']]]],
            ['$project' => [
                'response_time' => [
                    '$subtract' => ['$reviewed_at', '$created_at']
                ]
            ]],
            ['$group' => [
                '_id' => null,
                'avg_response_time' => ['$avg' => '$response_time']
            ]]
        ];
        
        $result = $collection->aggregate($pipeline)->toArray();
        $stats['avg_response_time_hours'] = !empty($result) ? 
            round($result[0]['avg_response_time'] / (1000 * 60 * 60), 1) : 0;
        
        return $stats;
        
    } catch (Exception $e) {
        return [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'avg_response_time_hours' => 0
        ];
    }
}
?>