<?php
/**
 * Script de actualización de base de datos para Cost Tracking
 * Big Plan Tracker - Sistema de seguimiento de costos
 * 
 * Este script actualiza la base de datos para incluir
 * todas las funcionalidades de seguimiento de costos.
 */

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables only in development
if (file_exists(__DIR__ . '/.env') && getenv('APP_ENV') !== 'production') {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

use App\Config\DatabaseConnection;

class DatabaseCostTrackingUpdater {
    private $db;
    private $database;
    
    public function __construct() {
        try {
            $this->db = DatabaseConnection::getInstance();
            $this->database = $this->db->getDatabase();
            echo "✅ Conexión a base de datos establecida\n";
        } catch (Exception $e) {
            die("❌ Error conectando a la base de datos: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Ejecutar todas las actualizaciones
     */
    public function runAllUpdates() {
        echo "\n🚀 Iniciando actualización de base de datos para Cost Tracking...\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        $this->updateCompaniesCollection();
        $this->updateTasksCollection();
        $this->updateTimeTrackingCollection();
        $this->addSystemConfiguration();
        $this->createIndexes();
        $this->validateData();
        
        echo "\n✅ Actualización completada exitosamente!\n";
        echo "=" . str_repeat("=", 60) . "\n";
    }
    
    /**
     * Actualizar colección de empresas
     */
    private function updateCompaniesCollection() {
        echo "📊 Actualizando colección de empresas...\n";
        
        $companies = $this->database->companies;
        
        // Agregar campos de costo a empresas existentes que no los tengan
        $result = $companies->updateMany(
            ['cost_per_hour' => ['$exists' => false]],
            [
                '$set' => [
                    'cost_per_hour' => 25.00,
                    'currency' => 'EUR',
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        echo "   - Empresas actualizadas con costo por hora: {$result->getModifiedCount()}\n";
        
        // Agregar campos faltantes a empresas existentes
        $result = $companies->updateMany(
            ['currency' => ['$exists' => false]],
            [
                '$set' => [
                    'currency' => 'EUR',
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        echo "   - Empresas actualizadas con moneda: {$result->getModifiedCount()}\n";
        
        // Asegurar que todas las empresas tengan campos de contacto
        $result = $companies->updateMany(
            ['contact' => ['$exists' => false]],
            [
                '$set' => [
                    'contact' => [
                        'email' => '',
                        'phone' => '',
                        'address' => ''
                    ],
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        echo "   - Empresas actualizadas con información de contacto: {$result->getModifiedCount()}\n";
        
        // Asegurar que todas las empresas estén activas por defecto
        $result = $companies->updateMany(
            ['active' => ['$exists' => false]],
            [
                '$set' => [
                    'active' => true,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        echo "   - Empresas marcadas como activas: {$result->getModifiedCount()}\n";
    }
    
    /**
     * Actualizar colección de tareas
     */
    private function updateTasksCollection() {
        echo "📋 Actualizando colección de tareas...\n";
        
        $tasks = $this->database->tasks;
        
        // Agregar campos de costo a tareas existentes
        $result = $tasks->updateMany(
            ['total_cost' => ['$exists' => false]],
            [
                '$set' => [
                    'total_cost' => 0.00,
                    'actual_hours' => 0.00,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        echo "   - Tareas actualizadas con campos de costo: {$result->getModifiedCount()}\n";
        
        // Asegurar que todas las tareas tengan horas estimadas
        $result = $tasks->updateMany(
            ['estimated_hours' => ['$exists' => false]],
            [
                '$set' => [
                    'estimated_hours' => 1.00,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        echo "   - Tareas actualizadas con horas estimadas: {$result->getModifiedCount()}\n";
        
        // Recalcular costos para tareas existentes
        $this->recalculateTaskCosts();
    }
    
    /**
     * Actualizar colección de seguimiento de tiempo
     */
    private function updateTimeTrackingCollection() {
        echo "⏱️ Actualizando colección de seguimiento de tiempo...\n";
        
        $timeTracking = $this->database->time_tracking;
        
        // Agregar campo de costo a registros existentes
        $result = $timeTracking->updateMany(
            ['cost' => ['$exists' => false]],
            [
                '$set' => [
                    'cost' => 0.00
                ]
            ]
        );
        
        echo "   - Registros de tiempo actualizados con campo de costo: {$result->getModifiedCount()}\n";
        
        // Recalcular costos para registros de tiempo existentes
        $this->recalculateTimeTrackingCosts();
    }
    
    /**
     * Recalcular costos de tareas
     */
    private function recalculateTaskCosts() {
        echo "💰 Recalculando costos de tareas...\n";
        
        $tasks = $this->database->tasks->find();
        $updatedTasks = 0;
        
        foreach ($tasks as $task) {
            // Obtener la empresa de la tarea
            $company = $this->database->companies->findOne(['_id' => $task['company_id']]);
            if (!$company || !isset($company['cost_per_hour'])) {
                continue;
            }
            
            // Calcular tiempo total de la tarea
            $timeRecords = $this->database->time_tracking->find(['task_id' => $task['_id']]);
            $totalMinutes = 0;
            $totalCost = 0;
            
            foreach ($timeRecords as $record) {
                if (isset($record['duration_minutes'])) {
                    $totalMinutes += $record['duration_minutes'];
                }
            }
            
            $totalHours = $totalMinutes / 60;
            $totalCost = $totalHours * $company['cost_per_hour'];
            
            // Actualizar la tarea
            $this->database->tasks->updateOne(
                ['_id' => $task['_id']],
                [
                    '$set' => [
                        'actual_hours' => round($totalHours, 2),
                        'total_cost' => round($totalCost, 2),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            $updatedTasks++;
        }
        
        echo "   - Costos recalculados para {$updatedTasks} tareas\n";
    }
    
    /**
     * Recalcular costos de seguimiento de tiempo
     */
    private function recalculateTimeTrackingCosts() {
        echo "⏰ Recalculando costos de seguimiento de tiempo...\n";
        
        $timeRecords = $this->database->time_tracking->find();
        $updatedRecords = 0;
        
        foreach ($timeRecords as $record) {
            // Obtener la tarea y la empresa
            $task = $this->database->tasks->findOne(['_id' => $record['task_id']]);
            if (!$task) continue;
            
            $company = $this->database->companies->findOne(['_id' => $task['company_id']]);
            if (!$company || !isset($company['cost_per_hour'])) {
                continue;
            }
            
            // Calcular costo del registro
            $hours = ($record['duration_minutes'] ?? 0) / 60;
            $cost = $hours * $company['cost_per_hour'];
            
            // Actualizar el registro
            $this->database->time_tracking->updateOne(
                ['_id' => $record['_id']],
                [
                    '$set' => [
                        'cost' => round($cost, 2)
                    ]
                ]
            );
            
            $updatedRecords++;
        }
        
        echo "   - Costos recalculados para {$updatedRecords} registros de tiempo\n";
    }
    
    /**
     * Agregar configuración del sistema
     */
    private function addSystemConfiguration() {
        echo "⚙️ Agregando configuración del sistema...\n";
        
        $config = $this->database->system_config;
        
        $systemConfigs = [
            [
                'key' => 'default_currency',
                'value' => 'EUR',
                'description' => 'Moneda por defecto del sistema'
            ],
            [
                'key' => 'default_cost_per_hour',
                'value' => 25.00,
                'description' => 'Costo por hora por defecto para nuevas empresas'
            ],
            [
                'key' => 'cost_tracking_enabled',
                'value' => true,
                'description' => 'Habilitar seguimiento de costos'
            ],
            [
                'key' => 'auto_calculate_costs',
                'value' => true,
                'description' => 'Calcular costos automáticamente'
            ]
        ];
        
        foreach ($systemConfigs as $configItem) {
            $existing = $config->findOne(['key' => $configItem['key']]);
            if (!$existing) {
                $config->insertOne([
                    'key' => $configItem['key'],
                    'value' => $configItem['value'],
                    'description' => $configItem['description'],
                    'updated_at' => new MongoDB\BSON\UTCDateTime(),
                    'updated_by' => null
                ]);
                echo "   - Configuración agregada: {$configItem['key']}\n";
            }
        }
    }
    
    /**
     * Crear índices necesarios
     */
    private function createIndexes() {
        echo "🔍 Creando índices para optimización...\n";
        
        try {
            // Índices para empresas
            $this->database->companies->createIndex(['cost_per_hour' => 1]);
            $this->database->companies->createIndex(['currency' => 1]);
            
            // Índices para tareas
            $this->database->tasks->createIndex(['total_cost' => -1]);
            $this->database->tasks->createIndex(['actual_hours' => -1]);
            
            // Índices para seguimiento de tiempo
            $this->database->time_tracking->createIndex(['cost' => -1]);
            $this->database->time_tracking->createIndex(['task_id' => 1, 'cost' => -1]);
            
            echo "   - Índices creados exitosamente\n";
        } catch (Exception $e) {
            echo "   - Advertencia: Algunos índices ya existían\n";
        }
    }
    
    /**
     * Validar datos después de la actualización
     */
    private function validateData() {
        echo "✅ Validando datos actualizados...\n";
        
        // Contar empresas con costo configurado
        $companiesWithCost = $this->database->companies->countDocuments(['cost_per_hour' => ['$exists' => true]]);
        echo "   - Empresas con costo por hora configurado: {$companiesWithCost}\n";
        
        // Contar tareas con costo calculado
        $tasksWithCost = $this->database->tasks->countDocuments(['total_cost' => ['$exists' => true]]);
        echo "   - Tareas con costo calculado: {$tasksWithCost}\n";
        
        // Contar registros de tiempo con costo
        $timeRecordsWithCost = $this->database->time_tracking->countDocuments(['cost' => ['$exists' => true]]);
        echo "   - Registros de tiempo con costo: {$timeRecordsWithCost}\n";
        
        // Calcular costo total del sistema
        $pipeline = [
            ['$group' => ['_id' => null, 'total' => ['$sum' => '$total_cost']]]
        ];
        $result = $this->database->tasks->aggregate($pipeline)->toArray();
        $totalSystemCost = $result[0]['total'] ?? 0;
        echo "   - Costo total del sistema: €" . number_format($totalSystemCost, 2) . "\n";
    }
    
    /**
     * Crear empresa de ejemplo si no existe ninguna
     */
    public function createSampleCompany() {
        $companiesCount = $this->database->companies->countDocuments();
        
        if ($companiesCount == 0) {
            echo "🏢 Creando empresa de ejemplo...\n";
            
            $sampleCompany = [
                'name' => 'Empresa Ejemplo',
                'description' => 'Empresa de ejemplo para pruebas del sistema',
                'cost_per_hour' => 30.00,
                'currency' => 'EUR',
                'contact' => [
                    'email' => 'contacto@ejemplo.com',
                    'phone' => '+34 123 456 789',
                    'address' => 'Calle Ejemplo, 123, Madrid'
                ],
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'active' => true
            ];
            
            $this->database->companies->insertOne($sampleCompany);
            echo "   - Empresa de ejemplo creada\n";
        }
    }
}

// Ejecutar el script
try {
    $updater = new DatabaseCostTrackingUpdater();
    $updater->runAllUpdates();
    $updater->createSampleCompany();
    
    echo "\n🎉 ¡Base de datos actualizada exitosamente!\n";
    echo "Ahora puedes usar todas las funcionalidades de seguimiento de costos.\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error durante la actualización: " . $e->getMessage() . "\n";
    echo "💡 Sugerencias:\n";
    echo "   - Verifica que la base de datos esté ejecutándose\n";
    echo "   - Revisa la configuración de conexión\n";
    echo "   - Asegúrate de tener permisos de escritura\n";
    exit(1);
}
?>