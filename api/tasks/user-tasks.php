<?php
/**
 * User Tasks API
 * Returns tasks assigned to the current user
 */

require_once '../../config/mongodb.php';
require_once '../../classes/TaskManager.php';
require_once '../../classes/CompanyManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado'
        ]);
        exit;
    }
    
    $taskManager = new TaskManager();
    $companyManager = new CompanyManager();
    
    // Get user's tasks
    $tasks = $taskManager->getUserTasks($_SESSION['user_id']);
    
    // Get companies for the user
    $companies = $companyManager->getUserCompanies($_SESSION['user_id']);
    
    // Format tasks for frontend
    $formattedTasks = [];
    foreach ($tasks as $task) {
        $formattedTasks[] = [
            '_id' => (string)$task['_id'],
            'title' => $task['title'],
            'description' => $task['description'] ?? '',
            'status' => $task['status'],
            'company_id' => (string)$task['company_id'],
            'assigned_to' => $task['assigned_to'],
            'due_date' => $task['due_date'] ?? null,
            'estimated_hours' => $task['estimated_hours'] ?? null,
            'created_at' => $task['created_at'],
            'updated_at' => $task['updated_at']
        ];
    }
    
    // Format companies for frontend
    $formattedCompanies = [];
    foreach ($companies as $company) {
        $formattedCompanies[] = [
            '_id' => (string)$company['_id'],
            'name' => $company['name'],
            'cost_per_hour' => $company['cost_per_hour']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tasks' => $formattedTasks,
        'companies' => $formattedCompanies
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>