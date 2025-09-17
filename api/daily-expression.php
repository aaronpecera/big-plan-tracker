<?php
/**
 * API para el sistema de expresión diaria
 * Maneja el registro y consulta de estados emocionales diarios
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

$db = MongoDBConfig::getInstance();

try {
    $collection = $db->getCollection('daily_expressions');
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            // Crear nueva expresión diaria
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Datos inválidos');
            }
            
            // Validar campos requeridos
            $required_fields = ['mood', 'energy', 'stress', 'productivity', 'weather'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Campo requerido: $field");
                }
            }
            
            // Verificar si ya existe una expresión para hoy
            $today_start = new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000);
            $today_end = new MongoDB\BSON\UTCDateTime(strtotime('tomorrow') * 1000);
            
            $existing = $collection->findOne([
                'user_id' => $_SESSION['user_id'],
                'date' => [
                    '$gte' => $today_start,
                    '$lt' => $today_end
                ]
            ]);
            
            if ($existing) {
                // Actualizar expresión existente
                $update_data = [
                    'mood' => (int)$input['mood'],
                    'energy' => (int)$input['energy'],
                    'stress' => (int)$input['stress'],
                    'productivity' => (int)$input['productivity'],
                    'weather' => $input['weather'],
                    'tags' => $input['tags'] ?? [],
                    'notes' => $input['notes'] ?? '',
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $result = $collection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($existing['_id'])],
                    ['$set' => $update_data]
                );
                
                if ($result->getModifiedCount() > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Expresión diaria actualizada',
                        'id' => (string)$existing['_id']
                    ]);
                } else {
                    throw new Exception('Error al actualizar la expresión');
                }
            } else {
                // Crear nueva expresión
                $expression_data = [
                    'user_id' => $_SESSION['user_id'],
                    'mood' => (int)$input['mood'],
                    'energy' => (int)$input['energy'],
                    'stress' => (int)$input['stress'],
                    'productivity' => (int)$input['productivity'],
                    'weather' => $input['weather'],
                    'tags' => $input['tags'] ?? [],
                    'notes' => $input['notes'] ?? '',
                    'date' => new MongoDB\BSON\UTCDateTime(),
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $result = $collection->insertOne($expression_data);
                
                if ($result->getInsertedId()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Expresión diaria guardada',
                        'id' => (string)$result->getInsertedId()
                    ]);
                } else {
                    throw new Exception('Error al guardar la expresión');
                }
            }
            break;
            
        case 'GET':
            // Obtener expresiones diarias
            $user_id = $_SESSION['user_id'];
            $period = $_GET['period'] ?? 'week'; // week, month, year
            $page = (int)($_GET['page'] ?? 1);
            $limit = 20;
            $skip = ($page - 1) * $limit;
            
            // Calcular rango de fechas
            $end_date = new MongoDB\BSON\UTCDateTime();
            $start_date = new MongoDB\BSON\UTCDateTime();
            
            switch ($period) {
                case 'week':
                    $start_date = new MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000);
                    break;
                case 'month':
                    $start_date = new MongoDB\BSON\UTCDateTime(strtotime('-30 days') * 1000);
                    break;
                case 'year':
                    $start_date = new MongoDB\BSON\UTCDateTime(strtotime('-365 days') * 1000);
                    break;
            }
            
            $filter = [
                'user_id' => $user_id,
                'date' => [
                    '$gte' => $start_date,
                    '$lte' => $end_date
                ]
            ];
            
            // Obtener expresiones
            $expressions = $collection->find($filter, [
                'sort' => ['date' => -1],
                'skip' => $skip,
                'limit' => $limit
            ])->toArray();
            
            // Contar total
            $total = $collection->countDocuments($filter);
            
            // Calcular estadísticas
            $stats = [];
            if (!empty($expressions)) {
                $mood_avg = array_sum(array_column($expressions, 'mood')) / count($expressions);
                $energy_avg = array_sum(array_column($expressions, 'energy')) / count($expressions);
                $stress_avg = array_sum(array_column($expressions, 'stress')) / count($expressions);
                $productivity_avg = array_sum(array_column($expressions, 'productivity')) / count($expressions);
                
                $stats = [
                    'mood_avg' => round($mood_avg, 1),
                    'energy_avg' => round($energy_avg, 1),
                    'stress_avg' => round($stress_avg, 1),
                    'productivity_avg' => round($productivity_avg, 1),
                    'total_entries' => count($expressions)
                ];
            }
            
            // Formatear respuesta
            $formatted_expressions = array_map(function($expr) {
                return [
                    'id' => (string)$expr['_id'],
                    'mood' => $expr['mood'],
                    'energy' => $expr['energy'],
                    'stress' => $expr['stress'],
                    'productivity' => $expr['productivity'],
                    'weather' => $expr['weather'],
                    'tags' => $expr['tags'] ?? [],
                    'notes' => $expr['notes'] ?? '',
                    'date' => $expr['date']->toDateTime()->format('Y-m-d H:i:s'),
                    'created_at' => $expr['created_at']->toDateTime()->format('Y-m-d H:i:s')
                ];
            }, $expressions);
            
            echo json_encode([
                'success' => true,
                'expressions' => $formatted_expressions,
                'stats' => $stats,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'PUT':
            // Actualizar expresión específica
            $input = json_decode(file_get_contents('php://input'), true);
            $expression_id = $_GET['id'] ?? null;
            
            if (!$expression_id || !$input) {
                throw new Exception('ID y datos requeridos');
            }
            
            $update_data = [
                'mood' => (int)$input['mood'],
                'energy' => (int)$input['energy'],
                'stress' => (int)$input['stress'],
                'productivity' => (int)$input['productivity'],
                'weather' => $input['weather'],
                'tags' => $input['tags'] ?? [],
                'notes' => $input['notes'] ?? '',
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $collection->updateOne(
                [
                    '_id' => new MongoDB\BSON\ObjectId($expression_id),
                    'user_id' => $_SESSION['user_id']
                ],
                ['$set' => $update_data]
            );
            
            if ($result->getModifiedCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Expresión actualizada'
                ]);
            } else {
                throw new Exception('Expresión no encontrada o sin cambios');
            }
            break;
            
        case 'DELETE':
            // Eliminar expresión
            $expression_id = $_GET['id'] ?? null;
            
            if (!$expression_id) {
                throw new Exception('ID requerido');
            }
            
            $result = $collection->deleteOne([
                '_id' => new MongoDB\BSON\ObjectId($expression_id),
                'user_id' => $_SESSION['user_id']
            ]);
            
            if ($result->getDeletedCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Expresión eliminada'
                ]);
            } else {
                throw new Exception('Expresión no encontrada');
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
 * Función auxiliar para validar datos de entrada
 */
function validateExpressionData($data) {
    $errors = [];
    
    // Validar rangos numéricos (1-10)
    $numeric_fields = ['mood', 'energy', 'stress', 'productivity'];
    foreach ($numeric_fields as $field) {
        if (!isset($data[$field]) || !is_numeric($data[$field])) {
            $errors[] = "Campo $field debe ser numérico";
        } elseif ($data[$field] < 1 || $data[$field] > 10) {
            $errors[] = "Campo $field debe estar entre 1 y 10";
        }
    }
    
    // Validar clima
    $valid_weather = ['sunny', 'cloudy', 'rainy', 'stormy', 'snowy'];
    if (!isset($data['weather']) || !in_array($data['weather'], $valid_weather)) {
        $errors[] = "Campo weather debe ser uno de: " . implode(', ', $valid_weather);
    }
    
    return $errors;
}
?>