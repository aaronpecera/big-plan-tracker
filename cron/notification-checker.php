<?php
/**
 * BIG PLAN TRACKER - Notification Cron Job
 * Automated task checking and notification generation
 * 
 * This script should be run periodically (e.g., every 15 minutes) via cron:
 * 0,15,30,45 * * * * /usr/bin/php /path/to/notification-checker.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Include configuration
require_once __DIR__ . '/../config/MongoDBConfig.php';

class NotificationChecker {
    private $database;
    private $tasksCollection;
    private $usersCollection;
    private $notificationsCollection;
    
    public function __construct() {
        try {
            $mongoConfig = MongoDBConfig::getInstance();
            $this->database = $mongoConfig->getDatabase();
            $this->tasksCollection = $this->database->selectCollection('tasks');
            $this->usersCollection = $this->database->selectCollection('users');
            $this->notificationsCollection = $this->database->selectCollection('notifications');
            
            echo "[" . date('Y-m-d H:i:s') . "] Notification checker initialized\n";
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error initializing: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    public function run() {
        echo "[" . date('Y-m-d H:i:s') . "] Starting notification check...\n";
        
        try {
            $this->checkOverdueTasks();
            $this->checkUpcomingDeadlines();
            $this->checkStuckTasks();
            $this->cleanupOldNotifications();
            
            echo "[" . date('Y-m-d H:i:s') . "] Notification check completed successfully\n";
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error during notification check: " . $e->getMessage() . "\n";
        }
    }
    
    private function checkOverdueTasks() {
        echo "[" . date('Y-m-d H:i:s') . "] Checking overdue tasks...\n";
        
        $now = new UTCDateTime();
        $oneDayAgo = new UTCDateTime((time() - 86400) * 1000);
        
        // Find overdue tasks that haven't been notified recently
        $overdueTasks = $this->tasksCollection->find([
            'due_date' => ['$lt' => $now],
            'status' => ['$nin' => ['completed', 'cancelled']],
            '$or' => [
                ['last_overdue_notification' => ['$exists' => false]],
                ['last_overdue_notification' => ['$lt' => $oneDayAgo]]
            ]
        ]);
        
        $count = 0;
        foreach ($overdueTasks as $task) {
            $this->createOverdueNotification($task);
            
            // Update task with notification timestamp
            $this->tasksCollection->updateOne(
                ['_id' => $task['_id']],
                ['$set' => ['last_overdue_notification' => $now]]
            );
            
            $count++;
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Created $count overdue notifications\n";
    }
    
    private function checkUpcomingDeadlines() {
        echo "[" . date('Y-m-d H:i:s') . "] Checking upcoming deadlines...\n";
        
        $now = new MongoDB\BSON\UTCDateTime();
        $tomorrow = new MongoDB\BSON\UTCDateTime((time() + 86400) * 1000);
        $threeDays = new MongoDB\BSON\UTCDateTime((time() + 259200) * 1000);
        
        // Find tasks due in the next 24 hours
        $upcomingTasks = $this->tasksCollection->find([
            'due_date' => [
                '$gte' => $now,
                '$lte' => $tomorrow
            ],
            'status' => ['$nin' => ['completed', 'cancelled']],
            '$or' => [
                ['last_reminder_notification' => ['$exists' => false]],
                ['last_reminder_notification' => ['$lt' => new MongoDB\BSON\UTCDateTime((time() - 43200) * 1000)]] // 12 hours ago
            ]
        ]);
        
        $count = 0;
        foreach ($upcomingTasks as $task) {
            $this->createUpcomingDeadlineNotification($task, '24 horas');
            
            // Update task with notification timestamp
            $this->tasksCollection->updateOne(
                ['_id' => $task['_id']],
                ['$set' => ['last_reminder_notification' => $now]]
            );
            
            $count++;
        }
        
        // Find tasks due in 3 days (weekly reminder)
        $weeklyReminders = $this->tasksCollection->find([
            'due_date' => [
                '$gte' => $tomorrow,
                '$lte' => $threeDays
            ],
            'status' => ['$nin' => ['completed', 'cancelled']],
            'priority' => ['$in' => ['high', 'urgent']],
            '$or' => [
                ['last_weekly_reminder' => ['$exists' => false]],
                ['last_weekly_reminder' => ['$lt' => new MongoDB\BSON\UTCDateTime((time() - 604800) * 1000)]] // 1 week ago
            ]
        ]);
        
        foreach ($weeklyReminders as $task) {
            $this->createUpcomingDeadlineNotification($task, '3 días');
            
            // Update task with notification timestamp
            $this->tasksCollection->updateOne(
                ['_id' => $task['_id']],
                ['$set' => ['last_weekly_reminder' => $now]]
            );
            
            $count++;
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Created $count upcoming deadline notifications\n";
    }
    
    private function checkStuckTasks() {
        echo "[" . date('Y-m-d H:i:s') . "] Checking stuck tasks...\n";
        
        $sevenDaysAgo = new MongoDB\BSON\UTCDateTime((time() - 604800) * 1000);
        $threeDaysAgo = new MongoDB\BSON\UTCDateTime((time() - 259200) * 1000);
        
        // Find tasks that haven't been updated in 7 days
        $stuckTasks = $this->tasksCollection->find([
            'status' => 'in_progress',
            'updated_at' => ['$lt' => $sevenDaysAgo],
            '$or' => [
                ['last_stuck_notification' => ['$exists' => false]],
                ['last_stuck_notification' => ['$lt' => $threeDaysAgo]]
            ]
        ]);
        
        $count = 0;
        foreach ($stuckTasks as $task) {
            $this->createStuckTaskNotification($task);
            
            // Update task with notification timestamp
            $this->tasksCollection->updateOne(
                ['_id' => $task['_id']],
                ['$set' => ['last_stuck_notification' => new MongoDB\BSON\UTCDateTime()]]
            );
            
            $count++;
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Created $count stuck task notifications\n";
    }
    
    private function createOverdueNotification($task) {
        $daysOverdue = ceil((time() - $task['due_date']->toDateTime()->getTimestamp()) / 86400);
        
        $notification = [
            'user_id' => $task['assigned_to'] ?? null,
            'type' => 'task_overdue',
            'priority' => 'high',
            'title' => 'Tarea Vencida',
            'message' => "La tarea '{$task['title']}' está vencida desde hace {$daysOverdue} día(s).",
            'task_id' => $task['_id'],
            'read' => false,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'metadata' => [
                'task_title' => $task['title'],
                'days_overdue' => $daysOverdue,
                'original_due_date' => $task['due_date']
            ]
        ];
        
        $this->notificationsCollection->insertOne($notification);
        
        // Also create admin notification if task is high priority
        if (isset($task['priority']) && in_array($task['priority'], ['high', 'urgent'])) {
            $adminNotification = $notification;
            $adminNotification['user_id'] = null; // Admin notification
            $adminNotification['title'] = 'Tarea Crítica Vencida';
            $adminNotification['message'] = "Tarea de alta prioridad '{$task['title']}' vencida hace {$daysOverdue} día(s).";
            
            $this->notificationsCollection->insertOne($adminNotification);
        }
    }
    
    private function createUpcomingDeadlineNotification($task, $timeframe) {
        $notification = [
            'user_id' => $task['assigned_to'] ?? null,
            'type' => 'task_due',
            'priority' => $task['priority'] ?? 'medium',
            'title' => 'Tarea Próxima a Vencer',
            'message' => "La tarea '{$task['title']}' vence en {$timeframe}.",
            'task_id' => $task['_id'],
            'read' => false,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'metadata' => [
                'task_title' => $task['title'],
                'due_date' => $task['due_date'],
                'timeframe' => $timeframe
            ]
        ];
        
        $this->notificationsCollection->insertOne($notification);
    }
    
    private function createStuckTaskNotification($task) {
        $daysSinceUpdate = ceil((time() - $task['updated_at']->toDateTime()->getTimestamp()) / 86400);
        
        $notification = [
            'user_id' => $task['assigned_to'] ?? null,
            'type' => 'warning',
            'priority' => 'medium',
            'title' => 'Tarea Sin Actividad',
            'message' => "La tarea '{$task['title']}' no ha sido actualizada en {$daysSinceUpdate} días.",
            'task_id' => $task['_id'],
            'read' => false,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'metadata' => [
                'task_title' => $task['title'],
                'days_inactive' => $daysSinceUpdate,
                'last_update' => $task['updated_at']
            ]
        ];
        
        $this->notificationsCollection->insertOne($notification);
    }
    
    private function cleanupOldNotifications() {
        echo "[" . date('Y-m-d H:i:s') . "] Cleaning up old notifications...\n";
        
        // Delete read notifications older than 30 days
        $thirtyDaysAgo = new MongoDB\BSON\UTCDateTime((time() - 2592000) * 1000);
        
        $result = $this->notificationsCollection->deleteMany([
            'read' => true,
            'created_at' => ['$lt' => $thirtyDaysAgo]
        ]);
        
        echo "[" . date('Y-m-d H:i:s') . "] Deleted {$result->getDeletedCount()} old notifications\n";
        
        // Delete unread notifications older than 90 days (except high priority)
        $ninetyDaysAgo = new MongoDB\BSON\UTCDateTime((time() - 7776000) * 1000);
        
        $result = $this->notificationsCollection->deleteMany([
            'read' => false,
            'priority' => ['$nin' => ['high', 'urgent']],
            'created_at' => ['$lt' => $ninetyDaysAgo]
        ]);
        
        echo "[" . date('Y-m-d H:i:s') . "] Deleted {$result->getDeletedCount()} old unread notifications\n";
    }
}

// Run the notification checker
try {
    $checker = new NotificationChecker();
    $checker->run();
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Notification checker finished\n";
?>