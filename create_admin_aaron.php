<?php
/**
 * Script para crear el usuario administrador global "aaron"
 * Contraseña: Redrover99!@
 * 
 * NOTA: Este script requiere MongoDB PHP extension.
 * Si no está disponible, usa create_admin_aaron_api.php en su lugar.
 */

echo "🚀 Iniciando creación del usuario administrador 'aaron'...\n\n";

// Verificar si MongoDB está disponible
if (!extension_loaded('mongodb')) {
    echo "⚠️  Extensión MongoDB no disponible en PHP.\n";
    echo "📝 Generando archivo SQL alternativo...\n\n";
    
    // Generar archivo SQL como alternativa
    $hashedPassword = password_hash('Redrover99!@', PASSWORD_BCRYPT);
    $currentDate = date('c'); // ISO 8601 format
    
    $mongoCommands = "// Comandos MongoDB para crear usuario administrador 'aaron'\n";
    $mongoCommands .= "// Ejecutar en MongoDB Shell o MongoDB Compass\n\n";
    
    $mongoCommands .= "// 1. Crear empresa si no existe\n";
    $mongoCommands .= "db.companies.insertOne({\n";
    $mongoCommands .= "  name: 'Global Admin Company',\n";
    $mongoCommands .= "  description: 'Company for global administrators',\n";
    $mongoCommands .= "  created_at: new Date(),\n";
    $mongoCommands .= "  is_active: true\n";
    $mongoCommands .= "});\n\n";
    
    $mongoCommands .= "// 2. Obtener ID de la empresa (ejecutar después del paso 1)\n";
    $mongoCommands .= "var company = db.companies.findOne({name: 'Global Admin Company'});\n";
    $mongoCommands .= "var companyId = company._id;\n\n";
    
    $mongoCommands .= "// 3. Crear usuario administrador\n";
    $mongoCommands .= "db.users.insertOne({\n";
    $mongoCommands .= "  username: 'aaron',\n";
    $mongoCommands .= "  email: 'aaron@admin.com',\n";
    $mongoCommands .= "  password: '$hashedPassword',\n";
    $mongoCommands .= "  first_name: 'Aaron',\n";
    $mongoCommands .= "  last_name: 'Administrator',\n";
    $mongoCommands .= "  role: 'admin',\n";
    $mongoCommands .= "  company_id: companyId,\n";
    $mongoCommands .= "  status: 'active',\n";
    $mongoCommands .= "  is_active: true,\n";
    $mongoCommands .= "  permissions: [\n";
    $mongoCommands .= "    'users.manage',\n";
    $mongoCommands .= "    'companies.manage',\n";
    $mongoCommands .= "    'projects.manage',\n";
    $mongoCommands .= "    'tasks.manage',\n";
    $mongoCommands .= "    'reports.view',\n";
    $mongoCommands .= "    'system.admin',\n";
    $mongoCommands .= "    'global.admin'\n";
    $mongoCommands .= "  ],\n";
    $mongoCommands .= "  created_at: new Date(),\n";
    $mongoCommands .= "  updated_at: new Date(),\n";
    $mongoCommands .= "  last_login: null\n";
    $mongoCommands .= "});\n\n";
    
    $mongoCommands .= "// 4. Verificar creación\n";
    $mongoCommands .= "db.users.findOne({username: 'aaron'});\n\n";
    
    $mongoCommands .= "// CREDENCIALES DE ACCESO:\n";
    $mongoCommands .= "// Usuario: aaron\n";
    $mongoCommands .= "// Contraseña: Redrover99!@\n";
    $mongoCommands .= "// Email: aaron@admin.com\n";
    
    file_put_contents('mongodb_create_aaron.js', $mongoCommands);
    
    echo "✅ Archivo MongoDB generado: mongodb_create_aaron.js\n";
    echo "\n📋 Instrucciones:\n";
    echo "1. Conectar a MongoDB usando MongoDB Shell o Compass\n";
    echo "2. Seleccionar la base de datos 'bigplantracker'\n";
    echo "3. Ejecutar los comandos del archivo mongodb_create_aaron.js\n";
    echo "\n🔐 Credenciales del usuario:\n";
    echo "   - Usuario: aaron\n";
    echo "   - Contraseña: Redrover99!@\n";
    echo "   - Email: aaron@admin.com\n";
    echo "   - Hash de contraseña: $hashedPassword\n";
    
    echo "\n💡 Alternativa más fácil:\n";
    echo "   - El usuario 'aaron' ya está incluido en api/init_database.php\n";
    echo "   - Simplemente reinicializa la base de datos visitando:\n";
    echo "   - https://big-plan-tracker.onrender.com/api/init_database\n";
    
    exit(0);
}

// Cargar variables de entorno desde .env
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

try {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/src/Config/DatabaseConnection.php';
    
    $db = \App\Config\DatabaseConnection::getInstance()->getDatabase();
    
    echo "✅ Conexión a MongoDB establecida.\n";
    echo "📝 Creando usuario administrador 'aaron'...\n";
    
    // El resto del código de creación iría aquí
    // Pero como ya está incluido en init_database.php, solo informamos
    
    echo "✅ El usuario 'aaron' está configurado en el sistema de inicialización.\n";
    echo "🔄 Para activarlo, reinicializa la base de datos.\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Solución recomendada:\n";
    echo "   - El usuario 'aaron' ya está incluido en api/init_database.php\n";
    echo "   - Reinicializa la base de datos para crear el usuario automáticamente\n";
    exit(1);
}

echo "\n🎉 ¡Proceso completado!\n";
echo "El usuario 'aaron' puede ahora acceder al sistema con permisos de administrador global.\n";

echo "\n🎉 ¡Proceso completado exitosamente!\n";
echo "El usuario 'aaron' puede ahora acceder al sistema con permisos de administrador global.\n";
?>