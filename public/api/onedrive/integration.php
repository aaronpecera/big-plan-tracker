<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../middleware/auth.php';
require_once '../config/database.php';

class OneDriveIntegration {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $db;

    public function __construct() {
        // Configuración de OneDrive (estas deberían estar en variables de entorno)
        $this->clientId = 'YOUR_ONEDRIVE_CLIENT_ID';
        $this->clientSecret = 'YOUR_ONEDRIVE_CLIENT_SECRET';
        $this->redirectUri = 'http://localhost:8000/api/onedrive/callback.php';
        $this->db = DatabaseConnection::getInstance();
    }

    public function getAuthUrl($userId) {
        $state = base64_encode(json_encode(['user_id' => $userId, 'timestamp' => time()]));
        
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => 'Files.ReadWrite.All offline_access',
            'state' => $state
        ];

        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForToken($code, $state) {
        $stateData = json_decode(base64_decode($state), true);
        
        if (!$stateData || !isset($stateData['user_id'])) {
            throw new Exception('Estado inválido');
        }

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $response = $this->makeHttpRequest(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'POST',
            $data
        );

        if (isset($response['access_token'])) {
            // Guardar tokens en la base de datos
            $this->saveUserTokens($stateData['user_id'], $response);
            return $response;
        }

        throw new Exception('Error al obtener token de acceso');
    }

    public function refreshToken($userId) {
        $userTokens = $this->getUserTokens($userId);
        
        if (!$userTokens || !isset($userTokens['refresh_token'])) {
            throw new Exception('No hay refresh token disponible');
        }

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $userTokens['refresh_token'],
            'grant_type' => 'refresh_token'
        ];

        $response = $this->makeHttpRequest(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'POST',
            $data
        );

        if (isset($response['access_token'])) {
            $this->saveUserTokens($userId, $response);
            return $response;
        }

