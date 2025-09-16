<?php
/**
 * Users API
 * Handles user management operations
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
    $usersCollection = $database->selectCollection('users');
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    
    switch ($method) {
        case 'GET':
            if ($action === 'count') {
                handleCount($usersCollection);
            } elseif ($action === 'list') {
                handleList($usersCollection);
            } elseif ($action === 'get' && isset($_GET['id'])) {
                handleGet($usersCollection, $_GET['id']);
            } elseif ($action === 'profile') {
                handleProfile($usersCollection);
            } else {
                throw new Exception('Invalid action or missing parameters');
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                handleCreate($usersCollection);
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'PUT':
            if ($action === 'update' && isset($_GET['id'])) {
                handleUpdate($usersCollection, $_GET['id']);
            } elseif ($action === 'profile') {
                handleUpdateProfile($usersCollection);
            } else {
                throw new Exception('Invalid action or missing parameters');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete' && isset($_GET['id'])) {
                handleDelete($usersCollection, $_GET['id']);
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

function handleCount($usersCollection) {
    $companyId = $_SESSION['company_id'] ?? null;
    
    $filter = ['status' => 'active'];
    if ($companyId) {
        $filter['company_id'] = new MongoDB\BSON\ObjectId($companyId);
    }
    
    $count = $usersCollection->countDocuments($filter);
    
    $response = [
        'success' => true,
        'count' => $count,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(200);
    echo json_encode($response);
}

function handleList($usersCollection) {
    $companyId = $_SESSION['company_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    $role = $_GET['role'] ?? null;
    
    $filter = ['status' => 'active'];
    if ($companyId) {
        $filter['company_id'] = new MongoDB\BSON\ObjectId($companyId);
    }
    if ($role) {
        $filter['role'] = $role;
    }
    
    $options = [
        'limit' => $limit,
        'skip' => $offset,
        'sort' => ['created_at' => -1],
        'projection' => [
            'password' => 0 // Exclude password from results
        ]
    ];
    
    $cursor = $usersCollection->find($filter, $options);
    $users = [];
    
    foreach ($cursor as $user) {
        $users[] = [
            'id' => (string)$user['_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
            'status' => $user['status'],
            'last_login' => $user['last_login'] ? $user['last_login']->toDateTime()->format('Y-m-d H:i:s') : null,
            'created_at' => $user['created_at']->toDateTime()->format('Y-m-d H:i:s'),
            'permissions' => $user['permissions'] ?? []
        ];
    }
    
    $total = $usersCollection->countDocuments($filter);
    
    $response = [
        'success' => true,
        'users' => $users,
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

function handleGet($usersCollection, $userId) {
    try {
        $user = $usersCollection->findOne([
            '_id' => new MongoDB\BSON\ObjectId($userId)
        ], [
            'projection' => ['password' => 0]
        ]);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Check if user has access to this user data
        $companyId = $_SESSION['company_id'] ?? null;
        $currentUserId = $_SESSION['user_id'] ?? null;
        $currentUserRole = $_SESSION['role'] ?? null;
        
        // Users can view their own profile, or admins/managers can view company users
        if ($currentUserId !== $userId && 
            !in_array($currentUserRole, ['admin', 'manager']) &&
            $companyId && (string)$user['company_id'] !== $companyId) {
            throw new Exception('Access denied');
        }
        
        $response = [
            'success' => true,
            'user' => [
                'id' => (string)$user['_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role'],
                'status' => $user['status'],
                'last_login' => $user['last_login'] ? $user['last_login']->toDateTime()->format('Y-m-d H:i:s') : null,
                'created_at' => $user['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                'updated_at' => $user['updated_at']->toDateTime()->format('Y-m-d H:i:s'),
                'permissions' => $user['permissions'] ?? []
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get user: ' . $e->getMessage());
    }
}

function handleProfile($usersCollection) {
    try {
        $currentUserId = $_SESSION['user_id'];
        
        $user = $usersCollection->findOne([
            '_id' => new MongoDB\BSON\ObjectId($currentUserId)
        ], [
            'projection' => ['password' => 0]
        ]);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $response = [
            'success' => true,
            'user' => [
                'id' => (string)$user['_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role'],
                'status' => $user['status'],
                'last_login' => $user['last_login'] ? $user['last_login']->toDateTime()->format('Y-m-d H:i:s') : null,
                'created_at' => $user['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                'updated_at' => $user['updated_at']->toDateTime()->format('Y-m-d H:i:s'),
                'permissions' => $user['permissions'] ?? []
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get profile: ' . $e->getMessage());
    }
}

function handleCreate($usersCollection) {
    // Check if current user has permission to create users
    $currentUserRole = $_SESSION['role'] ?? null;
    if (!in_array($currentUserRole, ['admin', 'manager'])) {
        throw new Exception('Insufficient permissions');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['email']) || !isset($input['password'])) {
        throw new Exception('Username, email, and password are required');
    }
    
    $companyId = $_SESSION['company_id'] ?? null;
    if (!$companyId) {
        throw new Exception('Company ID is required');
    }
    
    // Check if username or email already exists
    $existingUser = $usersCollection->findOne([
        '$or' => [
            ['username' => trim($input['username'])],
            ['email' => trim($input['email'])]
        ]
    ]);
    
    if ($existingUser) {
        throw new Exception('Username or email already exists');
    }
    
    $userData = [
        'username' => trim($input['username']),
        'email' => trim($input['email']),
        'password' => password_hash($input['password'], PASSWORD_BCRYPT),
        'first_name' => trim($input['first_name'] ?? ''),
        'last_name' => trim($input['last_name'] ?? ''),
        'role' => $input['role'] ?? 'user',
        'company_id' => new MongoDB\BSON\ObjectId($companyId),
        'status' => $input['status'] ?? 'active',
        'permissions' => $input['permissions'] ?? [],
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
        'last_login' => null
    ];
    
    $result = $usersCollection->insertOne($userData);
    
    $response = [
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => (string)$result->getInsertedId(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(201);
    echo json_encode($response);
}

function handleUpdate($usersCollection, $userId) {
    // Check if current user has permission to update users
    $currentUserRole = $_SESSION['role'] ?? null;
    $currentUserId = $_SESSION['user_id'] ?? null;
    
    if ($currentUserId !== $userId && !in_array($currentUserRole, ['admin', 'manager'])) {
        throw new Exception('Insufficient permissions');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    try {
        $updateData = ['updated_at' => new MongoDB\BSON\UTCDateTime()];
        
        if (isset($input['username'])) {
            // Check if username is already taken by another user
            $existingUser = $usersCollection->findOne([
                'username' => trim($input['username']),
                '_id' => ['$ne' => new MongoDB\BSON\ObjectId($userId)]
            ]);
            
            if ($existingUser) {
                throw new Exception('Username already exists');
            }
            
            $updateData['username'] = trim($input['username']);
        }
        
        if (isset($input['email'])) {
            // Check if email is already taken by another user
            $existingUser = $usersCollection->findOne([
                'email' => trim($input['email']),
                '_id' => ['$ne' => new MongoDB\BSON\ObjectId($userId)]
            ]);
            
            if ($existingUser) {
                throw new Exception('Email already exists');
            }
            
            $updateData['email'] = trim($input['email']);
        }
        
        if (isset($input['password']) && !empty($input['password'])) {
            $updateData['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
        }
        
        if (isset($input['first_name'])) {
            $updateData['first_name'] = trim($input['first_name']);
        }
        
        if (isset($input['last_name'])) {
            $updateData['last_name'] = trim($input['last_name']);
        }
        
        // Only admins can change roles and permissions
        if (in_array($currentUserRole, ['admin']) && isset($input['role'])) {
            $updateData['role'] = $input['role'];
        }
        
        if (in_array($currentUserRole, ['admin']) && isset($input['permissions'])) {
            $updateData['permissions'] = $input['permissions'];
        }
        
        if (in_array($currentUserRole, ['admin']) && isset($input['status'])) {
            $updateData['status'] = $input['status'];
        }
        
        $result = $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => $updateData]
        );
        
        if ($result->getMatchedCount() === 0) {
            throw new Exception('User not found');
        }
        
        $response = [
            'success' => true,
            'message' => 'User updated successfully',
            'modified_count' => $result->getModifiedCount(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update user: ' . $e->getMessage());
    }
}

function handleUpdateProfile($usersCollection) {
    $currentUserId = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    try {
        $updateData = ['updated_at' => new MongoDB\BSON\UTCDateTime()];
        
        if (isset($input['first_name'])) {
            $updateData['first_name'] = trim($input['first_name']);
        }
        
        if (isset($input['last_name'])) {
            $updateData['last_name'] = trim($input['last_name']);
        }
        
        if (isset($input['email'])) {
            // Check if email is already taken by another user
            $existingUser = $usersCollection->findOne([
                'email' => trim($input['email']),
                '_id' => ['$ne' => new MongoDB\BSON\ObjectId($currentUserId)]
            ]);
            
            if ($existingUser) {
                throw new Exception('Email already exists');
            }
            
            $updateData['email'] = trim($input['email']);
        }
        
        if (isset($input['password']) && !empty($input['password'])) {
            $updateData['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
        }
        
        $result = $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($currentUserId)],
            ['$set' => $updateData]
        );
        
        if ($result->getMatchedCount() === 0) {
            throw new Exception('User not found');
        }
        
        $response = [
            'success' => true,
            'message' => 'Profile updated successfully',
            'modified_count' => $result->getModifiedCount(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update profile: ' . $e->getMessage());
    }
}

function handleDelete($usersCollection, $userId) {
    // Check if current user has permission to delete users
    $currentUserRole = $_SESSION['role'] ?? null;
    $currentUserId = $_SESSION['user_id'] ?? null;
    
    if (!in_array($currentUserRole, ['admin'])) {
        throw new Exception('Insufficient permissions');
    }
    
    // Prevent self-deletion
    if ($currentUserId === $userId) {
        throw new Exception('Cannot delete your own account');
    }
    
    try {
        // Soft delete by setting status to inactive
        $result = $usersCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => [
                'status' => 'inactive',
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        
        if ($result->getMatchedCount() === 0) {
            throw new Exception('User not found');
        }
        
        $response = [
            'success' => true,
            'message' => 'User deactivated successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(200);
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Failed to delete user: ' . $e->getMessage());
    }
}
?>