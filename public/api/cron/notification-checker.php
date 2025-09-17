<?php
/**
 * Script de Cron para verificar tareas vencidas y próximas a vencer
 * Ejecutar cada 15 minutos: 0,15,30,45 * * * * /usr/bin/php /path/to/notification-checker.php
 */

// Solo permitir ejecución desde línea de comandos o con token especial
if (php_sapi_name() !== 'cli' && (!isset($_GET['cron_token']) || $_GET['cron_token'] !== 'your_secure_cron_token_here')) {
    http_response_code(403);
    die('Acceso denegado');
}

require_once '../config/database.php';

class NotificationCronJob {
    private $db;
    private $logFile;

    public function __construct() {
        $this->db = DatabaseConnection::getInstance();
        $this->logFile = __DIR__ . '/../../logs/notification-cron.log';
        
        // Crear directorio de logs si no existe
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function run() {
        $this->log("Iniciando verificación de notificaciones...");
        
        try {
            $overdueCount = $this->checkOverdueTasks();
            $upcomingCount = $this->checkUpcomingDueTasks();
            $cleanupCount = $this->cleanupExpiredNotifications();
            
            $this->log("Verificación completada:");
            $this->log("- Notificaciones de tareas vencidas enviadas: {$overdueCount}");
            $this->log("- Notificaciones de tareas próximas a vencer enviadas: {$upcomingCount}");
            $this->log("- Notificaciones expiradas eliminadas: {$cleanupCount}");
            
            return [
                'success' => true,
                'overdue_notifications' => $overdueCount,
                'upcoming_notifications' => $upcomingCount,
                'cleanup_count' => $cleanupCount
            ];
            
        } catch (Exception $e) {
            $this->log("Error durante la verificación: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkOverdueTasks() {
        $database = $this->db->getDatabase();
        $tasksCollection = $database->selectCollection('tasks');
        $notificationsCollection = $database->selectCollection('notifications');
        $usersCollection = $database->selectCollection('users');
        
        $currentDate = new DateTime();
        $count = 0;

        // Buscar tareas vencidas que no han sido notificadas
        $overdueTasks = $tasksCollection->find([
            'due_date' => ['$lt' => $currentDate],
            'status' => ['$nin' => ['completed', 'cancelled']],
            'overdue_notified' => ['$ne' => true]
        ])->toArray();

        foreach ($overdueTasks as $task) {
            if (isset($task['assigned_to'])) {
                // Verificar que el usuario existe y está activo
                $user = $usersCollection->findOne([
                    '_id' => $task['assigned_to'],
                    'status' => 'active'
                ]);

                if ($user) {
                    // Crear notificación
                    $notification = [
                        'user_id' => $task['assigned_to'],
                        'title' => 'Tarea Vencida',
                        'message' => "La tarea '{$task['title']}' ha vencido y requiere atención inmediata.",
                        'type' => 'task_overdue',
                        'priority' => 'urgent',
                        'task_id' => $task['_id'],
                        'read' => false,
                        'created_at' => new DateTime(),
                        'expires_at' => (new DateTime())->add(new DateInterval('P30D'))
                    ];

                    $notificationsCollection->insertOne($notification);

                    // Marcar tarea como notificada
                    $tasksCollection->updateOne(
                        ['_id' => $task['_id']],
                        ['$set' => ['overdue_notified' => true]]
                    );

                    $count++;
                    $this->log("Notificación de vencimiento enviada para tarea: {$task['title']} (Usuario: {$user['email']})");
                }
            }
        }

        return $count;
    }

    private function checkUpcomingDueTasks() {
        $database = $this->db->getDatabase();
        $tasksCollection = $database->selectCollection('tasks');
        $notificationsCollection = $database->selectCollection('notifications');
        $usersCollection = $database->selectCollection('users');
        
        $currentDate = new DateTime();
        $tomorrow = (clone $currentDate)->add(new DateInterval('P1D'));
        $count = 0;

        // Buscar tareas que vencen en las próximas 24 horas
        $upcomingTasks = $tasksCollection->find([
            'due_date' => [
                '$gte' => $currentDate,
                '$lte' => $tomorrow
            ],
            'status' => ['$nin' => ['completed', 'cancelled']],
            'due_soon_notified' => ['$ne' => true]
        ])->toArray();

        foreach ($upcomingTasks as $task) {
            if (isset($task['assigned_to'])) {
                // Verificar que el usuario existe y está activo
                $user = $usersCollection->findOne([
                    '_id' => $task['assigned_to'],
                    'status' => 'active'
                ]);

                if ($user) {
                    $dueDate = $task['due_date']->toDateTime();
                    $hoursRemaining = round(($dueDate->getTimestamp() - $currentDate->getTimestamp()) / 3600);

                    // Crear notificación
                    $notification = [
                        'user_id' => $task['assigned_to'],
                        'title' => 'Tarea Próxima a Vencer',
                        'message' => "La tarea '{$task['title']}' vence en {$hoursRemaining} horas.",
                        'type' => 'task_due',
                        'priority' => 'high',
                        'task_id' => $task['_id'],
                        'read' => false,
                        'created_at' => new DateTime(),
                        'expires_at' => (new DateTime())->add(new DateInterval('P30D'))
                    ];

                    $notificationsCollection->insertOne($notification);

                    // Marcar tarea como notificada
                    $tasksCollection->updateOne(
                        ['_id' => $task['_id']],
                        ['$set' => ['due_soon_notified' => true]]
                    );

                    $count++;
                    $this->log("Notificación de vencimiento próximo enviada para tarea: {$task['title']} (Usuario: {$user['email']})");
                }
            }
        }

        return $count;
    }

    private function cleanupExpiredNotifications() {
        $database = $this->db->getDatabase();
        $notificationsCollection = $database->selectCollection('notifications');
        $currentDate = new DateTime();

        // Eliminar notificaciones expiradas
        $result = $notificationsCollection->deleteMany([
            'expires_at' => ['$lt' => $currentDate]
        ]);

        $deletedCount = $result->getDeletedCount();
        
        if ($deletedCount > 0) {
            $this->log("Eliminadas {$deletedCount} notificaciones expiradas");
        }

        return $deletedCount;
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // Escribir al archivo de log
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // También mostrar en consola si se ejecuta desde CLI
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
}

// Ejecutar el trabajo de cron
$cronJob = new NotificationCronJob();
$result = $cronJob->run();

// Si se ejecuta desde web, devolver JSON
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode($result);
}

exit($result['success'] ? 0 : 1);
?>