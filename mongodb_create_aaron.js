// Comandos MongoDB para crear usuario administrador 'aaron'
// Ejecutar en MongoDB Shell o MongoDB Compass

// 1. Crear empresa si no existe
db.companies.insertOne({
  name: 'Global Admin Company',
  description: 'Company for global administrators',
  created_at: new Date(),
  is_active: true
});

// 2. Obtener ID de la empresa (ejecutar después del paso 1)
var company = db.companies.findOne({name: 'Global Admin Company'});
var companyId = company._id;

// 3. Crear usuario administrador
db.users.insertOne({
  username: 'aaron',
  email: 'aaron@admin.com',
  password: '$2y$12$gNio3rEXFWwEcGjJz5tviugOf2Vp2L3RNuG9DOfvjQbQ/YQPDg9lO',
  first_name: 'Aaron',
  last_name: 'Administrator',
  role: 'admin',
  company_id: companyId,
  status: 'active',
  is_active: true,
  permissions: [
    'users.manage',
    'companies.manage',
    'projects.manage',
    'tasks.manage',
    'reports.view',
    'system.admin',
    'global.admin'
  ],
  created_at: new Date(),
  updated_at: new Date(),
  last_login: null
});

// 4. Verificar creación
db.users.findOne({username: 'aaron'});

// CREDENCIALES DE ACCESO:
// Usuario: aaron
// Contraseña: Redrover99!@
// Email: aaron@admin.com
