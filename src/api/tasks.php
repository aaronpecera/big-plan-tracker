<?php
/**
 * Tasks API
 * Handles task management operations
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

// Session is already started in index.php

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
            'error' => 'Autenticación requerida'
        ]);
        exit();
    }

    $db = DatabaseConnection::getInstance();
    $database = $db->getDatabase();
    $tasksCollection = $database->selectCollection('tasks');
    $projectsCollection = $database->selectCollection('projects');
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    
    switch ($method) {
        case 'GET':
            if ($action === 'count') {
                handleCount($tasksCollection);
            } elseif ($action === 'list') {
                handleList($tasksCollection);
            } elseif ($action === 'recent') {
                handleRecent($tasksCollection);
            } elseif ($action === 'get' && isset($_GET['id'])) {
                handleGet($tasksCollection, $_GET['id']);
            } else {
                throw new Exception('Acción inválida o parámetros faltantes');
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                handleCreate($tasksCollection, $projectsCollection);
            } else {
                throw new Exception('Acción inválida');
            }
            break;
            
        case 'PUT':
            if ($action === 'update' && isset($_GET['id'])) {
                handleUpdate($tasksCollection, $_GET['id']);
            } else {
                throw new Exception('Acción inválida o parámetros faltantes');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete' && isset($_GET['id'])) {
                handleDelete($tasksCollection, $_GET['id']);
            } else {
                throw new Exception('Acción inválida o parámetros faltantes');
            }
            break;
            
        default:
            throw new Exception('Método no permitido');
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

function handleCount($tasksCollection) {
    $companyId = $_SESSION['company_id'] ?? null;
    
    // Get all tasks for the company
    $pipeline = [];
    
    if ($companyId) {
        $pipeline[] = [
            '$lookup' => [
                'from' => 'projects',
                'localField' => 'project_id',
                'foreignField' => '_id',
                'as' => 'project'
            ]
        ];
        
        $pipeline[] = [
            '$match' => [
                'project.company_id' => new MongoDB\BSON\ObjectId($companyId)
            ]
        ];
    }
    
    $pipeline[] = [
        '$group' => [
            '_id' => '$status',
            'count' => ['$sum' => 1]
        ]
    ];
    
    $cursor = $tasksCollection->aggregate($pipeline);
    $statusCounts = [];
    $totalCount = 0;
    
    foreach ($cursor as $result) {
        $status = $result['_id'];
        $count = $result['count'];
        $statusCounts[$status] = $count;
        $totalCount += $count;
    }
    
    $completedCount = $statusCounts['completed'] ?? 0;
    $completedPercentage = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 2) : 0;
    
    $response = [
        'success' => true,
        'count' => $totalCount,
        'status_counts' => $statusCounts,
        'completed_percentage' => $completedPercentage,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function handleList($tasksCollection) {
    $companyId = $_SESSION['company_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    $status = $_GET['status'] ?? null;
    $projectId = $_GET['project_id'] ?? null;
    
    $pipeline = [];
    
    // Match filters
    $matchConditions = [];
    if ($status) {
        $matchConditions['status'] = $status;
    }
    if ($projectId) {
        $matchConditions['project_id'] = new MongoDB\BSON\ObjectId($projectId);
    }
    
    if (!empty($matchConditions)) {
        $pipeline[] = ['$match' => $matchConditions];
    }
    
    // Lookup project info
    $pipeline[] = [
        '$lookup' => [
            'from' => 'projects',
            'localField' => 'project_id',
            'foreignField' => '_id',
            'as' => 'project'
        ]
    ];
    
    // Filter by company if specified
    if ($companyId) {
        $pipeline[] = [
            '$match' => [
                'project.company_id' => new MongoDB\BSON\ObjectId($companyId)
            ]
        ];
    }
    
    // Sort by created_at descending
    $pipeline[] = ['$sort' => ['created_at' => -1]];
    
    // Add pagination
    $pipeline[] = ['$skip' => $offset];
    $pipeline[] = ['$limit' => $limit];
    
    $cursor = $tasksCollection->aggregate($pipeline);
    $tasks = [];
    
    foreach ($cursor as $task) {
        $project = $task['project'][0] ?? null;
        
        $tasks[] = [
            'id' => (string)$task['_id'],
            'title' => $task['title'],
            'description' => $task['description'] ?? '',
            'status' => $task['status'],
            'priority' => $task['priority'] ?? 'medium',
            'due_date' => $task['due_date'] ? $task['due_date']->toDateTime()->format('Y-m-d') : null,
            'assigned_to' => $task['assigned_to'] ? (string)$task['assigned_to'] : null,
            'estimated_hours' => $task['estimated_hours'] ?? 0,
            'actual_hours' => $task['actual_hours'] ?? 0,
            'tags' => $task['tags'] ?? [],
            'project' => $project ? [
                'id' => (string)$project['_id'],
                'name' => $project['name']
            ] : null,
            'created_at' => $task['created_at']->toDateTime()->format('Y-m-d H:i:s'),
            'updated_at' => $task['updated_at']->toDateTime()->format('Y-m-d H:i:s'),
            'completed_at' => $task['completed_at'] ? $task['completed_at']->toDateTime()->format('Y-m-d H:i:s') : null
        ];
    }
    
    // Get total count for pagination
    $countPipeline = $pipeline;
    array_pop($countPipeline); // Remove limit
    array_pop($countPipeline); // Remove skip
    $countPipeline[] = ['$count' => 'total'];
    
    $countCursor = $tasksCollection->aggregate($countPipeline);
    $countResult = $countCursor->toArray();
    $total = $countResult[0]['total'] ?? 0;
    
    $response = [
        'success' => true,
        'tasks' => $tasks,
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

function handleRecent($tasksCollection) {
    $companyId = $_SESSION['company_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 5);
    
    $pipeline = [];
    
    // Lookup project info
    $pipeline[] = [
        '$lookup' => [
            'from' => 'projects',
            'localField' => 'project_id',
            'foreignField' => '_id',
            'as' => 'project'
        ]
    ];
    
    // Filter by company if specified
    if ($companyId) {
        $pipeline[] = [
            '$match' => [
                'project.company_id' => new MongoDB\BSON\ObjectId($companyId)
            ]
        ];
    }
    
    // Sort by updated_at descending
    $pipeline[] = ['$sort' => ['updated_at' => -1]];
    $pipeline[] = ['$limit' => $limit];
    
    $cursor = $tasksCollection->aggregate($pipeline);
    $tasks = [];
    
    foreach ($cursor as $task) {
        $project = $task['project'][0] ?? null;
        
        $tasks[] = [
            'id' => (string)$task['_id'],
            'title' => $task['title'],
            'description' => $task['description'] ?? '',
            'status' => $task['status'],
            'priority' => $task['priority'] ?? 'medium',
            'due_date' => $task['due_date'] ? $task['due_date']->toDateTime()->format('Y-m-d') : null,
            'project' => $project ? [
                'id' => (string)$project['_id'],
                'name' => $project['name']
            ] : null,
            'updated_at' => $task['updated_at']->toDateTime()->format('Y-m-d H:i:s')
        ];
    }
    
    $response = [
        'success' => true,
        'tasks' => $tasks,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function handleGet($tasksCollection, $taskId) {
    try {
        $pipeline = [
            ['$match' => ['_id' => new MongoDB\BSON\ObjectId($taskId)]],
            [
                '$lookup' => [
                    'from' => 'projects',
                    'localField' => 'project_id',
                    'foreignField' => '_id',
                    'as' => 'project'
                ]
            ]
        ];
        
        $cursor = $tasksCollection->aggregate($pipeline);
        $result = $cursor->toArray();
        
        if (empty($result)) {
            throw new Exception('Tarea no encontrada');
        }
        
        $task = $result[0];
        $project = $task['project'][0] ?? null;
        
        // Check if user has access to this task
        $companyId = $_SESSION['company_id'] ?? null;
        if ($companyId && $project && (string)$project['company_id'] !== $companyId) {
            throw new Exception('Access denied');
        }
        
        $response = [
            'success' => true,
            'task' => [
                'id' => (string)$task['_id'],
                'title' => $task['title'],
                'description' => $task['description'] ?? '',
                'status' => $task['status'],
                'priority' => $task['priority'] ?? 'medium',
                'due_date' => $task['due_date'] ? $task['due_date']->toDateTime()->format('Y-m-d') : null,
                'assigned_to' => $task['assigned_to'] ? (string)$task['assigned_to'] : null,
                'estimated_hours' => $task['estimated_hours'] ?? 0,
                'actual_hours' => $task['actual_hours'] ?? 0,
                'tags' => $task['tags'] ?? [],
                'project' => $project ? [
                    'id' => (string)$project['_id'],
                    'name' => $project['name']
                ] : null,
                'created_at' => $task['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                'updated_at' => $task['updated_at']->toDateTime()->format('Y-m-d H:i:s'),
                'completed_at' => $task['completed_at'] ? $task['completed_at']->toDateTime()->format('Y-m-d H:i:s') : null
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get task: ' . $e->getMessage());
    }
}

function handleCreate($tasksCollection, $projectsCollection) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['title']) || !isset($input['project_id'])) {
        throw new Exception('El título de la tarea y el ID del proyecto son requeridos');
    }
    
    // Verify project exists and user has access
    $project = $projectsCollection->findOne([
        '_id' => new MongoDB\BSON\ObjectId($input['project_id'])
    ]);
    
    if (!$project) {
        throw new Exception('Project not found');
    }
    
    $companyId = $_SESSION['company_id'] ?? null;
    if ($companyId && (string)$project['company_id'] !== $companyId) {
        throw new Exception('Access denied');
    }
    
    $taskData = [
        'title' => trim($input['title']),
        'description' => trim($input['description'] ?? ''),
        'project_id' => new MongoDB\BSON\ObjectId($input['project_id']),
        'status' => $input['status'] ?? 'pending',
        'priority' => $input['priority'] ?? 'medium',
        'due_date' => isset($input['due_date']) ? new MongoDB\BSON\UTCDateTime(strtotime($input['due_date']) * 1000) : null,
        'assigned_to' => isset($input['assigned_to']) ? new MongoDB\BSON\ObjectId($input['assigned_to']) : null,
        'estimated_hours' => (float)($input['estimated_hours'] ?? 0),
        'actual_hours' => (float)($input['actual_hours'] ?? 0),
        'tags' => $input['tags'] ?? [],
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
        'completed_at' => null
    ];
    
    $result = $tasksCollection->insertOne($taskData);
    
    $response = [
        'success' => true,
        'message' => 'Tarea creada exitosamente',
        'task_id' => (string)$result->getInsertedId(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(201);
    echo json_encode($response);
}

function handleUpdate($tasksCollection, $taskId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    try {
        $updateData = ['updated_at' => new MongoDB\BSON\UTCDateTime()];
        
        if (isset($input['title'])) {
            $updateData['title'] = trim($input['title']);
        }
        if (isset($input['description'])) {
            $updateData['description'] = trim($input['description']);
        }
        if (isset($input['status'])) {
            $updateData['status'] = $input['status'];
            
            // Set completed_at when status changes to completed
            if ($input['status'] === 'completed') {
                $updateData['completed_at'] = new MongoDB\BSON\UTCDateTime();
            } elseif ($input['status'] !== 'completed') {
                $updateData['completed_at'] = null;
            }
        }
        if (isset($input['priority'])) {
            $updateData['priority'] = $input['priority'];
        }
        if (isset($input['due_date'])) {
            $updateData['due_date'] = new MongoDB\BSON\UTCDateTime(strtotime($input['due_date']) * 1000);
        }
        if (isset($input['assigned_to'])) {
            $updateData['assigned_to'] = $input['assigned_to'] ? new MongoDB\BSON\ObjectId($input['assigned_to']) : null;
        }
        if (isset($input['estimated_hours'])) {
            $updateData['estimated_hours'] = (float)$input['estimated_hours'];
        }
        if (isset($input['actual_hours'])) {
            $updateData['actual_hours'] = (float)$input['actual_hours'];
        }
        if (isset($input['tags'])) {
            $updateData['tags'] = $input['tags'];
        }
        
        $result = $tasksCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($taskId)],
            ['$set' => $updateData]
        );
        
        if ($result->getMatchedCount() === 0) {
            throw new Exception('Task not found');
        }
        
        $response = [
            'success' => true,
            'message' => 'Tarea actualizada exitosamente',
            'modified_count' => $result->getModifiedCount(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update task: ' . $e->getMessage());
    }
}

function handleDelete($tasksCollection, $taskId) {
    try {
        $result = $tasksCollection->deleteOne([
            '_id' => new MongoDB\BSON\ObjectId($taskId)
        ]);
        
        if ($result->getDeletedCount() === 0) {
            throw new Exception('Task not found');
        }
        
        $response = [
            'success' => true,
            'message' => 'Tarea eliminada exitosamente',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to delete task: ' . $e->getMessage());
    }
}
?>