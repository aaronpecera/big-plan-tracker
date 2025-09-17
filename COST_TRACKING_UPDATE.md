# 💰 Cost Tracking Database Update

## 📋 Descripción
Scripts para actualizar la base de datos MongoDB con todas las funcionalidades de seguimiento de costos del Big Plan Tracker.

## 🚀 Cómo ejecutar la actualización

### Opción 1: Ejecución Simple
```bash
php run_cost_update.php
```

### Opción 2: Ejecución Directa
```bash
php update_database_cost_tracking.php
```

## 📊 Qué hace la actualización

### 1. **Empresas (Companies)**
- ✅ Agrega campo `cost_per_hour` (€25.00 por defecto)
- ✅ Agrega campo `currency` (EUR por defecto)
- ✅ Asegura campos de contacto completos
- ✅ Marca empresas como activas

### 2. **Tareas (Tasks)**
- ✅ Agrega campo `total_cost` 
- ✅ Agrega campo `actual_hours`
- ✅ Recalcula costos basados en tiempo registrado
- ✅ Asegura horas estimadas

### 3. **Seguimiento de Tiempo (Time Tracking)**
- ✅ Agrega campo `cost` a cada registro
- ✅ Recalcula costos automáticamente

### 4. **Configuración del Sistema**
- ✅ Moneda por defecto: EUR
- ✅ Costo por hora por defecto: €25.00
- ✅ Seguimiento de costos habilitado
- ✅ Cálculo automático activado

### 5. **Optimización**
- ✅ Crea índices para consultas rápidas
- ✅ Valida integridad de datos
- ✅ Reporta estadísticas finales

## 🎯 Después de la actualización

1. **Configurar Empresas**
   - Ve a: `http://localhost:8000/views/companies.html`
   - Edita cada empresa para configurar su costo por hora
   - Establece la moneda apropiada

2. **Ver Reportes de Costos**
   - Ve a: `http://localhost:8000/views/reports.html`
   - Sección "Reportes de Costos" estará disponible
   - Exporta reportes en CSV

3. **Seguimiento Automático**
   - Los costos se calculan automáticamente
   - Cada registro de tiempo genera un costo
   - Los totales se actualizan en tiempo real

## ⚠️ Requisitos Previos

- ✅ MongoDB ejecutándose
- ✅ Extensión PHP MongoDB instalada
- ✅ Composer dependencies (`composer install`)
- ✅ Configuración correcta en `config/mongodb.php`

## 🔧 Solución de Problemas

### Error de conexión MongoDB
```bash
# Verificar que MongoDB esté ejecutándose
brew services start mongodb-community
# o
sudo systemctl start mongod
```

### Error de extensión MongoDB
```bash
# Instalar extensión MongoDB para PHP
pecl install mongodb
```

### Error de dependencias
```bash
# Instalar dependencias de Composer
composer install
```

## 📈 Estadísticas Post-Actualización

El script mostrará:
- 📊 Empresas actualizadas
- 📋 Tareas con costos calculados  
- ⏱️ Registros de tiempo procesados
- 💰 Costo total del sistema
- 🔍 Índices creados

## 🎉 ¡Listo!

Una vez ejecutado, tu sistema tendrá:
- ✅ Seguimiento completo de costos
- ✅ Reportes avanzados
- ✅ Exportación CSV
- ✅ Cálculos automáticos
- ✅ Interfaz moderna

¡Disfruta del nuevo sistema de seguimiento de costos! 🚀