        throw new Exception('Error al renovar token');
    }

    public function listFiles($userId, $path = '') {
        $accessToken = $this->getValidAccessToken($userId);
        
        $url = 'https://graph.microsoft.com/v1.0/me/drive/root';
        if ($path) {
            $url .= ':/' . ltrim($path, '/') . ':';
        }
        $url .= '/children';

        $response = $this->makeHttpRequest($url, 'GET', null, [
            'Authorization: Bearer ' . $accessToken
        ]);

        return $response;
    }

    public function uploadFile($userId, $fileName, $fileContent, $path = '') {
        $accessToken = $this->getValidAccessToken($userId);
        
        $url = 'https://graph.microsoft.com/v1.0/me/drive/root';
        if ($path) {
            $url .= ':/' . ltrim($path, '/') . '/';
        } else {
            $url .= '/';
        }
        $url .= $fileName . ':/content';

        $response = $this->makeHttpRequest($url, 'PUT', $fileContent, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/octet-stream'
        ]);

        return $response;
    }

    public function downloadFile($userId, $fileId) {
        $accessToken = $this->getValidAccessToken($userId);
        
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/{$fileId}/content";

        $response = $this->makeHttpRequest($url, 'GET', null, [
            'Authorization: Bearer ' . $accessToken
        ], false);

        return $response;
    }

    public function deleteFile($userId, $fileId) {
        $accessToken = $this->getValidAccessToken($userId);
        
        $url = "https://graph.microsoft.com/v1.0/me/drive/items/{$fileId}";

        $response = $this->makeHttpRequest($url, 'DELETE', null, [
            'Authorization: Bearer ' . $accessToken
        ]);

        return $response;
    }

    public function createFolder($userId, $folderName, $parentPath = '') {
        $accessToken = $this->getValidAccessToken($userId);
        
        $url = 'https://graph.microsoft.com/v1.0/me/drive/root';
        if ($parentPath) {
            $url .= ':/' . ltrim($parentPath, '/') . ':';
        }
        $url .= '/children';

        $data = [
            'name' => $folderName,
            'folder' => new stdClass(),
            '@microsoft.graph.conflictBehavior' => 'rename'
        ];

        $response = $this->makeHttpRequest($url, 'POST', json_encode($data), [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        return $response;
    }

    private function getValidAccessToken($userId) {
        $userTokens = $this->getUserTokens($userId);
        
        if (!$userTokens) {
            throw new Exception('Usuario no tiene tokens de OneDrive');
        }

        // Verificar si el token ha expirado
        if (time() >= $userTokens['expires_at']) {
            $newTokens = $this->refreshToken($userId);
            return $newTokens['access_token'];
        }

        return $userTokens['access_token'];
    }

    private function saveUserTokens($userId, $tokens) {
        $db = MongoDBConfig::getInstance();
        $database = $db->getDatabase();
        $collection = $database->selectCollection('onedrive_tokens');
        
        $tokenData = [
            'user_id' => $userId,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => time() + ($tokens['expires_in'] ?? 3600),
            'scope' => $tokens['scope'] ?? '',
            'updated_at' => new DateTime()
        ];

        $collection->replaceOne(
            ['user_id' => $userId],
            $tokenData,
            ['upsert' => true]
        );
    }

    private function getUserTokens($userId) {
        $db = MongoDBConfig::getInstance();
        $database = $db->getDatabase();
        $collection = $database->selectCollection('onedrive_tokens');
        $tokens = $collection->findOne(['user_id' => $userId]);
        
        return $tokens ? $tokens->toArray() : null;
    }

    private function makeHttpRequest($url, $method = 'GET', $data = null, $headers = [], $decodeJson = true) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            if (is_array($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('Error de cURL: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception('Error HTTP: ' . $httpCode . ' - ' . $response);
        }

        return $decodeJson ? json_decode($response, true) : $response;
    }
}

// Manejar las solicitudes
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Verificar autenticación
    $currentUser = requireAuth();
    
    $onedrive = new OneDriveIntegration();

    switch ($action) {
        case 'auth_url':
            $authUrl = $onedrive->getAuthUrl($currentUser['user_id']);
            echo json_encode([
                'success' => true,
                'auth_url' => $authUrl
            ]);
            break;

        case 'list_files':
            $path = $_GET['path'] ?? '';
            $files = $onedrive->listFiles($currentUser['user_id'], $path);
            echo json_encode([
                'success' => true,
                'files' => $files
            ]);
            break;

        case 'upload':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            if (!isset($_FILES['file'])) {
                throw new Exception('No se proporcionó archivo');
            }

            $file = $_FILES['file'];
            $path = $_POST['path'] ?? '';
            $fileContent = file_get_contents($file['tmp_name']);
            
            $result = $onedrive->uploadFile($currentUser['user_id'], $file['name'], $fileContent, $path);
            echo json_encode([
                'success' => true,
                'file' => $result
            ]);
            break;

        case 'download':
            $fileId = $_GET['file_id'] ?? '';
            if (!$fileId) {
                throw new Exception('ID de archivo requerido');
            }
            
            $fileContent = $onedrive->downloadFile($currentUser['user_id'], $fileId);
            
            // Enviar archivo como descarga
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="downloaded_file"');
            echo $fileContent;
            exit;

        case 'delete':
            if ($method !== 'DELETE') {
                throw new Exception('Método no permitido');
            }
            
            $fileId = $_GET['file_id'] ?? '';
            if (!$fileId) {
                throw new Exception('ID de archivo requerido');
            }
            
            $onedrive->deleteFile($currentUser['user_id'], $fileId);
            echo json_encode([
                'success' => true,
                'message' => 'Archivo eliminado correctamente'
            ]);
            break;

        case 'create_folder':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $folderName = $input['name'] ?? '';
            $parentPath = $input['parent_path'] ?? '';
            
            if (!$folderName) {
                throw new Exception('Nombre de carpeta requerido');
            }
            
            $result = $onedrive->createFolder($currentUser['user_id'], $folderName, $parentPath);
            echo json_encode([
                'success' => true,
                'folder' => $result
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida'
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>