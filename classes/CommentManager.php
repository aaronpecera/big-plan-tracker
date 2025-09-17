<?php
require_once __DIR__ . '/../config/mongodb.php';

/**
 * Gestor de comentarios y actualizaciones para tareas en BIG PLAN
 */
class CommentManager {
    private $db;
    private $comments_collection;
    
    public function __construct() {
        $this->db = MongoDBConfig::getInstance();
        $this->comments_collection = $this->db->getCollection('task_comments');
    }
    
    /**
     * Agregar comentario a una tarea
     */
    public function addComment($taskId, $userId, $content, $type = 'comment') {
        try {
            if (empty($content)) {
                throw new Exception("El contenido del comentario no puede estar vacío");
            }
            
            // Verificar que la tarea existe
            $taskManager = new TaskManager();
            $task = $taskManager->getTaskById($taskId);
            if (!$task) {
                throw new Exception("Tarea no encontrada");
            }
            
            // Verificar permisos (usuario asignado o admin)
            $userManager = new UserManager();
            $user = $userManager->getUserById($userId);
            if (!$user) {
                throw new Exception("Usuario no encontrado");
            }
            
            $hasPermission = in_array($userId, $task['assigned_users']) || 
                           $user['role'] === 'admin' || 
                           $task['created_by'] === $userId;
            
            if (!$hasPermission) {
                throw new Exception("No tienes permisos para comentar en esta tarea");
            }
            
            $comment = [
                'task_id' => $taskId,
                'user_id' => $userId,
                'user_name' => $user['name'],
                'user_avatar' => $user['avatar'] ?? '/assets/images/default-avatar.png',
                'content' => trim($content),
                'type' => $type, // comment, update, system
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
                'edited' => false,
                'attachments' => [],
                'mentions' => $this->extractMentions($content),
                'active' => true
            ];
            
            $result = $this->comments_collection->insertOne($comment);
            
            if ($result->getInsertedCount() > 0) {
                // Notificar a usuarios mencionados
                $this->notifyMentionedUsers($comment['mentions'], $taskId, $userId);
                
                return [
                    'success' => true,
                    'comment_id' => (string)$result->getInsertedId(),
                    'message' => 'Comentario agregado exitosamente'
                ];
            } else {
                throw new Exception("Error al agregar el comentario");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener comentarios de una tarea
     */
    public function getTaskComments($taskId, $limit = 50, $offset = 0) {
        try {
            $comments = $this->comments_collection->find(
                ['task_id' => $taskId, 'active' => true],
                [
                    'sort' => ['created_at' => 1],
                    'limit' => $limit,
                    'skip' => $offset
                ]
            );
            
            return [
                'success' => true,
                'comments' => iterator_to_array($comments)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Editar comentario
     */
    public function editComment($commentId, $userId, $newContent) {
        try {
            if (empty($newContent)) {
                throw new Exception("El contenido no puede estar vacío");
            }
            
            $comment = $this->comments_collection->findOne(['_id' => $commentId, 'active' => true]);
            if (!$comment) {
                throw new Exception("Comentario no encontrado");
            }
            
            // Solo el autor puede editar su comentario
            if ($comment['user_id'] !== $userId) {
                throw new Exception("Solo puedes editar tus propios comentarios");
            }
            
            // No permitir editar comentarios del sistema
            if ($comment['type'] === 'system') {
                throw new Exception("No se pueden editar comentarios del sistema");
            }
            
            $result = $this->comments_collection->updateOne(
                ['_id' => $commentId],
                ['$set' => [
                    'content' => trim($newContent),
                    'updated_at' => new DateTime(),
                    'edited' => true,
                    'mentions' => $this->extractMentions($newContent)
                ]]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'message' => 'Comentario actualizado'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Eliminar comentario
     */
    public function deleteComment($commentId, $userId) {
        try {
            $comment = $this->comments_collection->findOne(['_id' => $commentId, 'active' => true]);
            if (!$comment) {
                throw new Exception("Comentario no encontrado");
            }
            
            // Verificar permisos (autor o admin)
            $userManager = new UserManager();
            $user = $userManager->getUserById($userId);
            
            $canDelete = $comment['user_id'] === $userId || $user['role'] === 'admin';
            
            if (!$canDelete) {
                throw new Exception("No tienes permisos para eliminar este comentario");
            }
            
            $result = $this->comments_collection->updateOne(
                ['_id' => $commentId],
                ['$set' => [
                    'active' => false,
                    'updated_at' => new DateTime()
                ]]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'message' => 'Comentario eliminado'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Agregar actualización del sistema
     */
    public function addSystemUpdate($taskId, $userId, $action, $details = []) {
        $systemMessages = [
            'task_created' => 'creó la tarea',
            'task_started' => 'inició la tarea',
            'task_paused' => 'pausó la tarea',
            'task_resumed' => 'reanudó la tarea',
            'task_completed' => 'completó la tarea',
            'task_assigned' => 'asignó la tarea',
            'task_updated' => 'actualizó la tarea',
            'due_date_changed' => 'cambió la fecha de vencimiento',
            'priority_changed' => 'cambió la prioridad',
            'user_added' => 'agregó un usuario a la tarea',
            'user_removed' => 'removió un usuario de la tarea'
        ];
        
        $message = $systemMessages[$action] ?? 'realizó una acción';
        
        // Agregar detalles específicos
        if (!empty($details)) {
            if (isset($details['from']) && isset($details['to'])) {
                $message .= " de '{$details['from']}' a '{$details['to']}'";
            } elseif (isset($details['value'])) {
                $message .= ": {$details['value']}";
            }
        }
        
        return $this->addComment($taskId, $userId, $message, 'system');
    }
    
    /**
     * Agregar archivo adjunto a comentario
     */
    public function addAttachment($commentId, $userId, $fileName, $filePath, $fileSize) {
        try {
            $comment = $this->comments_collection->findOne(['_id' => $commentId, 'active' => true]);
            if (!$comment) {
                throw new Exception("Comentario no encontrado");
            }
            
            if ($comment['user_id'] !== $userId) {
                throw new Exception("Solo puedes agregar archivos a tus comentarios");
            }
            
            $attachment = [
                'id' => uniqid(),
                'name' => $fileName,
                'path' => $filePath,
                'size' => $fileSize,
                'uploaded_at' => new DateTime()
            ];
            
            $result = $this->comments_collection->updateOne(
                ['_id' => $commentId],
                ['$push' => ['attachments' => $attachment]]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'attachment_id' => $attachment['id'],
                'message' => 'Archivo adjunto agregado'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de comentarios
     */
    public function getCommentStats($taskId) {
        try {
            $pipeline = [
                ['$match' => ['task_id' => $taskId, 'active' => true]],
                ['$group' => [
                    '_id' => '$type',
                    'count' => ['$sum' => 1]
                ]]
            ];
            
            $stats = $this->comments_collection->aggregate($pipeline);
            $result = [
                'total' => 0,
                'comments' => 0,
                'updates' => 0,
                'system' => 0
            ];
            
            foreach ($stats as $stat) {
                $result[$stat['_id']] = $stat['count'];
                $result['total'] += $stat['count'];
            }
            
            return [
                'success' => true,
                'stats' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar comentarios
     */
    public function searchComments($taskId, $searchTerm) {
        try {
            $regex = new MongoDB\BSON\Regex($searchTerm, 'i');
            
            $comments = $this->comments_collection->find([
                'task_id' => $taskId,
                'active' => true,
                'content' => $regex
            ], [
                'sort' => ['created_at' => -1]
            ]);
            
            return [
                'success' => true,
                'comments' => iterator_to_array($comments)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extraer menciones de usuarios (@usuario)
     */
    private function extractMentions($content) {
        preg_match_all('/@(\w+)/', $content, $matches);
        return array_unique($matches[1]);
    }
    
    /**
     * Notificar usuarios mencionados
     */
    private function notifyMentionedUsers($mentions, $taskId, $fromUserId) {
        if (empty($mentions)) return;
        
        // Aquí se implementaría el sistema de notificaciones
        // Por ahora solo registramos la mención
        foreach ($mentions as $username) {
            // Buscar usuario por nombre de usuario
            $userManager = new UserManager();
            // Implementar búsqueda por username cuando esté disponible
        }
    }
    
    /**
     * Obtener comentarios recientes del usuario
     */
    public function getUserRecentComments($userId, $limit = 10) {
        try {
            $comments = $this->comments_collection->find(
                ['user_id' => $userId, 'active' => true],
                [
                    'sort' => ['created_at' => -1],
                    'limit' => $limit
                ]
            );
            
            return [
                'success' => true,
                'comments' => iterator_to_array($comments)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>