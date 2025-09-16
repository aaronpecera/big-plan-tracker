<?php

namespace App\Config;

use MongoDB\Client;
use MongoDB\Database as MongoDatabase;
use Exception;

class DatabaseConnection
{
    private static $instance = null;
    private $client;
    private $database;
    
    private function __construct()
    {
        try {
            $uri = $_ENV['MONGODB_URI'] ?? getenv('MONGODB_URI');
            $dbName = $_ENV['MONGODB_DATABASE'] ?? getenv('MONGODB_DATABASE') ?? 'bigplantracker';
            
            if (!$uri) {
                throw new Exception('MongoDB URI not configured');
            }
            
            $this->client = new \MongoDB\Client($uri);
            $this->database = $this->client->selectDatabase($dbName);
            
            // Test connection
            $this->database->command(['ping' => 1]);
            
        } catch (Exception $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new Exception('Failed to connect to database: ' . $e->getMessage());
        }
    }
    
    public static function getInstance(): DatabaseConnection
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getDatabase(): MongoDatabase
    {
        return $this->database;
    }
    
    public function getCollection(string $name)
    {
        return $this->database->selectCollection($name);
    }
    
    public function testConnection(): bool
    {
        try {
            $this->database->command(['ping' => 1]);
            return true;
        } catch (Exception $e) {
            error_log('Database ping failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function initializeCollections(): bool
    {
        try {
            $collections = [
                'users' => [
                    'indexes' => [
                        ['key' => ['username' => 1], 'unique' => true],
                        ['key' => ['email' => 1], 'unique' => true]
                    ]
                ],
                'companies' => [
                    'indexes' => [
                        ['key' => ['name' => 1], 'unique' => true]
                    ]
                ],
                'projects' => [
                    'indexes' => [
                        ['key' => ['company_id' => 1]],
                        ['key' => ['name' => 1]]
                    ]
                ],
                'tasks' => [
                    'indexes' => [
                        ['key' => ['project_id' => 1]],
                        ['key' => ['assigned_to' => 1]],
                        ['key' => ['status' => 1]]
                    ]
                ],
                'activities' => [
                    'indexes' => [
                        ['key' => ['task_id' => 1]],
                        ['key' => ['user_id' => 1]],
                        ['key' => ['created_at' => -1]]
                    ]
                ]
            ];
            
            foreach ($collections as $collectionName => $config) {
                $collection = $this->getCollection($collectionName);
                
                // Create indexes
                if (isset($config['indexes'])) {
                    foreach ($config['indexes'] as $index) {
                        try {
                            $collection->createIndex($index['key'], $index);
                        } catch (Exception $e) {
                            // Index might already exist, continue
                            error_log("Index creation warning for $collectionName: " . $e->getMessage());
                        }
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Collection initialization error: ' . $e->getMessage());
            return false;
        }
    }
}