<?php
/**
 * Start Task API
 * Starts or resumes a task and begins time tracking
 */

require_once '../../config/mongodb.php';
require_once '../../classes/TaskManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado'
        ]);
        exit;
    }
    
    // Get task ID from URL
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    $pathParts = explode('/', trim($pathInfo, '/'));
    
    if (count($pathParts) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de tarea requerido'
        ]);
        exit;
    }
    
    $taskId = $pathParts[0];
    $action = $pathParts[1];
    
    if ($action !== 'start' && $action !== 'resume') {
        echo json_encode([
            'success' => false,
            'message' => 'Acción no válida'
        ]);
        exit;
    }
    
    $taskManager = new TaskManager();
    
    // Start or resume the task
    $result = $taskManager->startTask($taskId, $_SESSION['user_id']);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => $action === 'start' ? 'Tarea iniciada correctamente' : 'Tarea reanudada correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al iniciar la tarea'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>