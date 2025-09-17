<?php
/**
 * Configuración de MongoDB para BIG PLAN
 * Sistema de gestión de tareas y tiempo como Microsoft Planner
 */

class MongoDBConfig {
    private static $instance = null;
    private $client;
    private $database;
    
    // Configuración de conexión
    private $host = 'localhost';
    private $port = 27017;
    private $database_name = 'big_plan_tracker';
    private $username = '';
    private $password = '';
    
    private function __construct() {
        try {
            // Construir URI de conexión
            $uri = "mongodb://";
            if (!empty($this->username) && !empty($this->password)) {
                $uri .= $this->username . ":" . $this->password . "@";
            }
            $uri .= $this->host . ":" . $this->port;
            
            // Crear cliente MongoDB
            $this->client = new MongoDB\Client($uri);
            $this->database = $this->client->selectDatabase($this->database_name);
            
            // Verificar conexión
            $this->client->listDatabases();
            
        } catch (Exception $e) {
            error_log("Error conectando a MongoDB: " . $e->getMessage());
            throw new Exception("No se pudo conectar a la base de datos");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getDatabase() {
        return $this->database;
    }
    
    public function getCollection($name) {
        return $this->database->selectCollection($name);
    }
    
    /**
     * Inicializar colecciones y índices
     */
    public function initializeDatabase() {
        try {
            // Crear índices para usuarios
            $this->getCollection('users')->createIndex(['email' => 1], ['unique' => true]);
            $this->getCollection('users')->createIndex(['username' => 1], ['unique' => true]);
            
            // Crear índices para empresas
            $this->getCollection('companies')->createIndex(['name' => 1], ['unique' => true]);
            
            // Crear índices para tareas
            $this->getCollection('tasks')->createIndex(['assigned_to' => 1]);
            $this->getCollection('tasks')->createIndex(['company_id' => 1]);
            $this->getCollection('tasks')->createIndex(['status' => 1]);
            $this->getCollection('tasks')->createIndex(['due_date' => 1]);
            $this->getCollection('tasks')->createIndex(['created_at' => -1]);
            
            // Crear índices para tracking de tiempo
            $this->getCollection('time_tracking')->createIndex(['task_id' => 1]);
            $this->getCollection('time_tracking')->createIndex(['user_id' => 1]);
            $this->getCollection('time_tracking')->createIndex(['start_time' => -1]);
            
            // Crear índices para comentarios
            $this->getCollection('task_comments')->createIndex(['task_id' => 1]);
            $this->getCollection('task_comments')->createIndex(['created_at' => -1]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error inicializando base de datos: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Esquemas de colecciones para BIG PLAN
 */
class BigPlanSchemas {
    
    /**
     * Esquema para usuarios
     */
    public static function getUserSchema() {
        return [
            '_id' => 'ObjectId',
            'username' => 'string',
            'email' => 'string',
            'password' => 'string', // hash
            'role' => 'string', // 'admin' o 'user'
            'profile' => [
                'first_name' => 'string',
                'last_name' => 'string',
                'avatar' => 'string', // URL de la imagen
                'background' => 'string', // URL del fondo personalizado
                'phone' => 'string',
                'position' => 'string'
            ],
            'companies' => ['array'], // IDs de empresas asignadas
            'preferences' => [
                'language' => 'string',
                'timezone' => 'string',
                'notifications' => 'boolean'
            ],
            'last_login' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'active' => 'boolean'
        ];
    }
    
    /**
     * Esquema para empresas
     */
    public static function getCompanySchema() {
        return [
            '_id' => 'ObjectId',
            'name' => 'string',
            'description' => 'string',
            'cost_per_hour' => 'decimal', // Costo por hora
            'currency' => 'string', // EUR, USD, etc.
            'logo' => 'string', // URL del logo
            'contact' => [
                'email' => 'string',
                'phone' => 'string',
                'address' => 'string'
            ],
            'created_by' => 'ObjectId', // ID del admin que la creó
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'active' => 'boolean'
        ];
    }
    
    /**
     * Esquema para tareas
     */
    public static function getTaskSchema() {
        return [
            '_id' => 'ObjectId',
            'title' => 'string',
            'description' => 'string',
            'company_id' => 'ObjectId',
            'assigned_to' => ['array'], // Array de IDs de usuarios
            'created_by' => 'ObjectId', // ID del admin
            'status' => 'string', // 'not_started', 'in_progress', 'paused', 'completed'
            'priority' => 'string', // 'low', 'medium', 'high', 'urgent'
            'estimated_hours' => 'decimal',
            'actual_hours' => 'decimal',
            'total_cost' => 'decimal',
            'due_date' => 'datetime',
            'start_date' => 'datetime',
            'completion_date' => 'datetime',
            'tags' => ['array'],
            'attachments' => ['array'],
            'status_history' => [
                [
                    'status' => 'string',
                    'changed_by' => 'ObjectId',
                    'changed_at' => 'datetime',
                    'comment' => 'string'
                ]
            ],
            'extension_requests' => [
                [
                    'requested_by' => 'ObjectId',
                    'requested_at' => 'datetime',
                    'reason' => 'string',
                    'new_due_date' => 'datetime',
                    'status' => 'string', // 'pending', 'approved', 'rejected'
                    'admin_response' => 'string'
                ]
            ],
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }
    
    /**
     * Esquema para tracking de tiempo
     */
    public static function getTimeTrackingSchema() {
        return [
            '_id' => 'ObjectId',
            'task_id' => 'ObjectId',
            'user_id' => 'ObjectId',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'int',
            'cost' => 'decimal',
            'description' => 'string',
            'is_manual' => 'boolean', // Si fue ingresado manualmente
            'created_at' => 'datetime'
        ];
    }
    
    /**
     * Esquema para comentarios de tareas
     */
    public static function getTaskCommentsSchema() {
        return [
            '_id' => 'ObjectId',
            'task_id' => 'ObjectId',
            'user_id' => 'ObjectId',
            'comment' => 'string',
            'type' => 'string', // 'comment', 'status_change', 'assignment'
            'attachments' => ['array'],
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }
    
    /**
     * Esquema para configuración del sistema
     */
    public static function getSystemConfigSchema() {
        return [
            '_id' => 'ObjectId',
            'key' => 'string',
            'value' => 'mixed',
            'description' => 'string',
            'updated_by' => 'ObjectId',
            'updated_at' => 'datetime'
        ];
    }
    
    /**
     * Esquema para chat
     */
    public static function getChatSchema() {
        return [
            '_id' => 'ObjectId',
            'from_user' => 'ObjectId',
            'to_user' => 'ObjectId',
            'message' => 'string',
            'read' => 'boolean',
            'created_at' => 'datetime'
        ];
    }
}
?>