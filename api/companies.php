<?php
// Configuración de CORS y headers
require_once '../src/Config/DatabaseConnection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Verificar permisos de administrador para ciertas acciones
$user_role = $_SESSION['user_role'] ?? 'user';
$action = $_GET['action'] ?? '';

if (in_array($action, ['create', 'update', 'delete']) && $user_role !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Permisos insuficientes']);
    exit();
}

try {
    $db = \App\Config\DatabaseConnection::getInstance();
    $database = $db->getDatabase();
    $companiesCollection = $database->companies;
    
    switch ($action) {
        case 'list':
            // Listar todas las empresas
            $companies = $companiesCollection->find([], [
                'sort' => ['name' => 1]
            ])->toArray();
            
            // Convertir ObjectId a string para JSON
            foreach ($companies as &$company) {
                $company['_id'] = (string)$company['_id'];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $companies
            ]);
            break;
            
        case 'create':
            // Crear nueva empresa
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Nombre de empresa requerido']);
                exit();
            }
            
            // Verificar si ya existe una empresa con ese nombre
            $existing = $companiesCollection->findOne(['name' => $input['name']]);
            if ($existing) {
                http_response_code(409);
                echo json_encode(['error' => 'Ya existe una empresa con ese nombre']);
                exit();
            }
            
            $companyData = [
                'name' => $input['name'],
                'description' => $input['description'] ?? '',
                'email' => $input['email'] ?? '',
                'phone' => $input['phone'] ?? '',
                'address' => $input['address'] ?? '',
                'website' => $input['website'] ?? '',
                'active' => true,
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
                'updated_at' => new \MongoDB\BSON\UTCDateTime(),
                'created_by' => $_SESSION['user_id']
            ];
            
            $result = $companiesCollection->insertOne($companyData);
            
            if ($result->getInsertedCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Empresa creada exitosamente',
                    'id' => (string)$result->getInsertedId()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al crear la empresa']);
            }
            break;
            
        case 'get':
            // Obtener empresa específica
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de empresa requerido']);
                exit();
            }
            
            try {
                $company = $companiesCollection->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
                
                if ($company) {
                    $company['_id'] = (string)$company['_id'];
                    echo json_encode([
                        'success' => true,
                        'data' => $company
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Empresa no encontrada']);
                }
            } catch (\MongoDB\Exception\InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de empresa inválido']);
            }
            break;
            
        case 'update':
            // Actualizar empresa
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de empresa requerido']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos de empresa requeridos']);
                exit();
            }
            
            try {
                $updateData = [
                    'updated_at' => new \MongoDB\BSON\UTCDateTime()
                ];
                
                // Solo actualizar campos proporcionados
                $allowedFields = ['name', 'description', 'email', 'phone', 'address', 'website', 'active'];
                foreach ($allowedFields as $field) {
                    if (isset($input[$field])) {
                        $updateData[$field] = $input[$field];
                    }
                }
                
                $result = $companiesCollection->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId($id)],
                    ['$set' => $updateData]
                );
                
                if ($result->getModifiedCount() > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Empresa actualizada exitosamente'
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Empresa no encontrada o sin cambios']);
                }
            } catch (\MongoDB\Exception\InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de empresa inválido']);
            }
            break;
            
        case 'delete':
            // Eliminar empresa (soft delete)
            $id = $_GET['id'] ?? '';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de empresa requerido']);
                exit();
            }
            
            try {
                $result = $companiesCollection->updateOne(
                    ['_id' => new \MongoDB\BSON\ObjectId($id)],
                    ['$set' => [
                        'active' => false,
                        'deleted_at' => new \MongoDB\BSON\UTCDateTime(),
                        'deleted_by' => $_SESSION['user_id']
                    ]]
                );
                
                if ($result->getModifiedCount() > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Empresa eliminada exitosamente'
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Empresa no encontrada']);
                }
            } catch (\MongoDB\Exception\InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de empresa inválido']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Error in companies.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>