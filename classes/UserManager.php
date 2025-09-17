<?php
require_once __DIR__ . '/../config/mongodb.php';

/**
 * Gestor de usuarios para BIG PLAN
 * Maneja autenticación, perfiles y roles
 */
class UserManager {
    private $db;
    private $users_collection;
    
    public function __construct() {
        $this->db = MongoDBConfig::getInstance();
        $this->users_collection = $this->db->getCollection('users');
    }
    
    /**
     * Crear nuevo usuario
     */
    public function createUser($userData) {
        try {
            // Validar datos requeridos
            if (empty($userData['username']) || empty($userData['email']) || empty($userData['password'])) {
                throw new Exception("Faltan datos requeridos");
            }
            
            // Verificar si el usuario ya existe
            if ($this->getUserByEmail($userData['email'])) {
                throw new Exception("El email ya está registrado");
            }
            
            if ($this->getUserByUsername($userData['username'])) {
                throw new Exception("El nombre de usuario ya existe");
            }
            
            // Preparar datos del usuario
            $user = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
                'role' => $userData['role'] ?? 'user',
                'profile' => [
                    'first_name' => $userData['first_name'] ?? '',
                    'last_name' => $userData['last_name'] ?? '',
                    'avatar' => $userData['avatar'] ?? '/assets/images/default-avatar.png',
                    'background' => $userData['background'] ?? '/assets/images/default-bg.jpg',
                    'phone' => $userData['phone'] ?? '',
                    'position' => $userData['position'] ?? ''
                ],
                'companies' => $userData['companies'] ?? [],
                'preferences' => [
                    'language' => 'es',
                    'timezone' => 'Europe/Madrid',
                    'notifications' => true
                ],
                'last_login' => null,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'active' => true
            ];
            
            $result = $this->users_collection->insertOne($user);
            
            if ($result->getInsertedCount() > 0) {
                return [
                    'success' => true,
                    'user_id' => (string)$result->getInsertedId(),
                    'message' => 'Usuario creado exitosamente'
                ];
            } else {
                throw new Exception("Error al crear el usuario");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Autenticar usuario
     */
    public function authenticateUser($email, $password) {
        try {
            $user = $this->getUserByEmail($email);
            
            if (!$user) {
                throw new Exception("Usuario no encontrado");
            }
            
            if (!$user['active']) {
                throw new Exception("Cuenta desactivada");
            }
            
            if (!password_verify($password, $user['password'])) {
                throw new Exception("Contraseña incorrecta");
            }
            
            // Actualizar último login
            $this->updateLastLogin($user['_id']);
            
            // Preparar datos de sesión (sin contraseña)
            unset($user['password']);
            
            return [
                'success' => true,
                'user' => $user,
                'message' => 'Autenticación exitosa'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener usuario por email
     */
    public function getUserByEmail($email) {
        return $this->users_collection->findOne(['email' => $email]);
    }
    
    /**
     * Obtener usuario por username
     */
    public function getUserByUsername($username) {
        return $this->users_collection->findOne(['username' => $username]);
    }
    
    /**
     * Obtener usuario por ID
     */
    public function getUserById($userId) {
        try {
            $objectId = new MongoDB\BSON\ObjectId($userId);
            return $this->users_collection->findOne(['_id' => $objectId]);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Actualizar perfil de usuario
     */
    public function updateUserProfile($userId, $profileData) {
        try {
            $objectId = new MongoDB\BSON\ObjectId($userId);
            
            $updateData = [
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Actualizar campos del perfil
            if (isset($profileData['first_name'])) {
                $updateData['profile.first_name'] = $profileData['first_name'];
            }
            if (isset($profileData['last_name'])) {
                $updateData['profile.last_name'] = $profileData['last_name'];
            }
            if (isset($profileData['avatar'])) {
                $updateData['profile.avatar'] = $profileData['avatar'];
            }
            if (isset($profileData['background'])) {
                $updateData['profile.background'] = $profileData['background'];
            }
            if (isset($profileData['phone'])) {
                $updateData['profile.phone'] = $profileData['phone'];
            }
            if (isset($profileData['position'])) {
                $updateData['profile.position'] = $profileData['position'];
            }
            
            $result = $this->users_collection->updateOne(
                ['_id' => $objectId],
                ['$set' => $updateData]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'message' => $result->getModifiedCount() > 0 ? 'Perfil actualizado' : 'No se realizaron cambios'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cambiar contraseña
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->getUserById($userId);
            
            if (!$user) {
                throw new Exception("Usuario no encontrado");
            }
            
            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception("Contraseña actual incorrecta");
            }
            
            $objectId = new MongoDB\BSON\ObjectId($userId);
            $result = $this->users_collection->updateOne(
                ['_id' => $objectId],
                ['$set' => [
                    'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'message' => 'Contraseña actualizada exitosamente'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener todos los usuarios (para admin)
     */
    public function getAllUsers($filters = []) {
        try {
            $query = ['active' => true];
            
            if (isset($filters['role'])) {
                $query['role'] = $filters['role'];
            }
            
            if (isset($filters['company_id'])) {
                $query['companies'] = ['$in' => [$filters['company_id']]];
            }
            
            $users = $this->users_collection->find($query, [
                'projection' => ['password' => 0] // Excluir contraseña
            ])->toArray();
            
            return [
                'success' => true,
                'users' => $users
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Asignar empresas a usuario
     */
    public function assignCompaniesToUser($userId, $companyIds) {
        try {
            $objectId = new MongoDB\BSON\ObjectId($userId);
            
            $result = $this->users_collection->updateOne(
                ['_id' => $objectId],
                ['$set' => [
                    'companies' => $companyIds,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'message' => 'Empresas asignadas exitosamente'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar último login
     */
    private function updateLastLogin($userId) {
        $this->users_collection->updateOne(
            ['_id' => $userId],
            ['$set' => ['last_login' => new MongoDB\BSON\UTCDateTime()]]
        );
    }
    
    /**
     * Desactivar usuario
     */
    public function deactivateUser($userId) {
        try {
            $objectId = new MongoDB\BSON\ObjectId($userId);
            
            $result = $this->users_collection->updateOne(
                ['_id' => $objectId],
                ['$set' => [
                    'active' => false,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'message' => 'Usuario desactivado'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar si el usuario es admin
     */
    public function isAdmin($userId) {
        $user = $this->getUserById($userId);
        return $user && $user['role'] === 'admin';
    }
}
?>