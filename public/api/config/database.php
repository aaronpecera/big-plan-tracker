<?php
/**
 * Database Connection Class
 * Maneja la conexión a MongoDB
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

class DatabaseConnection {
    private static $instance = null;
    private $client;
    private $database;
    
    private function __construct() {
        try {
            // Configuración de MongoDB
            $mongoUri = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
            $dbName = getenv('MONGODB_DB') ?: 'big_plan_tracker';
            
            $this->client = new MongoDB\Client($mongoUri);
            $this->database = $this->client->selectDatabase($dbName);
            
        } catch (Exception $e) {
            error_log("Error conectando a MongoDB: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->database;
    }
    
    public function getDatabase() {
        return $this->database;
    }
    
    // Método para crear un objeto DateTime compatible
    public function createDateTimeObject($timestamp = null) {
        if (class_exists('MongoDB\BSON\UTCDateTime')) {
            return new MongoDB\BSON\UTCDateTime($timestamp ? $timestamp * 1000 : null);
        } else {
            // Fallback para cuando la extensión MongoDB no está disponible
            return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
        }
    }
}
?>