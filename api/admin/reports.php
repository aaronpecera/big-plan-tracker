<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/UserManager.php';
require_once __DIR__ . '/../../classes/TaskManager.php';
require_once __DIR__ . '/../../classes/CompanyManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Verificar autenticación y permisos de administrador
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$userManager = new UserManager();
$user = $userManager->getUserById($_SESSION['user_id']);

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$taskManager = new TaskManager();
$companyManager = new CompanyManager();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $reportType = $_GET['type'] ?? 'general';
        $companyId = $_GET['company_id'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $userId = $_GET['user_id'] ?? null;

        // Construir filtros
        $filters = [];
        
        if ($companyId) {
            $filters['company_id'] = new MongoDB\BSON\ObjectId($companyId);
        }
        
        if ($startDate && $endDate) {
            $filters['created_at'] = [
                '$gte' => new MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000),
                '$lte' => new MongoDB\BSON\UTCDateTime(strtotime($endDate . ' 23:59:59') * 1000)
            ];
        }
        
        if ($userId) {
            $filters['assigned_to'] = new MongoDB\BSON\ObjectId($userId);
        }

        switch ($reportType) {
            case 'general':
                $report = generateGeneralReport($taskManager, $companyManager, $filters);
                break;
            case 'company':
                $report = generateCompanyReport($taskManager, $companyManager, $filters);
                break;
            case 'user':
                $report = generateUserReport($taskManager, $userManager, $filters);
                break;
            case 'time':
                $report = generateTimeReport($taskManager, $filters);
                break;
            case 'cost':
                $report = generateCostReport($taskManager, $companyManager, $filters);
                break;
            default:
                throw new Exception('Tipo de reporte no válido');
        }

        echo json_encode([
            'success' => true,
            'report' => $report,
            'generated_at' => date('Y-m-d H:i:s'),
            'filters' => $filters
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al generar reporte: ' . $e->getMessage()]);
    }
}

function generateGeneralReport($taskManager, $companyManager, $filters) {
    $tasks = $taskManager->getTasksWithFilters($filters);
    $companies = $companyManager->getAllCompanies();
    
    $report = [
        'summary' => [
            'total_tasks' => count($tasks),
            'completed_tasks' => 0,
            'in_progress_tasks' => 0,
            'pending_tasks' => 0,
            'total_time_spent' => 0,
            'total_cost' => 0
        ],
        'by_company' => [],
        'by_status' => [],
        'by_user' => []
    ];
    
    $companyStats = [];
    $userStats = [];
    
    foreach ($tasks as $task) {
        // Estadísticas por estado
        $status = $task['status'];
        $report['summary'][$status . '_tasks']++;
        
        // Tiempo y costo
        $timeSpent = $task['time_spent'] ?? 0;
        $report['summary']['total_time_spent'] += $timeSpent;
        
        // Obtener costo por hora de la empresa
        $company = $companyManager->getCompanyById($task['company_id']);
        $hourlyRate = $company['hourly_rate'] ?? 0;
        $taskCost = ($timeSpent / 3600) * $hourlyRate;
        $report['summary']['total_cost'] += $taskCost;
        
        // Estadísticas por empresa
        $companyId = (string)$task['company_id'];
        if (!isset($companyStats[$companyId])) {
            $companyStats[$companyId] = [
                'company_name' => $company['name'],
                'task_count' => 0,
                'total_time' => 0,
                'total_cost' => 0
            ];
        }
        $companyStats[$companyId]['task_count']++;
        $companyStats[$companyId]['total_time'] += $timeSpent;
        $companyStats[$companyId]['total_cost'] += $taskCost;
        
        // Estadísticas por usuario
        $userId = (string)$task['assigned_to'];
        if (!isset($userStats[$userId])) {
            $userStats[$userId] = [
                'user_name' => $task['assigned_user_name'] ?? 'Usuario desconocido',
                'task_count' => 0,
                'total_time' => 0,
                'total_cost' => 0
            ];
        }
        $userStats[$userId]['task_count']++;
        $userStats[$userId]['total_time'] += $timeSpent;
        $userStats[$userId]['total_cost'] += $taskCost;
    }
    
    $report['by_company'] = array_values($companyStats);
    $report['by_user'] = array_values($userStats);
    
    return $report;
}

function generateCompanyReport($taskManager, $companyManager, $filters) {
    $tasks = $taskManager->getTasksWithFilters($filters);
    $companies = $companyManager->getAllCompanies();
    
    $report = [];
    
    foreach ($companies as $company) {
        $companyTasks = array_filter($tasks, function($task) use ($company) {
            return (string)$task['company_id'] === (string)$company['_id'];
        });
        
        $totalTime = 0;
        $totalCost = 0;
        $statusCount = ['pending' => 0, 'in_progress' => 0, 'paused' => 0, 'completed' => 0];
        
        foreach ($companyTasks as $task) {
            $timeSpent = $task['time_spent'] ?? 0;
            $totalTime += $timeSpent;
            $totalCost += ($timeSpent / 3600) * $company['hourly_rate'];
            $statusCount[$task['status']]++;
        }
        
        $report[] = [
            'company_id' => (string)$company['_id'],
            'company_name' => $company['name'],
            'hourly_rate' => $company['hourly_rate'],
            'total_tasks' => count($companyTasks),
            'total_time' => $totalTime,
            'total_cost' => $totalCost,
            'status_breakdown' => $statusCount,
            'tasks' => array_map(function($task) {
                return [
                    'id' => (string)$task['_id'],
                    'title' => $task['title'],
                    'status' => $task['status'],
                    'assigned_to' => $task['assigned_user_name'] ?? 'No asignado',
                    'time_spent' => $task['time_spent'] ?? 0,
                    'created_at' => $task['created_at']->toDateTime()->format('Y-m-d H:i:s')
                ];
            }, $companyTasks)
        ];
    }
    
    return $report;
}

