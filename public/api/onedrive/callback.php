<?php
require_once 'integration.php';

try {
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    $error = $_GET['error'] ?? '';

    if ($error) {
        throw new Exception('Error de autorización: ' . $error);
    }

    if (!$code || !$state) {
        throw new Exception('Parámetros de autorización faltantes');
    }

    $onedrive = new OneDriveIntegration();
    $tokens = $onedrive->exchangeCodeForToken($code, $state);

    // Redirigir de vuelta al dashboard con éxito
    $redirectUrl = '/views/user-dashboard.html?onedrive_connected=1';
    
    // Si es admin, redirigir al dashboard de admin
    $stateData = json_decode(base64_decode($state), true);
    if (isset($stateData['is_admin']) && $stateData['is_admin']) {
        $redirectUrl = '/views/admin-dashboard.html?onedrive_connected=1';
    }

    header('Location: ' . $redirectUrl);
    exit;

} catch (Exception $e) {
    // Redirigir con error
    $errorMessage = urlencode($e->getMessage());
    header('Location: /views/user-dashboard.html?onedrive_error=' . $errorMessage);
    exit;
}
?>