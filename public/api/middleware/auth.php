<?php
/**
 * Authentication Middleware
 * Verifica tokens de autenticación para APIs
 */

function verifyAuth() {
    // Obtener el token del header Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
        return ['success' => false, 'message' => 'Token no proporcionado'];
    }
    
    $token = substr($authHeader, 7); // Remover "Bearer "
    
    // Verificar si hay una sesión activa con este token
    session_start();
    
    if (!isset($_SESSION['auth_token']) || $_SESSION['auth_token'] !== $token) {
        return ['success' => false, 'message' => 'Token inválido'];
    }
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        return ['success' => false, 'message' => 'Sesión inválida'];
    }
    
    return [
        'success' => true,
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? ''
    ];
}

function requireAuth() {
    $authResult = verifyAuth();
    if (!$authResult['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $authResult['message']]);
        exit;
    }
    return $authResult;
}

function requireAdmin() {
    $authResult = requireAuth();
    if ($authResult['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado - Se requieren permisos de administrador']);
        exit;
    }
    return $authResult;
}

function requireUser() {
    $authResult = requireAuth();
    if ($authResult['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado - Este endpoint es solo para usuarios']);
        exit;
    }
    return $authResult;
}
?>