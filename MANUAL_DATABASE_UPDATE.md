# 💰 Manual Database Update for Cost Tracking

## 🚨 Situación Actual
La extensión MongoDB de PHP no está instalada, por lo que necesitamos actualizar la base de datos manualmente.

## 🛠️ Opción 1: Instalar MongoDB Extension (Recomendado)

### Para macOS con Homebrew:
```bash
# Instalar MongoDB PHP extension
pecl install mongodb

# Agregar a php.ini
echo "extension=mongodb.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")

# Reiniciar el servidor web
brew services restart php
```

### Verificar instalación:
```bash
php -m | grep mongodb
```

## 🔧 Opción 2: Actualización Manual via MongoDB Shell

Si prefieres actualizar manualmente, conecta a MongoDB y ejecuta estos comandos:

### 1. Conectar a MongoDB
```bash
mongosh bigplantracker
```

### 2. Actualizar Empresas (Companies)
```javascript
// Agregar campos de costo a empresas existentes
db.companies.updateMany(
  { cost_per_hour: { $exists: false } },
  { 
    $set: { 
      cost_per_hour: 25.00,
      currency: "EUR",
      updated_at: new Date()
    }
  }
);

// Agregar campos de contacto si no existen
db.companies.updateMany(
  { contact: { $exists: false } },
  { 
    $set: { 
      contact: {
        email: "",
        phone: "",
        address: ""
      },
      updated_at: new Date()
    }
  }
);

// Marcar empresas como activas
db.companies.updateMany(
  { active: { $exists: false } },
  { 
    $set: { 
      active: true,
      updated_at: new Date()
    }
  }
);
```

### 3. Actualizar Tareas (Tasks)
```javascript
// Agregar campos de costo a tareas
db.tasks.updateMany(
  { total_cost: { $exists: false } },
  { 
    $set: { 
      total_cost: 0.00,
      actual_hours: 0.00,
      updated_at: new Date()
    }
  }
);

// Agregar horas estimadas si no existen
db.tasks.updateMany(
  { estimated_hours: { $exists: false } },
  { 
    $set: { 
      estimated_hours: 1.00,
      updated_at: new Date()
    }
  }
);
```

### 4. Actualizar Time Tracking
```javascript
// Agregar campo de costo a registros de tiempo
db.time_tracking.updateMany(
  { cost: { $exists: false } },
  { 
    $set: { 
      cost: 0.00
    }
  }
);
```

### 5. Agregar Configuración del Sistema
```javascript
// Configuraciones del sistema
db.system_config.insertMany([
  {
    key: "default_currency",
    value: "EUR",
    description: "Moneda por defecto del sistema",
    updated_at: new Date(),
    updated_by: null
  },
  {
    key: "default_cost_per_hour",
    value: 25.00,
    description: "Costo por hora por defecto para nuevas empresas",
    updated_at: new Date(),
    updated_by: null
  },
  {
    key: "cost_tracking_enabled",
    value: true,
    description: "Habilitar seguimiento de costos",
    updated_at: new Date(),
    updated_by: null
  },
  {
    key: "auto_calculate_costs",
    value: true,
    description: "Calcular costos automáticamente",
    updated_at: new Date(),
    updated_by: null
  }
]);
```

### 6. Crear Índices para Optimización
```javascript
// Índices para empresas
db.companies.createIndex({ "cost_per_hour": 1 });
db.companies.createIndex({ "currency": 1 });

// Índices para tareas
db.tasks.createIndex({ "total_cost": -1 });
db.tasks.createIndex({ "actual_hours": -1 });

// Índices para seguimiento de tiempo
db.time_tracking.createIndex({ "cost": -1 });
db.time_tracking.createIndex({ "task_id": 1, "cost": -1 });
```

### 7. Recalcular Costos Existentes
```javascript
// Script para recalcular costos de tareas existentes
db.tasks.find().forEach(function(task) {
  // Obtener la empresa
  var company = db.companies.findOne({_id: task.company_id});
  if (!company || !company.cost_per_hour) return;
  
  // Calcular tiempo total
  var timeRecords = db.time_tracking.find({task_id: task._id});
  var totalMinutes = 0;
  
  timeRecords.forEach(function(record) {
    if (record.duration_minutes) {
      totalMinutes += record.duration_minutes;
    }
  });
  
  var totalHours = totalMinutes / 60;
  var totalCost = totalHours * company.cost_per_hour;
  
  // Actualizar la tarea
  db.tasks.updateOne(
    {_id: task._id},
    {
      $set: {
        actual_hours: Math.round(totalHours * 100) / 100,
        total_cost: Math.round(totalCost * 100) / 100,
        updated_at: new Date()
      }
    }
  );
});

// Recalcular costos de registros de tiempo
db.time_tracking.find().forEach(function(record) {
  var task = db.tasks.findOne({_id: record.task_id});
  if (!task) return;
  
  var company = db.companies.findOne({_id: task.company_id});
  if (!company || !company.cost_per_hour) return;
  
  var hours = (record.duration_minutes || 0) / 60;
  var cost = hours * company.cost_per_hour;
  
  db.time_tracking.updateOne(
    {_id: record._id},
    {
      $set: {
        cost: Math.round(cost * 100) / 100
      }
    }
  );
});
```

## 🎯 Opción 3: Usar la API Existente

También puedes usar la API existente para agregar los campos manualmente:

### 1. Crear/Editar Empresas con Costos
```bash
# Ejemplo usando curl para actualizar una empresa
curl -X PUT "http://localhost:8000/api/companies?action=update" \
  -H "Content-Type: application/json" \
  -d '{
    "id": "COMPANY_ID",
    "name": "Mi Empresa",
    "cost_per_hour": 30.00,
    "currency": "EUR"
  }'
```

## ✅ Verificación

Después de cualquier método, verifica que todo funcione:

1. **Accede a las empresas**: `http://localhost:8000/views/companies.html`
2. **Edita una empresa** y configura el costo por hora
3. **Ve a reportes**: `http://localhost:8000/views/reports.html`
4. **Verifica que aparezca la sección de costos**

## 🚀 Próximos Pasos

Una vez actualizada la base de datos:

1. ✅ **Configura costos por empresa** en la interfaz de empresas
2. ✅ **Crea tareas y registra tiempo** para ver costos automáticos
3. ✅ **Exporta reportes** desde la sección de costos
4. ✅ **Monitorea tendencias** en los gráficos de costos mensuales

## 💡 Recomendación

**La Opción 1 (instalar MongoDB extension) es la más recomendada** porque:
- ✅ Permite usar el script automático
- ✅ Recalcula todos los costos existentes
- ✅ Crea índices para mejor rendimiento
- ✅ Valida la integridad de los datos

¡El sistema de cost tracking estará completamente funcional después de cualquiera de estas opciones! 🎉