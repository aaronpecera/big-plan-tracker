<?php
/**
 * MongoDB Database Initialization Script
 * Converts MySQL schema to MongoDB collections with sample data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

use App\Config\DatabaseConnection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

try {
    $db = DatabaseConnection::getInstance();
    $database = $db->getDatabase();
    
    // Initialize collections with indexes
    $db->initializeCollections();
    
    $response = [
        'success' => true,
        'message' => 'Database initialization started',
        'collections_created' => [],
        'sample_data_inserted' => []
    ];
    
    // Create sample company
    $companiesCollection = $database->selectCollection('companies');
    $existingCompany = $companiesCollection->findOne(['name' => 'Demo Company']);
    
    if (!$existingCompany) {
        $companyId = new ObjectId();
        $companyData = [
            '_id' => $companyId,
            'name' => 'Demo Company',
            'description' => 'Sample company for demonstration purposes',
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'status' => 'active',
            'settings' => [
                'timezone' => 'UTC',
                'currency' => 'USD',
                'date_format' => 'Y-m-d'
            ]
        ];
        
        $companiesCollection->insertOne($companyData);
        $response['sample_data_inserted'][] = 'Demo Company created';
    } else {
        $companyId = $existingCompany['_id'];
        $response['sample_data_inserted'][] = 'Demo Company already exists';
    }
    
    // Create sample users
    $usersCollection = $database->selectCollection('users');
    
    $sampleUsers = [
        [
            'username' => 'admin',
            'email' => 'admin@demo.com',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'role' => 'admin',
            'company_id' => $companyId,
            'status' => 'active',
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'last_login' => null,
            'permissions' => [
                'users.manage',
                'companies.manage',
                'projects.manage',
                'tasks.manage',
                'reports.view'
            ]
        ],
        [
            'username' => 'user',
            'email' => 'user@demo.com',
            'password' => password_hash('user123', PASSWORD_BCRYPT),
            'first_name' => 'Demo',
            'last_name' => 'User',
            'role' => 'user',
            'company_id' => $companyId,
            'status' => 'active',
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'last_login' => null,
            'permissions' => [
                'projects.view',
                'tasks.manage',
                'profile.edit'
            ]
        ],
        [
            'username' => 'manager',
            'email' => 'manager@demo.com',
            'password' => password_hash('manager123', PASSWORD_BCRYPT),
            'first_name' => 'Project',
            'last_name' => 'Manager',
            'role' => 'manager',
            'company_id' => $companyId,
            'status' => 'active',
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'last_login' => null,
            'permissions' => [
                'projects.manage',
                'tasks.manage',
                'users.view',
                'reports.view'
            ]
        ]
    ];
    
    foreach ($sampleUsers as $userData) {
        $existingUser = $usersCollection->findOne(['username' => $userData['username']]);
        if (!$existingUser) {
            $usersCollection->insertOne($userData);
            $response['sample_data_inserted'][] = "User '{$userData['username']}' created";
        } else {
            $response['sample_data_inserted'][] = "User '{$userData['username']}' already exists";
        }
    }
    
    // Create sample project
    $projectsCollection = $database->selectCollection('projects');
    $existingProject = $projectsCollection->findOne(['name' => 'Demo Project']);
    
    if (!$existingProject) {
        $projectId = new ObjectId();
        $projectData = [
            '_id' => $projectId,
            'name' => 'Demo Project',
            'description' => 'Sample project for demonstration purposes',
            'company_id' => $companyId,
            'status' => 'active',
            'priority' => 'medium',
            'start_date' => new UTCDateTime(),
            'end_date' => new UTCDateTime(strtotime('+30 days') * 1000),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'budget' => 10000,
            'progress' => 25,
            'team_members' => [],
            'tags' => ['demo', 'sample', 'project']
        ];
        
        $projectsCollection->insertOne($projectData);
        $response['sample_data_inserted'][] = 'Demo Project created';
    } else {
        $projectId = $existingProject['_id'];
        $response['sample_data_inserted'][] = 'Demo Project already exists';
    }
    
    // Create sample tasks
    $tasksCollection = $database->selectCollection('tasks');
    
    $sampleTasks = [
        [
            'title' => 'Setup Development Environment',
            'description' => 'Configure development tools and environment',
            'project_id' => $projectId,
            'assigned_to' => null,
            'status' => 'completed',
            'priority' => 'high',
            'due_date' => new UTCDateTime(strtotime('+7 days') * 1000),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'completed_at' => new UTCDateTime(),
            'estimated_hours' => 8,
            'actual_hours' => 6,
            'tags' => ['setup', 'development']
        ],
        [
            'title' => 'Design User Interface',
            'description' => 'Create mockups and design system',
            'project_id' => $projectId,
            'assigned_to' => null,
            'status' => 'in_progress',
            'priority' => 'medium',
            'due_date' => new UTCDateTime(strtotime('+14 days') * 1000),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'completed_at' => null,
            'estimated_hours' => 16,
            'actual_hours' => 8,
            'tags' => ['design', 'ui']
        ],
        [
            'title' => 'Implement Authentication',
            'description' => 'Build login and user management system',
            'project_id' => $projectId,
            'assigned_to' => null,
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => new UTCDateTime(strtotime('+21 days') * 1000),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'completed_at' => null,
            'estimated_hours' => 12,
            'actual_hours' => 0,
            'tags' => ['backend', 'security']
        ]
    ];
    
    foreach ($sampleTasks as $taskData) {
        $existingTask = $tasksCollection->findOne(['title' => $taskData['title']]);
        if (!$existingTask) {
            $tasksCollection->insertOne($taskData);
            $response['sample_data_inserted'][] = "Task '{$taskData['title']}' created";
        } else {
            $response['sample_data_inserted'][] = "Task '{$taskData['title']}' already exists";
        }
    }
    
    // Create sample activities
    $activitiesCollection = $database->selectCollection('activities');
    $existingActivity = $activitiesCollection->findOne(['action' => 'database_initialized']);
    
    if (!$existingActivity) {
        $activityData = [
            'user_id' => null,
            'action' => 'database_initialized',
            'description' => 'Database initialized with sample data',
            'entity_type' => 'system',
            'entity_id' => null,
            'metadata' => [
                'collections_created' => ['users', 'companies', 'projects', 'tasks', 'activities'],
                'sample_data' => true
            ],
            'created_at' => new UTCDateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $activitiesCollection->insertOne($activityData);
        $response['sample_data_inserted'][] = 'System activity logged';
    }
    
    // Get collection statistics
    $collections = ['users', 'companies', 'projects', 'tasks', 'activities'];
    $stats = [];
    
    foreach ($collections as $collectionName) {
        $collection = $database->selectCollection($collectionName);
        $count = $collection->countDocuments();
        $stats[$collectionName] = $count;
        $response['collections_created'][] = "$collectionName ($count documents)";
    }
    
    $response['statistics'] = $stats;
    $response['message'] = 'Database initialization completed successfully';
    $response['timestamp'] = date('Y-m-d H:i:s');
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ];
    
    http_response_code(500);
    echo json_encode($response, JSON_PRETTY_PRINT);
}
?>