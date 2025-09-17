<?php
/**
 * BIG PLAN TRACKER - Notification System Test
 * Test script to verify notification functionality
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Include configuration
require_once __DIR__ . '/../config/mongodb.php';

use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

class NotificationTester {
    private $database;
    private $notificationsCollection;
    
    public function __construct() {
        try {
            $mongoConfig = MongoDBConfig::getInstance();
            $this->database = $mongoConfig->getDatabase();
            $this->notificationsCollection = $this->database->selectCollection('notifications');
            
            echo "✅ Notification tester initialized\n";
        } catch (Exception $e) {
            echo "❌ Error initializing: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    public function runTests() {
        echo "\n🧪 Running notification system tests...\n\n";
        
        $this->testCreateNotification();
        $this->testGetNotifications();
        $this->testMarkAsRead();
        $this->testDeleteNotification();
        
        echo "\n✅ All tests completed!\n";
    }
    
    private function testCreateNotification() {
        echo "📝 Testing notification creation...\n";
        
        $testNotification = [
            'user_id' => null, // Admin notification
            'type' => 'info',
            'priority' => 'medium',
            'title' => 'Test Notification',
            'message' => 'This is a test notification created by the test script.',
            'task_id' => null,
            'read' => false,
            'created_at' => new UTCDateTime(),
            'metadata' => [
                'test' => true,
                'created_by' => 'test-script'
            ]
        ];
        
        try {
            $result = $this->notificationsCollection->insertOne($testNotification);
            $insertedId = $result->getInsertedId();
            
            echo "   ✅ Notification created with ID: " . $insertedId . "\n";
            return $insertedId;
        } catch (Exception $e) {
            echo "   ❌ Failed to create notification: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    private function testGetNotifications() {
        echo "📋 Testing notification retrieval...\n";
        
        try {
            $notifications = $this->notificationsCollection->find(
                ['metadata.test' => true],
                ['sort' => ['created_at' => -1], 'limit' => 10]
            );
            
            $count = 0;
            foreach ($notifications as $notification) {
                $count++;
                echo "   📄 Found notification: " . $notification['title'] . "\n";
            }
            
            echo "   ✅ Retrieved $count test notifications\n";
        } catch (Exception $e) {
            echo "   ❌ Failed to retrieve notifications: " . $e->getMessage() . "\n";
        }
    }
    
    private function testMarkAsRead() {
        echo "👁️ Testing mark as read functionality...\n";
        
        try {
            // Find a test notification
            $notification = $this->notificationsCollection->findOne([
                'metadata.test' => true,
                'read' => false
            ]);
            
            if ($notification) {
                $result = $this->notificationsCollection->updateOne(
                    ['_id' => $notification['_id']],
                    ['$set' => ['read' => true, 'read_at' => new UTCDateTime()]]
                );
                
                if ($result->getModifiedCount() > 0) {
                    echo "   ✅ Notification marked as read\n";
                } else {
                    echo "   ⚠️ No notifications were modified\n";
                }
            } else {
                echo "   ⚠️ No unread test notifications found\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Failed to mark notification as read: " . $e->getMessage() . "\n";
        }
    }
    
    private function testDeleteNotification() {
        echo "🗑️ Testing notification deletion...\n";
        
        try {
            $result = $this->notificationsCollection->deleteMany([
                'metadata.test' => true
            ]);
            
            $deletedCount = $result->getDeletedCount();
            echo "   ✅ Deleted $deletedCount test notifications\n";
        } catch (Exception $e) {
            echo "   ❌ Failed to delete notifications: " . $e->getMessage() . "\n";
        }
    }
    
    public function createSampleNotifications() {
        echo "\n📦 Creating sample notifications for testing...\n";
        
        $sampleNotifications = [
            [
                'user_id' => null,
                'type' => 'task_overdue',
                'priority' => 'high',
                'title' => 'Tarea Vencida',
                'message' => 'La tarea "Revisar documentación" está vencida desde hace 2 días.',
                'task_id' => new ObjectId(),
                'read' => false,
                'created_at' => new UTCDateTime(),
                'metadata' => ['sample' => true]
            ],
            [
                'user_id' => null,
                'type' => 'task_due',
                'priority' => 'medium',
                'title' => 'Tarea Próxima a Vencer',
                'message' => 'La tarea "Preparar presentación" vence en 24 horas.',
                'task_id' => new ObjectId(),
                'read' => false,
                'created_at' => new UTCDateTime(),
                'metadata' => ['sample' => true]
            ],
            [
                'user_id' => null,
                'type' => 'info',
                'priority' => 'low',
                'title' => 'Actualización del Sistema',
                'message' => 'El sistema de notificaciones ha sido actualizado con nuevas funcionalidades.',
                'task_id' => null,
                'read' => true,
                'created_at' => new UTCDateTime((time() - 3600) * 1000), // 1 hour ago
                'read_at' => new UTCDateTime(),
                'metadata' => ['sample' => true]
            ]
        ];
        
        try {
            $result = $this->notificationsCollection->insertMany($sampleNotifications);
            $insertedCount = $result->getInsertedCount();
            
            echo "   ✅ Created $insertedCount sample notifications\n";
            echo "   💡 You can now test the frontend notification system!\n";
        } catch (Exception $e) {
            echo "   ❌ Failed to create sample notifications: " . $e->getMessage() . "\n";
        }
    }
    
    public function cleanupSampleNotifications() {
        echo "\n🧹 Cleaning up sample notifications...\n";
        
        try {
            $result = $this->notificationsCollection->deleteMany([
                'metadata.sample' => true
            ]);
            
            $deletedCount = $result->getDeletedCount();
            echo "   ✅ Deleted $deletedCount sample notifications\n";
        } catch (Exception $e) {
            echo "   ❌ Failed to cleanup sample notifications: " . $e->getMessage() . "\n";
        }
    }
}

// Run the tests
try {
    $tester = new NotificationTester();
    
    // Check command line arguments
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'test':
                $tester->runTests();
                break;
            case 'samples':
                $tester->createSampleNotifications();
                break;
            case 'cleanup':
                $tester->cleanupSampleNotifications();
                break;
            default:
                echo "Usage: php test-notifications.php [test|samples|cleanup]\n";
                echo "  test    - Run all notification tests\n";
                echo "  samples - Create sample notifications\n";
                echo "  cleanup - Remove sample notifications\n";
                break;
        }
    } else {
        echo "🚀 BIG PLAN TRACKER - Notification System Test\n";
        echo "Usage: php test-notifications.php [test|samples|cleanup]\n\n";
        echo "Available commands:\n";
        echo "  test    - Run all notification tests\n";
        echo "  samples - Create sample notifications for frontend testing\n";
        echo "  cleanup - Remove sample notifications\n\n";
        echo "Example: php test-notifications.php samples\n";
    }
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>