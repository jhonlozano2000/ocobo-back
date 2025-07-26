# OCOBO-BACK

Aplicación gestora del proceso de gestión documental desarrollada en Laravel.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## 📋 Descripción

OCOBO-BACK es una aplicación web desarrollada en Laravel que gestiona procesos documentales de manera eficiente y organizada. El sistema proporciona una API RESTful robusta para la gestión de usuarios, roles, permisos, configuración del sistema y gestión documental.

## 🚀 Características Principales

- **Autenticación y Autorización**: Sistema completo de autenticación con Sanctum y control de acceso basado en roles
- **Gestión de Usuarios**: CRUD completo de usuarios con gestión de archivos (avatars, firmas)
- **Control de Acceso**: Sistema de roles y permisos con Spatie Laravel-Permission
- **Configuración del Sistema**: Módulos de configuración para división política, sedes, listas, etc.
- **Gestión Documental**: Procesos de radicación y clasificación documental
- **API RESTful**: Endpoints bien documentados y estructurados
- **Validaciones Robustas**: Form Request classes para validaciones centralizadas
- **Manejo de Errores**: Sistema consistente de respuestas de error

## 🏗️ Arquitectura del Proyecto

### Módulos Optimizados

#### 🔐 **Control de Acceso**
- **UserController**: Gestión completa de usuarios con CRUD, estadísticas, perfil y contraseñas
- **RoleController**: Administración de roles y permisos
- **UserVentanillaController**: Gestión de asignación de usuarios a ventanillas
- **UserSessionController**: Control de sesiones de usuarios
- **NotificationSettingsController**: Configuración de notificaciones

**Endpoints principales:**
```
GET    /api/control-acceso/users                    # Listar usuarios
POST   /api/control-acceso/users                    # Crear usuario
GET    /api/control-acceso/users/{id}               # Obtener usuario
PUT    /api/control-acceso/users/{id}               # Actualizar usuario
DELETE /api/control-acceso/users/{id}               # Eliminar usuario
GET    /api/control-acceso/users/estadisticas       # Estadísticas de usuarios
PUT    /api/control-acceso/users/profile            # Actualizar perfil
PUT    /api/control-acceso/users/password           # Cambiar contraseña
POST   /api/control-acceso/users/activar-inactivar  # Activar/desactivar cuenta
```

#### ⚙️ **Configuración**
- **ConfigDiviPoliController**: Gestión de división política (países, departamentos, municipios)
- **ConfigSedeController**: Administración de sedes con estadísticas
- **ConfigListaController**: Gestión de listas maestras
- **ConfigListaDetalleController**: Detalles de listas maestras
- **ConfigServerArchivoController**: Configuración de servidores de archivos
- **ConfigVariasController**: Configuraciones varias del sistema
- **ConfigNumRadicadoController**: Configuración de numeración de radicados
- **ConfigVentanillasController**: Configuración de ventanillas

**Endpoints principales:**
```
# División Política
GET    /api/config/divipoli                         # Listar divisiones políticas
GET    /api/config/divipoli/estadisticas            # Estadísticas de división política
GET    /api/config/divipoli/list/divi-poli-completa # Estructura jerárquica completa
GET    /api/config/divipoli/list/paises             # Listar países
GET    /api/config/divipoli/list/departamentos/{id} # Departamentos por país
GET    /api/config/divipoli/list/municipios/{id}    # Municipios por departamento

# Sedes
GET    /api/config/sedes                            # Listar sedes
GET    /api/config/sedes-estadisticas               # Estadísticas de sedes

# Listas
GET    /api/config/listas                           # Listar listas maestras
GET    /api/config/listas-detalles                  # Detalles de listas

# Configuraciones varias
GET    /api/config/config-varias                    # Configuraciones varias
GET    /api/config/config-num-radicado              # Configuración de numeración
```

## 🛠️ Tecnologías Utilizadas

- **Framework**: Laravel 10.x
- **Base de Datos**: MySQL/PostgreSQL
- **Autenticación**: Laravel Sanctum
- **Roles y Permisos**: Spatie Laravel-Permission
- **Validaciones**: Form Request Classes
- **API**: RESTful API con JSON responses
- **Documentación**: PHPDoc completo

## 📦 Instalación

### Requisitos Previos
- PHP 8.1 o superior
- Composer
- MySQL/PostgreSQL
- Node.js y NPM (para assets)

### Pasos de Instalación

1. **Clonar el repositorio**
```bash
git clone [url-del-repositorio]
cd ocobo-back
```

2. **Instalar dependencias**
```bash
composer install
npm install
```

3. **Configurar variables de entorno**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configurar base de datos en .env**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ocobo_back
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

5. **Ejecutar migraciones y seeders**
```bash
php artisan migrate
php artisan db:seed
```

6. **Compilar assets (opcional)**
```bash
npm run dev
```

7. **Iniciar servidor**
```bash
php artisan serve
```

## 🔧 Configuración

### Archivos de Configuración Importantes

- **`.env`**: Variables de entorno
- **`config/auth.php`**: Configuración de autenticación
- **`config/permission.php`**: Configuración de roles y permisos
- **`config/filesystems.php`**: Configuración de almacenamiento de archivos

### Estructura de Rutas

Las rutas están organizadas por módulos en archivos separados:
- `routes/controlAcceso.php` - Rutas de control de acceso
- `routes/configuracion.php` - Rutas de configuración
- `routes/calidad.php` - Rutas de calidad
- `routes/clasifica_documental.php` - Rutas de clasificación documental
- `routes/gestion.php` - Rutas de gestión
- `routes/ventanilla.php` - Rutas de ventanilla única

## 📚 Documentación de la API

### Autenticación

La API utiliza Laravel Sanctum para autenticación. Todas las rutas (excepto login/register) requieren un token Bearer.

```bash
# Login
POST /api/login
{
    "email": "usuario@example.com",
    "password": "password"
}

# Usar token en requests
Authorization: Bearer {token}
```

### Respuestas Estándar

Todas las respuestas siguen el formato:

```json
{
    "status": true,
    "message": "Mensaje descriptivo",
    "data": { ... }
}
```

### Códigos de Estado HTTP

- `200` - OK
- `201` - Created
- `422` - Validation Error
- `404` - Not Found
- `500` - Server Error

## 🧪 Testing

```bash
# Ejecutar tests
php artisan test

# Ejecutar tests específicos
php artisan test --filter UserControllerTest
```

## 📁 Estructura del Proyecto

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── ControlAcceso/          # Controladores de control de acceso
│   │   ├── Configuracion/          # Controladores de configuración
│   │   ├── Calidad/                # Controladores de calidad
│   │   └── ...
│   ├── Requests/                   # Form Request classes
│   └── Traits/                     # Traits compartidos
├── Models/                         # Modelos Eloquent
├── Helpers/                        # Helpers personalizados
└── ...
```

## 🤝 Contribución

1. Fork el proyecto
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Soporte

Para soporte técnico o preguntas sobre el proyecto, contactar al equipo de desarrollo.

---

**Desarrollado con ❤️ usando Laravel**
