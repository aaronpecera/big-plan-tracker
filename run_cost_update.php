<?php
/**
 * Ejecutor del script de actualización de Cost Tracking
 * Ejecuta: php run_cost_update.php
 */

echo "🚀 Big Plan Tracker - Actualización de Cost Tracking\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Verificar que el archivo de actualización existe
if (!file_exists('update_database_cost_tracking.php')) {
    die("❌ Error: No se encuentra el archivo update_database_cost_tracking.php\n");
}

// Verificar que MongoDB está disponible
if (!extension_loaded('mongodb')) {
    die("❌ Error: Extensión MongoDB no está instalada\n");
}

// Verificar que Composer está instalado
if (!file_exists('vendor/autoload.php')) {
    die("❌ Error: Dependencias de Composer no instaladas. Ejecuta: composer install\n");
}

echo "✅ Verificaciones previas completadas\n";
echo "📊 Iniciando actualización de base de datos...\n\n";

// Incluir y ejecutar el script de actualización
try {
    include 'update_database_cost_tracking.php';
} catch (Exception $e) {
    echo "\n❌ Error durante la ejecución: " . $e->getMessage() . "\n";
    echo "💡 Sugerencias:\n";
    echo "   - Verifica que MongoDB esté ejecutándose\n";
    echo "   - Revisa la configuración en config/mongodb.php\n";
    echo "   - Asegúrate de tener permisos de escritura en la base de datos\n";
    exit(1);
}

echo "\n✅ Actualización completada exitosamente!\n";
echo "🎯 Próximos pasos:\n";
echo "   1. Accede a http://localhost:8000/views/companies.html\n";
echo "   2. Configura el costo por hora para tus empresas\n";
echo "   3. Ve a http://localhost:8000/views/reports.html para ver los reportes de costos\n";
echo "\n🎉 ¡El sistema de seguimiento de costos está listo!\n";
?>