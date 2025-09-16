<?php
/**
 * Projects API
 * Handles project management operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Load autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables only in development
if (file_exists(__DIR__ . '/../../.env') && getenv('APP_ENV') !== 'production') {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

use App\Config\DatabaseConnection;

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required'
        ]);
        exit();
    }

    $db = DatabaseConnection::getInstance();
    $database = $db->getDatabase();
    $projectsCollection = $database->selectCollection('projects');
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    
    switch ($method) {
        case 'GET':
            if ($action === 'count') {
                handleCount($projectsCollection);
            } elseif ($action === 'list') {
                handleList($projectsCollection);
            } elseif ($action === 'get' && isset($_GET['id'])) {
                handleGet($projectsCollection, $_GET['id']);
            } else {
                throw new Exception('Invalid action or missing parameters');
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                handleCreate($projectsCollection);
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'PUT':
            if ($action === 'update' && isset($_GET['id'])) {
                handleUpdate($projectsCollection, $_GET['id']);
            } else {
                throw new Exception('Invalid action or missing parameters');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete' && isset($_GET['id'])) {
                handleDelete($projectsCollection, $_GET['id']);
            } else {
                throw new Exception('Invalid action or missing parameters');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
    echo json_encode($response);
}

function handleCount($projectsCollection) {
    $companyId = $_SESSION['company_id'] ?? null;
    
    $filter = [];
    if ($companyId) {
        $filter['company_id'] = new MongoDB\BSON\ObjectId($companyId);
    }
    
    $count = $projectsCollection->countDocuments($filter);
    
    $response = [
        'success' => true,
        'count' => $count,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function handleList($projectsCollection) {
    $companyId = $_SESSION['company_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    $status = $_GET['status'] ?? null;
    
    $filter = [];
    if ($companyId) {
        $filter['company_id'] = new MongoDB\BSON\ObjectId($companyId);
    }
    if ($status) {
        $filter['status'] = $status;
    }
    
    $options = [
        'limit' => $limit,
        'skip' => $offset,
        'sort' => ['created_at' => -1]
    ];
    
    $cursor = $projectsCollection->find($filter, $options);
    $projects = [];
    
    foreach ($cursor as $project) {
        $projects[] = [
            'id' => (string)$project['_id'],
            'name' => $project['name'],
            'description' => $project['description'] ?? '',
            'status' => $project['status'],
            'priority' => $project['priority'] ?? 'medium',
            'start_date' => $project['start_date'] ? $project['start_date']->toDateTime()->format('Y-m-d') : null,
            'end_date' => $project['end_date'] ? $project['end_date']->toDateTime()->format('Y-m-d') : null,
            'budget' => $project['budget'] ?? 0,
            'progress' => $project['progress'] ?? 0,
            'team_members' => $project['team_members'] ?? [],
            'tags' => $project['tags'] ?? [],
            'created_at' => $project['created_at']->toDateTime()->format('Y-m-d H:i:s'),
            'updated_at' => $project['updated_at']->toDateTime()->format('Y-m-d H:i:s')
        ];
    }
    
    $total = $projectsCollection->countDocuments($filter);
    
    $response = [
        'success' => true,
        'projects' => $projects,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function handleGet($projectsCollection, $projectId) {
    try {
        $project = $projectsCollection->findOne([
            '_id' => new MongoDB\BSON\ObjectId($projectId)
        ]);
        
        if (!$project) {
            throw new Exception('Project not found');
        }
        
        // Check if user has access to this project
        $companyId = $_SESSION['company_id'] ?? null;
        if ($companyId && (string)$project['company_id'] !== $companyId) {
            throw new Exception('Access denied');
        }
        
        $response = [
            'success' => true,
            'project' => [
                'id' => (string)$project['_id'],
                'name' => $project['name'],
                'description' => $project['description'] ?? '',
                'status' => $project['status'],
                'priority' => $project['priority'] ?? 'medium',
                'start_date' => $project['start_date'] ? $project['start_date']->toDateTime()->format('Y-m-d') : null,
                'end_date' => $project['end_date'] ? $project['end_date']->toDateTime()->format('Y-m-d') : null,
                'budget' => $project['budget'] ?? 0,
                'progress' => $project['progress'] ?? 0,
                'team_members' => $project['team_members'] ?? [],
                'tags' => $project['tags'] ?? [],
                'created_at' => $project['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                'updated_at' => $project['updated_at']->toDateTime()->format('Y-m-d H:i:s')
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get project: ' . $e->getMessage());
    }
}

function handleCreate($projectsCollection) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['name'])) {
        throw new Exception('Project name is required');
    }
    
    $companyId = $_SESSION['company_id'] ?? null;
    if (!$companyId) {
        throw new Exception('Company ID is required');
    }
    
    $projectData = [
        'name' => trim($input['name']),
        'description' => trim($input['description'] ?? ''),
        'company_id' => new MongoDB\BSON\ObjectId($companyId),
        'status' => $input['status'] ?? 'active',
        'priority' => $input['priority'] ?? 'medium',
        'start_date' => isset($input['start_date']) ? new MongoDB\BSON\UTCDateTime(strtotime($input['start_date']) * 1000) : null,
        'end_date' => isset($input['end_date']) ? new MongoDB\BSON\UTCDateTime(strtotime($input['end_date']) * 1000) : null,
        'budget' => (float)($input['budget'] ?? 0),
        'progress' => (int)($input['progress'] ?? 0),
        'team_members' => $input['team_members'] ?? [],
        'tags' => $input['tags'] ?? [],
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    $result = $projectsCollection->insertOne($projectData);
    
    $response = [
        'success' => true,
        'message' => 'Project created successfully',
        'project_id' => (string)$result->getInsertedId(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(201);
    echo json_encode($response);
}

function handleUpdate($projectsCollection, $projectId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    try {
        $updateData = ['updated_at' => new MongoDB\BSON\UTCDateTime()];
        
        if (isset($input['name'])) {
            $updateData['name'] = trim($input['name']);
        }
        if (isset($input['description'])) {
            $updateData['description'] = trim($input['description']);
        }
        if (isset($input['status'])) {
            $updateData['status'] = $input['status'];
        }
        if (isset($input['priority'])) {
            $updateData['priority'] = $input['priority'];
        }
        if (isset($input['start_date'])) {
            $updateData['start_date'] = new MongoDB\BSON\UTCDateTime(strtotime($input['start_date']) * 1000);
        }
        if (isset($input['end_date'])) {
            $updateData['end_date'] = new MongoDB\BSON\UTCDateTime(strtotime($input['end_date']) * 1000);
        }
        if (isset($input['budget'])) {
            $updateData['budget'] = (float)$input['budget'];
        }
        if (isset($input['progress'])) {
            $updateData['progress'] = (int)$input['progress'];
        }
        if (isset($input['team_members'])) {
            $updateData['team_members'] = $input['team_members'];
        }
        if (isset($input['tags'])) {
            $updateData['tags'] = $input['tags'];
        }
        
        $result = $projectsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($projectId)],
            ['$set' => $updateData]
        );
        
        if ($result->getMatchedCount() === 0) {
            throw new Exception('Project not found');
        }
        
        $response = [
            'success' => true,
            'message' => 'Project updated successfully',
            'modified_count' => $result->getModifiedCount(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update project: ' . $e->getMessage());
    }
}

function handleDelete($projectsCollection, $projectId) {
    try {
        $result = $projectsCollection->deleteOne([
            '_id' => new MongoDB\BSON\ObjectId($projectId)
        ]);
        
        if ($result->getDeletedCount() === 0) {
            throw new Exception('Project not found');
        }
        
        $response = [
            'success' => true,
            'message' => 'Project deleted successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to delete project: ' . $e->getMessage());
    }
}
?>