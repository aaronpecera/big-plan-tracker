<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../middleware/auth.php';
require_once '../config/database.php';

use MongoDB\BSON\ObjectId;

// Verificar autenticación de administrador
requireAdmin();

try {
    $db = MongoDBConfig::getInstance();
    $database = $db->getDatabase();
    $activitiesCollection = $database->selectCollection('activities');
    $usersCollection = $database->selectCollection('users');
    $tasksCollection = $database->selectCollection('tasks');

    // Obtener actividades recientes (últimas 20)
    $cursor = $activitiesCollection->find(
        [],
        [
            'sort' => ['timestamp' => -1],
            'limit' => 20
        ]
    );
    $activities = iterator_to_array($cursor);

    $enrichedActivities = [];

    foreach ($activities as $activity) {
        $enrichedActivity = [
            'id' => (string)$activity['_id'],
            'description' => $activity['description'] ?? 'Actividad sin descripción',
            'timestamp' => $activity['timestamp']->toDateTime()->format('Y-m-d H:i:s'),
            'type' => $activity['type'] ?? 'general',
            'user_id' => $activity['user_id'] ?? null,
            'task_id' => $activity['task_id'] ?? null
        ];

        // Enriquecer con información del usuario
        if (isset($activity['user_id'])) {
            $user = $usersCollection->findOne(['_id' => new ObjectId($activity['user_id'])]);
            if ($user) {
                $enrichedActivity['user_name'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
                $enrichedActivity['user_email'] = $user['email'] ?? '';
            }
        }

        // Enriquecer con información de la tarea
        if (isset($activity['task_id'])) {
            $task = $tasksCollection->findOne(['_id' => new ObjectId($activity['task_id'])]);
            if ($task) {
                $enrichedActivity['task_title'] = $task['title'] ?? '';
                $enrichedActivity['task_status'] = $task['status'] ?? '';
            }
        }

        $enrichedActivities[] = $enrichedActivity;
    }

    echo json_encode([
        'success' => true,
        'activities' => $enrichedActivities,
        'total' => count($enrichedActivities),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener actividad reciente',
        'error' => $e->getMessage()
    ]);
}
?>