<?php
/**
 * Daily Expression API
 * Manages the daily motivational expression
 */

require_once '../../config/mongodb.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

try {
    $db = MongoDBConnection::getInstance()->getDatabase();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get today's expression
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        $expression = $db->system_config->findOne([
            'key' => 'daily_expression',
            'date' => [
                '$gte' => new MongoDB\BSON\UTCDateTime($today->getTimestamp() * 1000)
            ]
        ]);
        
        if ($expression) {
            echo json_encode([
                'success' => true,
                'expression' => [
                    'text' => $expression['value']['text'],
                    'author' => $expression['value']['author'],
                    'date' => $expression['date']
                ]
            ]);
        } else {
            // Default expression if none set
            echo json_encode([
                'success' => true,
                'expression' => [
                    'text' => '¡Que tengas un día productivo y exitoso!',
                    'author' => 'BIG PLAN Team',
                    'date' => new DateTime()
                ]
            ]);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Set daily expression (admin only)
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            echo json_encode([
                'success' => false,
                'message' => 'No autorizado'
            ]);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['text']) || empty(trim($input['text']))) {
            echo json_encode([
                'success' => false,
                'message' => 'Texto de expresión requerido'
            ]);
            exit;
        }
        
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        // Update or insert today's expression
        $result = $db->system_config->updateOne(
            [
                'key' => 'daily_expression',
                'date' => [
                    '$gte' => new MongoDB\BSON\UTCDateTime($today->getTimestamp() * 1000),
                    '$lt' => new MongoDB\BSON\UTCDateTime(($today->getTimestamp() + 86400) * 1000)
                ]
            ],
            [
                '$set' => [
                    'value' => [
                        'text' => trim($input['text']),
                        'author' => $_SESSION['user_name'] ?? 'Administrador'
                    ],
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ],
            ['upsert' => true]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Expresión diaria actualizada correctamente'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>