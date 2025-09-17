# 👑 Usuario Administrador Global "aaron" - Instrucciones

## ✅ **Usuario Creado Exitosamente**

Se ha añadido el usuario administrador global **"aaron"** al sistema de inicialización de la base de datos.

## 🔐 **Credenciales de Acceso**

- **Usuario:** `aaron`
- **Contraseña:** `Redrover99!@`
- **Email:** `aaron@admin.com`
- **Rol:** Administrador Global

## 🚀 **Cómo Activar el Usuario**

### Opción 1: Reinicializar la Base de Datos (Recomendado)
1. Visita: `https://big-plan-tracker.onrender.com/api/init_database`
2. Esto creará automáticamente el usuario "aaron" junto con los datos de muestra
3. Usa las credenciales arriba para hacer login

### Opción 2: Usar MongoDB Directamente
Si tienes acceso directo a MongoDB, ejecuta estos comandos:

```javascript
// 1. Crear empresa si no existe
db.companies.insertOne({
  name: 'Global Admin Company',
  description: 'Company for global administrators',
  created_at: new Date(),
  is_active: true
});

// 2. Crear usuario administrador (reemplaza COMPANY_ID_HERE con el ID real)
db.users.insertOne({
  username: 'aaron',
  email: 'aaron@admin.com',
  password: '$2y$12$gOoc.H07VTwpoU6USsxlPelAgE5D3ZnS2R0QzoVIPyNM4GcV2EUnS',
  first_name: 'Aaron',
  last_name: 'Administrator',
  role: 'admin',
  company_id: ObjectId('COMPANY_ID_HERE'),
  status: 'active',
  created_at: new Date(),
  updated_at: new Date(),
  last_login: null,
  permissions: [
    'users.manage',
    'companies.manage',
    'projects.manage',
    'tasks.manage',
    'reports.view',
    'system.admin',
    'global.admin'
  ]
});
```

## 🎯 **Permisos del Usuario "aaron"**

El usuario "aaron" tiene permisos completos de administrador global:

- ✅ **users.manage** - Gestionar usuarios
- ✅ **companies.manage** - Gestionar empresas
- ✅ **projects.manage** - Gestionar proyectos
- ✅ **tasks.manage** - Gestionar tareas
- ✅ **reports.view** - Ver reportes
- ✅ **system.admin** - Administración del sistema
- ✅ **global.admin** - Administrador global

## 📋 **Verificación**

Para verificar que el usuario fue creado correctamente:

1. **Accede al sistema** con las credenciales proporcionadas
2. **Verifica los permisos** - deberías tener acceso completo a todas las funciones
3. **Revisa el panel de administración** - deberías poder gestionar usuarios, empresas, etc.

## 🔄 **Archivos Modificados**

- ✅ `api/init_database.php` - Usuario "aaron" añadido a la inicialización
- ✅ `create_admin_aaron_api.php` - Script alternativo de creación
- ✅ `create_admin_aaron.sql` - Comandos SQL/MongoDB para inserción manual

## ⚠️ **Notas Importantes**

1. **Seguridad:** Cambia la contraseña después del primer login
2. **Backup:** Haz backup de la base de datos antes de reinicializar
3. **Producción:** En producción, considera usar variables de entorno para credenciales
4. **Acceso:** Este usuario tendrá acceso completo al sistema

## 🎉 **¡Listo para Usar!**

El usuario "aaron" está configurado y listo para usar. Simplemente reinicializa la base de datos o ejecuta los comandos MongoDB manualmente.

---
**Creado:** $(date)  
**Sistema:** Big Plan Tracker  
**Versión:** Render Distribution