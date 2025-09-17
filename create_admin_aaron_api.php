<?php
/**
 * Script para crear el usuario administrador global "aaron" usando la API
 * Contraseña: Redrover99!@
 */

// Datos del nuevo administrador
$adminData = [
    'username' => 'aaron',
    'email' => 'aaron@admin.com',
    'password' => 'Redrover99!@',
    'first_name' => 'Aaron',
    'last_name' => 'Administrator',
    'role' => 'admin',
    'company_name' => 'Global Admin Company',
    'permissions' => [
        'manage_users' => true,
        'manage_companies' => true,
        'manage_projects' => true,
        'manage_tasks' => true,
        'view_reports' => true,
        'system_admin' => true,
        'global_admin' => true
    ]
];

echo "🚀 Creando usuario administrador global 'aaron'...\n\n";

// Función para hacer peticiones HTTP
function makeRequest($url, $data = null, $method = 'GET') {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($data))
        ]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        throw new Exception("cURL Error: $error");
    }
    
    return [
        'code' => $httpCode,
        'body' => $response
    ];
}

try {
    // Opción 1: Usar la API local si está disponible
    $localApiUrl = 'http://localhost/api/users.php';
    
    echo "📡 Intentando crear usuario via API local...\n";
    
    try {
        $response = makeRequest($localApiUrl, $adminData, 'POST');
        
        if ($response['code'] === 200 || $response['code'] === 201) {
            $result = json_decode($response['body'], true);
            
            if ($result && isset($result['success']) && $result['success']) {
                echo "✅ Usuario administrador global 'aaron' creado exitosamente via API local!\n";
                echo "📋 Detalles del usuario:\n";
                echo "   - Username: aaron\n";
                echo "   - Email: aaron@admin.com\n";
                echo "   - Rol: admin\n";
                echo "   - Permisos: Administrador Global\n";
                echo "\n🔐 Credenciales de acceso:\n";
                echo "   - Usuario: aaron\n";
                echo "   - Contraseña: Redrover99!@\n";
                echo "\n🎉 ¡Proceso completado exitosamente!\n";
                exit(0);
            }
        }
    } catch (Exception $e) {
        echo "⚠️  API local no disponible: " . $e->getMessage() . "\n";
    }
    
    // Opción 2: Usar la API de Render si está disponible
    $renderApiUrl = 'https://big-plan-tracker.onrender.com/api/users.php';
    
    echo "📡 Intentando crear usuario via API de Render...\n";
    
    try {
        $response = makeRequest($renderApiUrl, $adminData, 'POST');
        
        if ($response['code'] === 200 || $response['code'] === 201) {
            $result = json_decode($response['body'], true);
            
            if ($result && isset($result['success']) && $result['success']) {
                echo "✅ Usuario administrador global 'aaron' creado exitosamente via API de Render!\n";
                echo "📋 Detalles del usuario:\n";
                echo "   - Username: aaron\n";
                echo "   - Email: aaron@admin.com\n";
                echo "   - Rol: admin\n";
                echo "   - Permisos: Administrador Global\n";
                echo "\n🔐 Credenciales de acceso:\n";
                echo "   - Usuario: aaron\n";
                echo "   - Contraseña: Redrover99!@\n";
                echo "\n🎉 ¡Proceso completado exitosamente!\n";
                exit(0);
            }
        }
        
        echo "⚠️  Respuesta de API: " . $response['body'] . "\n";
        
    } catch (Exception $e) {
        echo "⚠️  API de Render no disponible: " . $e->getMessage() . "\n";
    }
    
    // Opción 3: Crear archivo SQL para inserción manual
    echo "📝 Creando archivo SQL para inserción manual...\n";
    
    $hashedPassword = password_hash($adminData['password'], PASSWORD_BCRYPT);
    $permissions = json_encode($adminData['permissions']);
    $currentDate = date('Y-m-d H:i:s');
    
    $sqlContent = "-- Script SQL para crear usuario administrador global 'aaron'\n";
    $sqlContent .= "-- Ejecutar este script en MongoDB o convertir a MongoDB syntax\n\n";
    
    $sqlContent .= "-- 1. Crear empresa si no existe\n";
    $sqlContent .= "db.companies.insertOne({\n";
    $sqlContent .= "  name: 'Global Admin Company',\n";
    $sqlContent .= "  description: 'Company for global administrators',\n";
    $sqlContent .= "  created_at: new Date(),\n";
    $sqlContent .= "  is_active: true\n";
    $sqlContent .= "});\n\n";
    
    $sqlContent .= "-- 2. Crear usuario administrador\n";
    $sqlContent .= "db.users.insertOne({\n";
    $sqlContent .= "  username: 'aaron',\n";
    $sqlContent .= "  email: 'aaron@admin.com',\n";
    $sqlContent .= "  password: '$hashedPassword',\n";
    $sqlContent .= "  first_name: 'Aaron',\n";
    $sqlContent .= "  last_name: 'Administrator',\n";
    $sqlContent .= "  role: 'admin',\n";
    $sqlContent .= "  company_id: ObjectId('COMPANY_ID_HERE'), // Reemplazar con ID de empresa\n";
    $sqlContent .= "  is_active: true,\n";
    $sqlContent .= "  permissions: {\n";
    $sqlContent .= "    manage_users: true,\n";
    $sqlContent .= "    manage_companies: true,\n";
    $sqlContent .= "    manage_projects: true,\n";
    $sqlContent .= "    manage_tasks: true,\n";
    $sqlContent .= "    view_reports: true,\n";
    $sqlContent .= "    system_admin: true,\n";
    $sqlContent .= "    global_admin: true\n";
    $sqlContent .= "  },\n";
    $sqlContent .= "  created_at: new Date(),\n";
    $sqlContent .= "  updated_at: new Date()\n";
    $sqlContent .= "});\n\n";
    
    $sqlContent .= "-- 3. Registrar actividad del sistema\n";
    $sqlContent .= "db.system_activities.insertOne({\n";
    $sqlContent .= "  activity_type: 'user_created',\n";
    $sqlContent .= "  description: 'Global admin user aaron created via script',\n";
    $sqlContent .= "  user_id: ObjectId('USER_ID_HERE'), // Reemplazar con ID de usuario\n";
    $sqlContent .= "  created_at: new Date()\n";
    $sqlContent .= "});\n\n";
    
    $sqlContent .= "-- CREDENCIALES DE ACCESO:\n";
    $sqlContent .= "-- Usuario: aaron\n";
    $sqlContent .= "-- Contraseña: Redrover99!@\n";
    
    file_put_contents('create_admin_aaron.sql', $sqlContent);
    
    echo "✅ Archivo SQL creado: create_admin_aaron.sql\n";
    echo "\n📋 Instrucciones:\n";
    echo "1. Conectar a MongoDB Atlas o tu instancia de MongoDB\n";
    echo "2. Ejecutar los comandos del archivo create_admin_aaron.sql\n";
    echo "3. Reemplazar 'COMPANY_ID_HERE' y 'USER_ID_HERE' con los IDs reales\n";
    echo "\n🔐 Credenciales del usuario:\n";
    echo "   - Usuario: aaron\n";
    echo "   - Contraseña: Redrover99!@\n";
    echo "   - Hash de contraseña: $hashedPassword\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎯 Para usar el usuario 'aaron':\n";
echo "1. Asegúrate de que la base de datos esté inicializada\n";
echo "2. Usa las credenciales proporcionadas para hacer login\n";
echo "3. El usuario tendrá permisos completos de administrador global\n";
?>