function generateUserReport($taskManager, $userManager, $filters) {
    $tasks = $taskManager->getTasksWithFilters($filters);
    $users = $userManager->getAllUsers();
    
    $report = [];
    
    foreach ($users as $user) {
        if ($user['role'] === 'admin') continue;
        
        $userTasks = array_filter($tasks, function($task) use ($user) {
            return (string)$task['assigned_to'] === (string)$user['_id'];
        });
        
        $totalTime = 0;
        $statusCount = ['pending' => 0, 'in_progress' => 0, 'paused' => 0, 'completed' => 0];
        
        foreach ($userTasks as $task) {
            $totalTime += $task['time_spent'] ?? 0;
            $statusCount[$task['status']]++;
        }
        
        $report[] = [
            'user_id' => (string)$user['_id'],
            'user_name' => $user['name'],
            'email' => $user['email'],
            'total_tasks' => count($userTasks),
            'total_time' => $totalTime,
            'status_breakdown' => $statusCount,
            'productivity_score' => calculateProductivityScore($userTasks),
            'recent_tasks' => array_slice(array_map(function($task) {
                return [
                    'id' => (string)$task['_id'],
                    'title' => $task['title'],
                    'status' => $task['status'],
                    'company_name' => $task['company_name'] ?? 'Empresa desconocida',
                    'time_spent' => $task['time_spent'] ?? 0
                ];
            }, $userTasks), 0, 10)
        ];
    }
    
    return $report;
}

function generateTimeReport($taskManager, $filters) {
    $tasks = $taskManager->getTasksWithFilters($filters);
    
    $report = [
        'daily_breakdown' => [],
        'weekly_breakdown' => [],
        'monthly_breakdown' => [],
        'average_task_time' => 0,
        'most_time_consuming_tasks' => []
    ];
    
    $dailyTime = [];
    $totalTime = 0;
    
    foreach ($tasks as $task) {
        $timeSpent = $task['time_spent'] ?? 0;
        $totalTime += $timeSpent;
        
        $date = $task['created_at']->toDateTime()->format('Y-m-d');
        if (!isset($dailyTime[$date])) {
            $dailyTime[$date] = 0;
        }
        $dailyTime[$date] += $timeSpent;
    }
    
    $report['daily_breakdown'] = $dailyTime;
    $report['average_task_time'] = count($tasks) > 0 ? $totalTime / count($tasks) : 0;
    
    // Tareas que más tiempo consumen
    usort($tasks, function($a, $b) {
        return ($b['time_spent'] ?? 0) - ($a['time_spent'] ?? 0);
    });
    
    $report['most_time_consuming_tasks'] = array_slice(array_map(function($task) {
        return [
            'id' => (string)$task['_id'],
            'title' => $task['title'],
            'time_spent' => $task['time_spent'] ?? 0,
            'company_name' => $task['company_name'] ?? 'Empresa desconocida'
        ];
    }, $tasks), 0, 10);
    
    return $report;
}

function generateCostReport($taskManager, $companyManager, $filters) {
    $tasks = $taskManager->getTasksWithFilters($filters);
    
    $report = [
        'total_cost' => 0,
        'cost_by_company' => [],
        'cost_by_month' => [],
        'most_expensive_tasks' => []
    ];
    
    $companyCosts = [];
    $monthlyCosts = [];
    $taskCosts = [];
    
    foreach ($tasks as $task) {
        $company = $companyManager->getCompanyById($task['company_id']);
        $hourlyRate = $company['hourly_rate'] ?? 0;
        $timeSpent = $task['time_spent'] ?? 0;
        $taskCost = ($timeSpent / 3600) * $hourlyRate;
        
        $report['total_cost'] += $taskCost;
        
        // Costo por empresa
        $companyName = $company['name'];
        if (!isset($companyCosts[$companyName])) {
            $companyCosts[$companyName] = 0;
        }
        $companyCosts[$companyName] += $taskCost;
        
        // Costo por mes
        $month = $task['created_at']->toDateTime()->format('Y-m');
        if (!isset($monthlyCosts[$month])) {
            $monthlyCosts[$month] = 0;
        }
        $monthlyCosts[$month] += $taskCost;
        
        // Guardar costo de tarea para ranking
        $taskCosts[] = [
            'id' => (string)$task['_id'],
            'title' => $task['title'],
            'cost' => $taskCost,
            'company_name' => $companyName
        ];
    }
    
    $report['cost_by_company'] = $companyCosts;
    $report['cost_by_month'] = $monthlyCosts;
    
    // Tareas más costosas
    usort($taskCosts, function($a, $b) {
        return $b['cost'] - $a['cost'];
    });
    $report['most_expensive_tasks'] = array_slice($taskCosts, 0, 10);
    
    return $report;
}

function calculateProductivityScore($tasks) {
    if (empty($tasks)) return 0;
    
    $completedTasks = array_filter($tasks, function($task) {
        return $task['status'] === 'completed';
    });
    
    $completionRate = count($completedTasks) / count($tasks);
    
    // Calcular tiempo promedio por tarea completada
    $totalTime = 0;
    foreach ($completedTasks as $task) {
        $totalTime += $task['time_spent'] ?? 0;
    }
    
    $avgTimePerTask = count($completedTasks) > 0 ? $totalTime / count($completedTasks) : 0;
    
    // Score basado en tasa de completación y eficiencia de tiempo
    $score = ($completionRate * 70) + (min($avgTimePerTask / 3600, 8) / 8 * 30);
    
    return round($score, 2);
}
?>