<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../middleware/auth.php';
require_once '../config/database.php';

use MongoDB\BSON\ObjectId;

class NotificationSystem {
    private $db;
    private $database;

    public function __construct() {
        $this->db = MongoDBConfig::getInstance();
        $this->database = $this->db->getDatabase();
    }

    public function createNotification($userId, $title, $message, $type = 'info', $taskId = null, $priority = 'medium') {
        $notificationsCollection = $this->database->selectCollection('notifications');
        
        $notification = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'task_id' => $taskId,
            'priority' => $priority,
            'read' => false,
            'created_at' => new DateTime(),
            'read_at' => null,
            'expires_at' => (new DateTime())->add(new DateInterval('P30D'))
        ];

        $result = $notificationsCollection->insertOne($notification);
        
        // Enviar notificación push si el usuario tiene habilitadas las notificaciones
        $this->sendPushNotification($userId, $title, $message, $type);
        
        return $result->getInsertedId();
    }

    public function getUserNotifications($userId, $unreadOnly = false, $limit = 50) {
        $notificationsCollection = $this->database->selectCollection('notifications');
        
        $filter = ['user_id' => $userId];
        if ($unreadOnly) {
            $filter['read'] = false;
        }

        $cursor = $notificationsCollection->find($filter, [
            'sort' => ['created_at' => -1],
            'limit' => $limit
        ]);

        $notifications = [];
        foreach ($cursor as $notification) {
            $notifications[] = [
                'id' => (string)$notification['_id'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => $notification['type'],
                'task_id' => $notification['task_id'] ?? null,
                'priority' => $notification['priority'],
                'read' => $notification['read'],
                'created_at' => $notification['created_at']->format('Y-m-d H:i:s'),
                'read_at' => $notification['read_at'] ? $notification['read_at']->format('Y-m-d H:i:s') : null
            ];
        }

        return $notifications;
    }

    public function markAsRead($notificationId, $userId) {
        $notificationsCollection = $this->database->selectCollection('notifications');
        
        $result = $notificationsCollection->updateOne(
            [
                '_id' => new ObjectId($notificationId),
                'user_id' => $userId
            ],
            [
                '$set' => [
                    'read' => true,
                    'read_at' => new DateTime()
                ]
            ]
        );

        return $result->getModifiedCount() > 0;
    }

    public function deleteNotification($notificationId, $userId) {
        $notificationsCollection = $this->database->selectCollection('notifications');
        
        $result = $notificationsCollection->deleteOne([
            '_id' => new ObjectId($notificationId),
            'user_id' => $userId
        ]);

        return $result->getDeletedCount() > 0;
    }

    public function checkOverdueTasks() {
        $tasksCollection = $this->database->selectCollection('tasks');
        $usersCollection = $this->database->selectCollection('users');
        $currentDate = new DateTime();
        $count = 0;

        $overdueTasks = $tasksCollection->find([
            'due_date' => ['$lt' => $currentDate],
            'status' => ['$nin' => ['completed', 'cancelled']],
            'overdue_notified' => ['$ne' => true]
        ]);

        foreach ($overdueTasks as $task) {
            if (isset($task['assigned_to'])) {
                $user = $usersCollection->findOne([
                    '_id' => $task['assigned_to'],
                    'status' => 'active'
                ]);

                if ($user) {
                    $this->createNotification(
                        $task['assigned_to'],
                        'Tarea Vencida',
                        "La tarea '{$task['title']}' está vencida desde " . $task['due_date']->toDateTime()->format('d/m/Y'),
                        'warning',
                        (string)$task['_id'],
                        'high'
                    );

                    $tasksCollection->updateOne(
                        ['_id' => $task['_id']],
                        ['$set' => ['overdue_notified' => true]]
                    );

                    $count++;
                }
            }
        }

        return $count;
    }

    public function checkUpcomingDueTasks() {
        $tasksCollection = $this->database->selectCollection('tasks');
        $usersCollection = $this->database->selectCollection('users');
        $currentDate = new DateTime();
        $tomorrow = (clone $currentDate)->add(new DateInterval('P1D'));
        $count = 0;

        $upcomingTasks = $tasksCollection->find([
            'due_date' => [
                '$gte' => $currentDate,
                '$lte' => $tomorrow
            ],
            'status' => ['$nin' => ['completed', 'cancelled']],
            'due_soon_notified' => ['$ne' => true]
        ]);

        foreach ($upcomingTasks as $task) {
            if (isset($task['assigned_to'])) {
                $user = $usersCollection->findOne([
                    '_id' => $task['assigned_to'],
                    'status' => 'active'
                ]);

                if ($user) {
                    $dueDate = $task['due_date']->toDateTime();
                    $hoursRemaining = round(($dueDate->getTimestamp() - $currentDate->getTimestamp()) / 3600);

                    $this->createNotification(
                        $task['assigned_to'],
                        'Tarea Próxima a Vencer',
                        "La tarea '{$task['title']}' vence en {$hoursRemaining} horas",
                        'info',
                        (string)$task['_id'],
                        'medium'
                    );

                    $tasksCollection->updateOne(
                        ['_id' => $task['_id']],
                        ['$set' => ['due_soon_notified' => true]]
                    );

                    $count++;
                }
            }
        }

        return $count;
    }

    public function sendPushNotification($userId, $title, $message, $type = 'info') {
        // Implementación básica de notificaciones push
        // En un entorno real, aquí se integraría con servicios como Firebase, Pusher, etc.
        
        $usersCollection = $this->database->selectCollection('users');
        $user = $usersCollection->findOne(['_id' => $userId]);

        if ($user && isset($user['push_notifications_enabled']) && $user['push_notifications_enabled']) {
            // Simular envío de notificación push
            error_log("Push notification sent to user {$userId}: {$title} - {$message}");
            return true;
        }

        return false;
    }

    public function getNotificationStats($userId) {
        $notificationsCollection = $this->database->selectCollection('notifications');
        
        $totalNotifications = $notificationsCollection->countDocuments(['user_id' => $userId]);
        $unreadNotifications = $notificationsCollection->countDocuments([
            'user_id' => $userId,
            'read' => false
        ]);

        return [
            'total' => $totalNotifications,
            'unread' => $unreadNotifications,
            'read' => $totalNotifications - $unreadNotifications
        ];
    }

    public function cleanupExpiredNotifications() {
        $notificationsCollection = $this->database->selectCollection('notifications');
        $currentDate = new DateTime();

        $result = $notificationsCollection->deleteMany([
            'expires_at' => ['$lt' => $currentDate]
        ]);

        return $result->getDeletedCount();
    }
}

