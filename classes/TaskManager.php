<?php
require_once __DIR__ . '/../config/mongodb.php';

/**
 * Gestor de tareas para BIG PLAN
 * Maneja tareas con estados: NO INICIADA, EN PROGRESO, PAUSADA, COMPLETADA
 */
class TaskManager {
    private $db;
    private $tasks_collection;
    private $time_tracking_collection;
    
    // Estados de tareas
    const STATUS_NOT_STARTED = 'NO_INICIADA';
    const STATUS_IN_PROGRESS = 'EN_PROGRESO';
    const STATUS_PAUSED = 'PAUSADA';
    const STATUS_COMPLETED = 'COMPLETADA';
    
    public function __construct() {
        $this->db = MongoDBConfig::getInstance();
        $this->tasks_collection = $this->db->getCollection('tasks');
        $this->time_tracking_collection = $this->db->getCollection('time_tracking');
    }
    
    /**
     * Crear nueva tarea
     */
    public function createTask($taskData, $adminId) {
        try {
            // Validar datos requeridos
            if (empty($taskData['title']) || empty($taskData['company_id']) || empty($taskData['assigned_users'])) {
                throw new Exception("Faltan datos requeridos: título, empresa y usuarios asignados");
            }
            
            // Preparar datos de la tarea
            $task = [
                'title' => trim($taskData['title']),
                'description' => $taskData['description'] ?? '',
                'company_id' => $taskData['company_id'],
                'assigned_users' => is_array($taskData['assigned_users']) ? $taskData['assigned_users'] : [$taskData['assigned_users']],
                'status' => self::STATUS_NOT_STARTED,
                'priority' => $taskData['priority'] ?? 'medium', // low, medium, high
                'estimated_hours' => isset($taskData['estimated_hours']) ? (float)$taskData['estimated_hours'] : null,
                'due_date' => isset($taskData['due_date']) ? new DateTime($taskData['due_date']) : null,
                'created_by' => $adminId,
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
                'status_history' => [
                    [
                        'status' => self::STATUS_NOT_STARTED,
                        'changed_by' => $adminId,
                        'changed_at' => new DateTime(),
                        'comment' => 'Tarea creada'
                    ]
                ],
                'total_time_spent' => 0, // en minutos
                'total_cost' => 0,
                'active' => true
            ];
            
            $result = $this->tasks_collection->insertOne($task);
            
            if ($result->getInsertedCount() > 0) {
                return [
                    'success' => true,
                    'task_id' => (string)$result->getInsertedId(),
                    'message' => 'Tarea creada exitosamente'
                ];
            } else {
                throw new Exception("Error al crear la tarea");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Iniciar tarea
     */
    public function startTask($taskId, $userId) {
        try {
            $task = $this->getTaskById($taskId);
            if (!$task) {
                throw new Exception("Tarea no encontrada");
            }
            
            // Verificar que el usuario esté asignado a la tarea
            if (!in_array($userId, $task['assigned_users'])) {
                throw new Exception("No tienes permisos para trabajar en esta tarea");
            }
            
            // Verificar estado actual
            if ($task['status'] === self::STATUS_COMPLETED) {
                throw new Exception("No se puede iniciar una tarea completada");
            }
            
            // Verificar si el usuario ya tiene una sesión activa en esta tarea
            $activeSession = $this->getActiveTimeSession($taskId, $userId);
            if ($activeSession) {
                throw new Exception("Ya tienes una sesión activa en esta tarea");
            }
            
            // Crear nueva sesión de tiempo
            $timeSession = [
                'task_id' => $taskId,
                'user_id' => $userId,
                'start_time' => new DateTime(),
                'end_time' => null,
                'duration_minutes' => 0,
                'status' => 'active',
                'created_at' => new DateTime()
            ];
            
            $this->time_tracking_collection->insertOne($timeSession);
            
            // Actualizar estado de la tarea si no está en progreso
            if ($task['status'] !== self::STATUS_IN_PROGRESS) {
                $this->updateTaskStatus($taskId, self::STATUS_IN_PROGRESS, $userId, 'Tarea iniciada');
            }
            
            return [
                'success' => true,
                'message' => 'Tarea iniciada correctamente'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Pausar tarea
     */
    public function pauseTask($taskId, $userId) {
        try {
            // Finalizar sesión activa
            $result = $this->stopTimeSession($taskId, $userId);
            if (!$result['success']) {
                return $result;
            }
            
            // Actualizar estado de la tarea
            $this->updateTaskStatus($taskId, self::STATUS_PAUSED, $userId, 'Tarea pausada');
            
            return [
                'success' => true,
                'message' => 'Tarea pausada correctamente'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reanudar tarea
     */
    public function resumeTask($taskId, $userId) {
        try {
            $task = $this->getTaskById($taskId);
            if (!$task) {
                throw new Exception("Tarea no encontrada");
            }
            
            if ($task['status'] !== self::STATUS_PAUSED) {
                throw new Exception("Solo se pueden reanudar tareas pausadas");
            }
            
            return $this->startTask($taskId, $userId);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Completar tarea
     */
    public function completeTask($taskId, $userId, $manualTime = null) {
        try {
            $task = $this->getTaskById($taskId);
            if (!$task) {
                throw new Exception("Tarea no encontrada");
            }
            
            // Si hay una sesión activa, finalizarla
            $activeSession = $this->getActiveTimeSession($taskId, $userId);
            if ($activeSession) {
                $this->stopTimeSession($taskId, $userId);
            }
            
            // Si se proporciona tiempo manual y no hay tiempo registrado
            if ($manualTime && $task['total_time_spent'] == 0) {
                $this->addManualTime($taskId, $userId, $manualTime);
            }
            
            // Actualizar estado de la tarea
            $this->updateTaskStatus($taskId, self::STATUS_COMPLETED, $userId, 'Tarea completada');
            
            // Calcular costo total final
            $this->calculateTaskCost($taskId);
            
            return [
                'success' => true,
                'message' => 'Tarea completada correctamente'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener tarea por ID
     */
    public function getTaskById($taskId) {
        try {
            return $this->tasks_collection->findOne(['_id' => $taskId, 'active' => true]);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Obtener tareas por usuario
     */
    public function getTasksByUser($userId, $status = null) {
        try {
            $query = [
                'assigned_users' => $userId,
                'active' => true
            ];
            
            if ($status) {
                $query['status'] = $status;
            }
            
            $tasks = $this->tasks_collection->find($query, [
                'sort' => ['created_at' => -1]
            ]);
            
            return [
                'success' => true,
                'tasks' => iterator_to_array($tasks)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener tareas por empresa
     */
    public function getTasksByCompany($companyId, $status = null) {
        try {
            $query = [
                'company_id' => $companyId,
                'active' => true
            ];
            
            if ($status) {
                $query['status'] = $status;
            }
            
            $tasks = $this->tasks_collection->find($query, [
                'sort' => ['created_at' => -1]
            ]);
            
            return [
                'success' => true,
                'tasks' => iterator_to_array($tasks)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener tareas próximas a vencer
     */
    public function getTasksNearDeadline($userId, $days = 3) {
        try {
            $deadline = new DateTime();
            $deadline->add(new DateInterval("P{$days}D"));
            
            $tasks = $this->tasks_collection->find([
                'assigned_users' => $userId,
                'active' => true,
                'status' => ['$ne' => self::STATUS_COMPLETED],
                'due_date' => [
                    '$lte' => $deadline,
                    '$gte' => new DateTime()
                ]
            ], [
                'sort' => ['due_date' => 1]
            ]);
            
            return [
                'success' => true,
                'tasks' => iterator_to_array($tasks)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener tareas vencidas
     */
    public function getOverdueTasks($userId) {
        try {
            $now = new DateTime();
            
            $tasks = $this->tasks_collection->find([
                'assigned_users' => $userId,
                'active' => true,
                'status' => ['$ne' => self::STATUS_COMPLETED],
                'due_date' => ['$lt' => $now]
            ], [
                'sort' => ['due_date' => 1]
            ]);
            
            return [
                'success' => true,
                'tasks' => iterator_to_array($tasks)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar estado de tarea
     */
    private function updateTaskStatus($taskId, $newStatus, $userId, $comment = '') {
        $statusUpdate = [
            'status' => $newStatus,
            'changed_by' => $userId,
            'changed_at' => new DateTime(),
            'comment' => $comment
        ];
        
        $this->tasks_collection->updateOne(
            ['_id' => $taskId],
            [
                '$set' => [
                    'status' => $newStatus,
                    'updated_at' => new DateTime()
                ],
                '$push' => ['status_history' => $statusUpdate]
            ]
        );
    }
    
    /**
     * Obtener sesión de tiempo activa
     */
    private function getActiveTimeSession($taskId, $userId) {
        return $this->time_tracking_collection->findOne([
            'task_id' => $taskId,
            'user_id' => $userId,
            'status' => 'active'
        ]);
    }
    
    /**
     * Finalizar sesión de tiempo
     */
    private function stopTimeSession($taskId, $userId) {
        try {
            $activeSession = $this->getActiveTimeSession($taskId, $userId);
            if (!$activeSession) {
                throw new Exception("No hay sesión activa para finalizar");
            }
            
            $endTime = new DateTime();
            $startTime = $activeSession['start_time'];
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            $durationMinutes = round($duration / 60);
            
            // Actualizar sesión
            $this->time_tracking_collection->updateOne(
                ['_id' => $activeSession['_id']],
                ['$set' => [
                    'end_time' => $endTime,
                    'duration_minutes' => $durationMinutes,
                    'status' => 'completed'
                ]]
            );
            
            // Actualizar tiempo total de la tarea
            $this->updateTaskTotalTime($taskId);
            
            return [
                'success' => true,
                'duration_minutes' => $durationMinutes
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Agregar tiempo manual
     */
    private function addManualTime($taskId, $userId, $minutes) {
        $timeSession = [
            'task_id' => $taskId,
            'user_id' => $userId,
            'start_time' => new DateTime(),
            'end_time' => new DateTime(),
            'duration_minutes' => (int)$minutes,
            'status' => 'manual',
            'created_at' => new DateTime()
        ];
        
        $this->time_tracking_collection->insertOne($timeSession);
        $this->updateTaskTotalTime($taskId);
    }
    
    /**
     * Actualizar tiempo total de tarea
     */
    private function updateTaskTotalTime($taskId) {
        $pipeline = [
            ['$match' => ['task_id' => $taskId]],
            ['$group' => [
                '_id' => '$task_id',
                'total_minutes' => ['$sum' => '$duration_minutes']
            ]]
        ];
        
        $result = $this->time_tracking_collection->aggregate($pipeline);
        $totalTime = 0;
        
        foreach ($result as $doc) {
            $totalTime = $doc['total_minutes'];
            break;
        }
        
        $this->tasks_collection->updateOne(
            ['_id' => $taskId],
            ['$set' => ['total_time_spent' => $totalTime]]
        );
        
        // Calcular costo
        $this->calculateTaskCost($taskId);
    }
    
    /**
     * Calcular costo de tarea
     */
    private function calculateTaskCost($taskId) {
        $task = $this->getTaskById($taskId);
        if (!$task) return;
        
        $companyManager = new CompanyManager();
        $company = $companyManager->getCompanyById($task['company_id']);
        
        if ($company) {
            $hours = $task['total_time_spent'] / 60;
            $totalCost = $hours * $company['cost_per_hour'];
            
            $this->tasks_collection->updateOne(
                ['_id' => $taskId],
                ['$set' => ['total_cost' => $totalCost]]
            );
        }
    }
}
?>