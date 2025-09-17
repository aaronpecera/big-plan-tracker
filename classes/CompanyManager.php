<?php
require_once __DIR__ . '/../config/mongodb.php';

/**
 * Gestor de empresas para BIG PLAN
 * Maneja empresas y costos por hora
 */
class CompanyManager {
    private $db;
    private $companies_collection;
    
    public function __construct() {
        $this->db = MongoDBConfig::getInstance();
        $this->companies_collection = $this->db->getCollection('companies');
    }
    
    /**
     * Crear nueva empresa
     */
    public function createCompany($companyData, $adminId) {
        try {
            // Validar datos requeridos
            if (empty($companyData['name']) || empty($companyData['cost_per_hour'])) {
                throw new Exception("Faltan datos requeridos: nombre y costo por hora");
            }
            
            // Verificar si la empresa ya existe
            if ($this->getCompanyByName($companyData['name'])) {
                throw new Exception("Ya existe una empresa con ese nombre");
            }
            
            // Preparar datos de la empresa
            $company = [
                'name' => trim($companyData['name']),
                'description' => $companyData['description'] ?? '',
                'cost_per_hour' => (float)$companyData['cost_per_hour'],
                'currency' => $companyData['currency'] ?? 'EUR',
                'logo' => $companyData['logo'] ?? '/assets/images/default-company-logo.png',
                'contact' => [
                    'email' => $companyData['contact_email'] ?? '',
                    'phone' => $companyData['contact_phone'] ?? '',
                    'address' => $companyData['contact_address'] ?? ''
                ],
                'created_by' => $adminId,
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
                'active' => true
            ];
            
            $result = $this->companies_collection->insertOne($company);
            
            if ($result->getInsertedCount() > 0) {
                return [
                    'success' => true,
                    'company_id' => (string)$result->getInsertedId(),
                    'message' => 'Empresa creada exitosamente'
                ];
            } else {
                throw new Exception("Error al crear la empresa");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener empresa por nombre
     */
    public function getCompanyByName($name) {
        return $this->companies_collection->findOne(['name' => $name, 'active' => true]);
    }
    
    /**
     * Obtener empresa por ID
     */
    public function getCompanyById($companyId) {
        try {
            return $this->companies_collection->findOne(['_id' => $companyId, 'active' => true]);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Obtener todas las empresas
     */
    public function getAllCompanies($activeOnly = true) {
        try {
            $query = [];
            if ($activeOnly) {
                $query['active'] = true;
            }
            
            $companies = $this->companies_collection->find($query)->toArray();
            
            return [
                'success' => true,
                'companies' => $companies
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar empresa
     */
    public function updateCompany($companyId, $updateData) {
        try {
            $updateFields = [
                'updated_at' => new DateTime()
            ];
            
            // Campos permitidos para actualizar
            $allowedFields = ['name', 'description', 'cost_per_hour', 'currency', 'logo'];
            foreach ($allowedFields as $field) {
                if (isset($updateData[$field])) {
                    if ($field === 'cost_per_hour') {
                        $updateFields[$field] = (float)$updateData[$field];
                    } else {
                        $updateFields[$field] = $updateData[$field];
                    }
                }
            }
            
            // Actualizar contacto si se proporciona
            if (isset($updateData['contact'])) {
                if (isset($updateData['contact']['email'])) {
                    $updateFields['contact.email'] = $updateData['contact']['email'];
                }
                if (isset($updateData['contact']['phone'])) {
                    $updateFields['contact.phone'] = $updateData['contact']['phone'];
                }
                if (isset($updateData['contact']['address'])) {
                    $updateFields['contact.address'] = $updateData['contact']['address'];
                }
            }
            
            $result = $this->companies_collection->updateOne(
                ['_id' => $companyId],
                ['$set' => $updateFields]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'message' => $result->getModifiedCount() > 0 ? 'Empresa actualizada' : 'No se realizaron cambios'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Desactivar empresa
     */
    public function deactivateCompany($companyId) {
        try {
            $result = $this->companies_collection->updateOne(
                ['_id' => $companyId],
                ['$set' => [
                    'active' => false,
                    'updated_at' => new DateTime()
                ]]
            );
            
            return [
                'success' => $result->getModifiedCount() > 0,
                'message' => 'Empresa desactivada'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de empresa
     */
    public function getCompanyStats($companyId) {
        try {
            // Obtener información básica de la empresa
            $company = $this->getCompanyById($companyId);
            if (!$company) {
                throw new Exception("Empresa no encontrada");
            }
            
            // Aquí se pueden agregar más estadísticas cuando tengamos el TaskManager
            $stats = [
                'company' => $company,
                'total_tasks' => 0,
                'active_tasks' => 0,
                'completed_tasks' => 0,
                'total_hours' => 0,
                'total_cost' => 0,
                'active_users' => 0
            ];
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar empresas
     */
    public function searchCompanies($searchTerm) {
        try {
            $regex = new MongoDB\BSON\Regex($searchTerm, 'i');
            
            $companies = $this->companies_collection->find([
                '$and' => [
                    ['active' => true],
                    ['$or' => [
                        ['name' => $regex],
                        ['description' => $regex]
                    ]]
                ]
            ])->toArray();
            
            return [
                'success' => true,
                'companies' => $companies
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener empresas por usuario
     */
    public function getCompaniesByUser($userId) {
        try {
            // Primero obtenemos las empresas asignadas al usuario
            $userManager = new UserManager();
            $user = $userManager->getUserById($userId);
            
            if (!$user || empty($user['companies'])) {
                return [
                    'success' => true,
                    'companies' => []
                ];
            }
            
            $companies = $this->companies_collection->find([
                '_id' => ['$in' => $user['companies']],
                'active' => true
            ])->toArray();
            
            return [
                'success' => true,
                'companies' => $companies
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validar costo por hora
     */
    public function validateCostPerHour($cost) {
        $cost = (float)$cost;
        return $cost > 0 && $cost <= 1000; // Máximo 1000 por hora
    }
    
    /**
     * Get companies where user has tasks assigned
     */
    public function getUserCompanies($userId) {
        try {
            // Get company IDs from user's tasks
            $pipeline = [
                [
                    '$match' => [
                        'assigned_to' => ['$in' => [new MongoDB\BSON\ObjectId($userId)]]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$company_id'
                    ]
                ]
            ];
            
            $taskCompanies = $this->db->tasks->aggregate($pipeline)->toArray();
            $companyIds = array_map(function($item) {
                return $item['_id'];
            }, $taskCompanies);
            
            if (empty($companyIds)) {
                return [];
            }
            
            // Get company details
            $companies = $this->db->companies->find([
                '_id' => ['$in' => $companyIds],
                'active' => true
            ])->toArray();
            
            return $companies;
            
        } catch (Exception $e) {
            error_log("Error getting user companies: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcular costo total
     */
    public function calculateCost($companyId, $hours) {
        try {
            $company = $this->getCompanyById($companyId);
            if (!$company) {
                throw new Exception("Empresa no encontrada");
            }
            
            $totalCost = $company['cost_per_hour'] * $hours;
            
            return [
                'success' => true,
                'cost_per_hour' => $company['cost_per_hour'],
                'hours' => $hours,
                'total_cost' => $totalCost,
                'currency' => $company['currency']
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