// Manejar las solicitudes
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $currentUser = requireAuth();
    $userId = $currentUser['user_id'];
    
    $notificationSystem = new NotificationSystem();

    switch ($action) {
        case 'get':
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            $limit = intval($_GET['limit'] ?? 50);
            
            $notifications = $notificationSystem->getUserNotifications($userId, $unreadOnly, $limit);
            $stats = $notificationSystem->getNotificationStats($userId);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'stats' => $stats
            ]);
            break;

        case 'mark_read':
            if ($method !== 'PUT') {
                throw new Exception('Método no permitido');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $notificationId = $input['notification_id'] ?? '';
            
            if (!$notificationId) {
                throw new Exception('ID de notificación requerido');
            }
            
            $success = $notificationSystem->markAsRead($notificationId, $userId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notificación marcada como leída' : 'Error al marcar notificación'
            ]);
            break;

        case 'delete':
            if ($method !== 'DELETE') {
                throw new Exception('Método no permitido');
            }
            
            $notificationId = $_GET['notification_id'] ?? '';
            
            if (!$notificationId) {
                throw new Exception('ID de notificación requerido');
            }
            
            $success = $notificationSystem->deleteNotification($notificationId, $userId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notificación eliminada' : 'Error al eliminar notificación'
            ]);
            break;

        case 'stats':
            $stats = $notificationSystem->getNotificationStats($userId);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;

        case 'check_overdue':
            // Solo para administradores
            $adminUser = requireAdmin();
            $count = $notificationSystem->checkOverdueTasks();
            
            echo json_encode([
                'success' => true,
                'message' => "Se enviaron {$count} notificaciones de tareas vencidas"
            ]);
            break;

        case 'check_upcoming':
            // Solo para administradores
            $adminUser = requireAdmin();
            $count = $notificationSystem->checkUpcomingDueTasks();
            
            echo json_encode([
                'success' => true,
                'message' => "Se enviaron {$count} notificaciones de tareas próximas a vencer"
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida'
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>