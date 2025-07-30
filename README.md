# OCOBO-BACK

Aplicación gestora del proceso de gestión documental desarrollada en Laravel.

**Versión**: 2.0  
**Última actualización**: Julio 2025  
**Estado**: En desarrollo activo

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## 📋 Descripción

OCOBO-BACK es una aplicación web desarrollada en Laravel que gestiona procesos documentales de manera eficiente y organizada. El sistema proporciona una API RESTful robusta para la gestión de usuarios, roles, permisos, configuración del sistema, gestión documental, clasificación documental y control de calidad.

## 🚀 Características Principales

- **Autenticación y Autorización**: Sistema completo de autenticación con Sanctum y control de acceso basado en roles
- **Gestión de Usuarios**: CRUD completo de usuarios con gestión de archivos (avatars, firmas)
- **Control de Acceso**: Sistema de roles y permisos con Spatie Laravel-Permission
- **Configuración del Sistema**: Módulos de configuración para división política, sedes, listas, etc.
- **Gestión Documental**: Procesos de radicación y clasificación documental
- **Clasificación Documental**: Sistema completo de TRD (Tabla de Retención Documental) con versiones
- **Control de Calidad**: Gestión de organigramas y estructuras organizacionales
- **Ventanilla Única**: Sistema completo de gestión de ventanillas y radicaciones
- **API RESTful**: Endpoints bien documentados y estructurados
- **Validaciones Robustas**: Form Request classes para validaciones centralizadas
- **Manejo de Errores**: Sistema consistente de respuestas de error
- **Estadísticas Avanzadas**: Análisis detallado de datos y métricas
- **Importación de Datos**: Soporte para importación de TRD desde archivos Excel
- **Estructura Jerárquica**: Soporte completo para organigramas con relaciones padre-hijo recursivas
- **Configuración Centralizada**: Sistema de configuraciones varias con numeración unificada
- **Gestión de Archivos**: Manejo seguro de uploads con validaciones avanzadas
- **Logging Avanzado**: Sistema de logs detallado para debugging y monitoreo

## 🏗️ Arquitectura del Proyecto

### Módulos Optimizados

#### 🔐 **Control de Acceso**
- **UserController**: Gestión completa de usuarios con CRUD, estadísticas, perfil y contraseñas
- **RoleController**: Administración de roles y permisos
- **UserVentanillaController**: Gestión de asignación de usuarios a ventanillas con estadísticas
- **UserSessionController**: Control de sesiones de usuarios
- **NotificationSettingsController**: Configuración de notificaciones
- **UserSedeController**: Gestión de relación muchos a muchos entre usuarios y sedes

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

# Gestión de ventanillas por usuario
GET    /api/control-acceso/users-ventanillas/estadisticas  # Estadísticas de asignaciones
GET    /api/control-acceso/users-ventanillas              # Listar asignaciones
POST   /api/control-acceso/users-ventanillas              # Crear asignación
PUT    /api/control-acceso/users-ventanillas/{id}         # Actualizar asignación
DELETE /api/control-acceso/users-ventanillas/{id}         # Eliminar asignación

# Gestión de sedes por usuario
GET    /api/control-acceso/users-sedes                   # Listar relaciones usuario-sede
POST   /api/control-acceso/users-sedes                   # Crear relación
PUT    /api/control-acceso/users-sedes/{id}              # Actualizar relación
DELETE /api/control-acceso/users-sedes/{id}              # Eliminar relación
```

#### ⚙️ **Configuración**
- **ConfigDiviPoliController**: Gestión de división política (países, departamentos, municipios)
- **ConfigSedeController**: Administración de sedes con estadísticas y relación con división política
- **ConfigListaController**: Gestión de listas maestras
- **ConfigListaDetalleController**: Detalles de listas maestras
- **ConfigServerArchivoController**: Configuración de servidores de archivos
- **ConfigVariasController**: Configuraciones varias del sistema (incluye numeración unificada)
- **ConfigNumRadicadoController**: Configuración de numeración de radicados
- **ConfigVentanillasController**: Configuración de ventanillas con estadísticas

**Endpoints principales:**
```
# División Política
GET    /api/config/divipoli                         # Listar divisiones políticas
POST   /api/config/divipoli                         # Crear división política
GET    /api/config/divipoli/{id}                    # Obtener división política
PUT    /api/config/divipoli/{id}                    # Actualizar división política
DELETE /api/config/divipoli/{id}                    # Eliminar división política
GET    /api/config/divipoli/estadisticas            # Estadísticas de división política
GET    /api/config/divipoli/list/divi-poli-completa # Estructura jerárquica completa
GET    /api/config/divipoli/list/paises             # Listar países
GET    /api/config/divipoli/list/departamentos/{id} # Departamentos por país
GET    /api/config/divipoli/list/municipios/{id}    # Municipios por departamento

# Sedes
GET    /api/config/sedes                            # Listar sedes
POST   /api/config/sedes                            # Crear sede
GET    /api/config/sedes/{id}                       # Obtener sede
PUT    /api/config/sedes/{id}                       # Actualizar sede
DELETE /api/config/sedes/{id}                       # Eliminar sede
GET    /api/config/sedes-estadisticas               # Estadísticas de sedes

# Listas
GET    /api/config/listas                           # Listar listas maestras
POST   /api/config/listas                           # Crear lista maestra
GET    /api/config/listas/{id}                      # Obtener lista maestra
PUT    /api/config/listas/{id}                      # Actualizar lista maestra
DELETE /api/config/listas/{id}                      # Eliminar lista maestra
GET    /api/config/listas-detalles                  # Detalles de listas

# Configuraciones varias
GET    /api/config/config-varias                    # Configuraciones varias
POST   /api/config/config-varias                    # Crear configuración
PUT    /api/config/config-varias/{clave}            # Actualizar configuración

# Numeración unificada
GET    /api/config/config-varias/numeracion-unificada # Obtener configuración de numeración unificada
PUT    /api/config/config-varias/numeracion-unificada # Actualizar numeración unificada

# Configuración de numeración de radicados
GET    /api/config/config-num-radicado              # Configuración de numeración
PUT    /api/config/config-num-radicado              # Actualizar numeración

# Ventanillas de configuración
GET    /api/config/config-ventanillas/estadisticas  # Estadísticas de ventanillas
GET    /api/config/config-ventanillas               # Listar ventanillas
POST   /api/config/config-ventanillas               # Crear ventanilla
GET    /api/config/config-ventanillas/{id}          # Obtener ventanilla
PUT    /api/config/config-ventanillas/{id}          # Actualizar ventanilla
DELETE /api/config/config-ventanillas/{id}          # Eliminar ventanilla
```

#### 🎯 **Calidad**
- **CalidadOrganigramaController**: Gestión completa de organigramas con estructura jerárquica

**Endpoints principales:**
```
# Organigrama
GET    /api/calidad/organigrama/estadisticas        # Estadísticas del organigrama
GET    /api/calidad/organigrama                     # Listar organigrama completo
POST   /api/calidad/organigrama                     # Crear nodo del organigrama
GET    /api/calidad/organigrama/{id}                # Obtener nodo específico
PUT    /api/calidad/organigrama/{id}                # Actualizar nodo
DELETE /api/calidad/organigrama/{id}                # Eliminar nodo
GET    /api/calidad/organigrama/dependencias        # Listar dependencias en formato árbol visual
GET    /api/calidad/organigrama/oficinas            # Listar oficinas con cargos
```

#### 📚 **Clasificación Documental**
- **ClasificacionDocumentalTRDController**: Gestión completa de elementos TRD (Series, SubSeries, Tipos de Documento)
- **ClasificacionDocumentalTRDVersionController**: Gestión de versiones de TRD con estados (TEMP, ACTIVO, HISTORICO)

**Endpoints principales:**
```
# TRD (Tabla de Retención Documental)
GET    /api/trd                                    # Listar elementos TRD
POST   /api/trd                                    # Crear elemento TRD
GET    /api/trd/{id}                               # Obtener elemento TRD
PUT    /api/trd/{id}                               # Actualizar elemento TRD
DELETE /api/trd/{id}                               # Eliminar elemento TRD
POST   /api/trd/importar                           # Importar TRD desde Excel
GET    /api/trd/estadisticas/{dependenciaId}       # Estadísticas por dependencia
GET    /api/trd/dependencia/{dependenciaId}        # Listar por dependencia

# Estadísticas avanzadas
GET    /api/trd/estadisticas/totales               # Estadísticas totales del sistema
GET    /api/trd/estadisticas/por-dependencias      # Estadísticas detalladas por dependencias
GET    /api/trd/estadisticas/comparativas          # Estadísticas comparativas entre dependencias

# Versiones TRD
GET    /api/trd-versiones                          # Listar versiones TRD
POST   /api/trd-versiones                          # Crear nueva versión
GET    /api/trd-versiones/{id}                     # Obtener versión específica
POST   /api/trd-versiones/aprobar/{dependenciaId}  # Aprobar versión
GET    /api/trd-versiones/pendientes/aprobar       # Versiones pendientes por aprobar
GET    /api/trd-versiones/estadisticas/{dependenciaId} # Estadísticas de versiones
```

#### 📋 **Ventanilla Única**
- **VentanillaUnicaController**: Gestión de ventanillas únicas por sede
- **PermisosVentanillaUnicaController**: Gestión de permisos de usuarios a ventanillas
- **VentanillaRadicaReciController**: Gestión de radicaciones recibidas
- **VentanillaRadicaReciArchivosController**: Gestión de archivos de radicaciones
- **VentanillaRadicaReciResponsaController**: Gestión de responsables de radicaciones

**Endpoints principales:**
```
# Ventanillas únicas
GET    /api/ventanilla/sedes/{sedeId}/ventanillas   # Listar ventanillas por sede
POST   /api/ventanilla/sedes/{sedeId}/ventanillas   # Crear ventanilla
GET    /api/ventanilla/ventanillas/{id}             # Obtener ventanilla
PUT    /api/ventanilla/ventanillas/{id}             # Actualizar ventanilla
DELETE /api/ventanilla/ventanillas/{id}             # Eliminar ventanilla

# Tipos documentales
POST   /api/ventanilla/ventanillas/{id}/tipos-documentales    # Configurar tipos
GET    /api/ventanilla/ventanillas/{id}/tipos-documentales    # Listar tipos

# Permisos
POST   /api/ventanilla/ventanillas/{id}/permisos             # Asignar permisos
GET    /api/ventanilla/ventanillas/{id}/usuarios-permitidos  # Listar usuarios permitidos
DELETE /api/ventanilla/ventanillas/{id}/permisos             # Revocar permisos
GET    /api/ventanilla/usuarios/{usuarioId}/ventanillas      # Ventanillas permitidas por usuario

# Radicaciones
GET    /api/ventanilla/radica-recibida                      # Listar radicaciones
POST   /api/ventanilla/radica-recibida                      # Crear radicación
GET    /api/ventanilla/radica-recibida/{id}                 # Obtener radicación
PUT    /api/ventanilla/radica-recibida/{id}                 # Actualizar radicación
DELETE /api/ventanilla/radica-recibida/{id}                 # Eliminar radicación
GET    /api/ventanilla/radica-recibida-admin/listar         # Listado administrativo

# Archivos de radicaciones
POST   /api/ventanilla/radica-recibida/{id}/upload          # Subir archivo
GET    /api/ventanilla/radica-recibida/{id}/download        # Descargar archivo
DELETE /api/ventanilla/radica-recibida/{id}/delete-file     # Eliminar archivo
GET    /api/ventanilla/radica-recibida/{id}/file-info       # Información del archivo
GET    /api/ventanilla/radica-recibida/{id}/historial       # Historial de eliminaciones

# Responsables
GET    /api/ventanilla/responsables                         # Listar responsables
POST   /api/ventanilla/responsables                         # Crear responsable
GET    /api/ventanilla/responsables/{id}                    # Obtener responsable
PUT    /api/ventanilla/responsables/{id}                    # Actualizar responsable
DELETE /api/ventanilla/responsables/{id}                    # Eliminar responsable
GET    /api/ventanilla/radica-recibida/{id}/responsables    # Responsables por radicación
```

## 🛠️ Tecnologías Utilizadas

- **Framework**: Laravel 10.x
- **Base de Datos**: MySQL/PostgreSQL
- **Autenticación**: Laravel Sanctum
- **Roles y Permisos**: Spatie Laravel-Permission
- **Validaciones**: Form Request Classes
- **API**: RESTful API con JSON responses
- **Documentación**: PHPDoc completo
- **Manejo de Archivos**: Laravel Storage con ArchivoHelper personalizado
- **Transacciones**: Database transactions para integridad de datos
- **Procesamiento de Excel**: PhpOffice/PhpSpreadsheet para importación de TRD
- **Análisis Estadístico**: Cálculos avanzados de mediana, desviación estándar y coeficientes de variación

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

## 🛠️ Stack Tecnológico

### Backend
- **Framework**: Laravel 10.x
- **PHP**: 8.1+
- **Base de datos**: MySQL/MariaDB
- **Autenticación**: Laravel Sanctum
- **Autorización**: Spatie Laravel-Permission
- **Validaciones**: Form Request Classes
- **API**: RESTful con ApiResponseTrait

### Funcionalidades Técnicas
- **Migraciones**: Control de versiones de BD con seeders
- **Modelos Eloquent**: Relaciones complejas y scopes avanzados
- **Helpers Personalizados**: ArchivoHelper para gestión de archivos
- **Logging**: Sistema de logs avanzado con Laravel Log
- **Importación**: PhpSpreadsheet para archivos Excel
- **Estructuras Jerárquicas**: Relaciones recursivas padre-hijo
- **Configuración Dinámica**: Sistema de configuraciones centralizadas

### Características de Desarrollo
- **Request Classes**: Validaciones centralizadas y reutilizables
- **Traits**: Código reutilizable (ApiResponseTrait)
- **Scopes**: Filtros de consulta reutilizables en modelos
- **Seeders**: Datos de prueba y configuración inicial
- **Documentación**: PHPDoc completo en controladores
- **Estructura Modular**: Organización por módulos funcionales

## 🎯 Características Avanzadas

### 📊 **Sistema de Estadísticas**

Todos los módulos principales incluyen endpoints de estadísticas que proporcionan:

- **Métricas Generales**: Totales, conteos por estado, distribución temporal
- **Análisis Jerárquico**: Estructuras organizacionales, relaciones padre-hijo
- **Rankings y Tendencias**: Elementos más utilizados, actividad reciente
- **Distribución Temporal**: Análisis por períodos (mes, año, histórico)
- **Estadísticas Comparativas**: Rankings entre dependencias con métricas avanzadas
- **Análisis de Rendimiento**: Coeficientes de variación, medianas y desviaciones estándar
- **Distribución Porcentual**: Análisis de distribución por tipos y categorías
- **Métricas Empresariales**: Estadísticas de configuración y uso del sistema

### 🔄 **Gestión de Archivos**

- **ArchivoHelper**: Helper personalizado para gestión de archivos
- **Múltiples Discos**: Soporte para diferentes tipos de almacenamiento
- **Validación Dinámica**: Tamaños y tipos de archivo configurables
- **Auditoría**: Historial de eliminaciones y cambios
- **Sistema de Logos**: Gestión de logos empresariales con validaciones
- **Almacenamiento Configurable**: Discos personalizados para diferentes tipos de archivos
- **Gestión de Firmas**: Sistema de gestión de firmas de usuarios
- **Avatars de Usuario**: Sistema de gestión de avatares con validaciones

### 🏗️ **Estructuras Jerárquicas**

- **Organigramas**: Gestión completa de estructuras organizacionales
- **División Política**: Países, departamentos, municipios
- **Ventanillas**: Configuración y gestión de ventanillas por sede
- **Relaciones Complejas**: Muchos a muchos, relaciones recursivas

### 📚 **Sistema de Clasificación Documental**

- **TRD Completa**: Gestión de Series, SubSeries y Tipos de Documento
- **Sistema de Versiones**: Control de versiones con estados (TEMP, ACTIVO, HISTORICO)
- **Validación Jerárquica**: Validaciones automáticas de jerarquía y dependencias
- **Importación Masiva**: Importación de TRD desde archivos Excel con validaciones
- **Estadísticas Avanzadas**: 
  - Estadísticas totales del sistema con distribución porcentual
  - Análisis por dependencias con paginación y ordenamiento
  - Estadísticas comparativas con rankings y métricas estadísticas avanzadas
  - Distribución porcentual por tipos de elementos
  - Análisis de rendimiento con coeficientes de variación
- **Workflow de Aprobación**: Sistema de aprobación de versiones con control de estados
- **Análisis de Rendimiento**: Coeficientes de variación, medianas y desviaciones estándar
- **Cálculos Estadísticos**: Métricas avanzadas como mediana, desviación estándar y rankings

### ⚙️ **Configuración Dinámica**

- **ConfigVarias**: Configuraciones flexibles del sistema
- **Numeración Unificada**: Sistema de numeración configurable
- **Listas Maestras**: Gestión de catálogos y referencias
- **Servidores de Archivos**: Configuración de almacenamiento
- **Información Empresarial**: Gestión de datos de la empresa (NIT, razón social, logo, etc.)
- **Configuración de Backups**: Configuración de backups automáticos y frecuencia
- **Sistema Multi-Sede**: Configuración para múltiples sedes
- **Gestión de Archivos**: Sistema de almacenamiento con múltiples discos

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
│   │   ├── VentanillaUnica/        # Controladores de ventanilla única
│   │   └── ...
│   ├── Requests/                   # Form Request classes
│   └── Traits/                     # Traits compartidos (ApiResponseTrait)
├── Models/                         # Modelos Eloquent
│   ├── ControlAcceso/              # Modelos de control de acceso
│   ├── Configuracion/              # Modelos de configuración
│   ├── Calidad/                    # Modelos de calidad
│   ├── VentanillaUnica/            # Modelos de ventanilla única
│   └── ...
├── Helpers/                        # Helpers personalizados (ArchivoHelper)
└── ...
```

## 🔄 Optimizaciones Recientes

### **Módulo Calidad - Submódulo Organigrama**
- ✅ Controlador completamente optimizado con ApiResponseTrait
- ✅ Método `estadisticas()` con análisis jerárquico completo
- ✅ Form Requests optimizados con validaciones robustas
- ✅ Modelo mejorado con scopes y métodos de utilidad
- ✅ Rutas organizadas y documentadas

### **Módulo Configuración - Ventanillas**
- ✅ Campos opcionales (`codigo`, `descripcion`) en ventanillas
- ✅ Método `estadisticas()` agregado
- ✅ Validaciones mejoradas
- ✅ Documentación completa

### **Módulo Control de Acceso**
- ✅ Gestión de usuarios-sedes (muchos a muchos)
- ✅ Estadísticas avanzadas en UserVentanillaController
- ✅ Optimización de validaciones de estado
- ✅ Manejo mejorado de errores

### **Módulo Configuración**
- ✅ Migración de `numeracion_unificada` de `config_sedes` a `config_varias`
- ✅ Implementación de información empresarial en `config_varias`
- ✅ Sistema de gestión de logos empresariales con ArchivoHelper
- ✅ Configuración de backups automáticos y frecuencia
- ✅ Optimización de ConfigVariasController con métodos simplificados
- ✅ Validaciones mejoradas para archivos y configuraciones
- ✅ Sistema de almacenamiento con múltiples discos
- ✅ Endpoints específicos para numeración unificada con validaciones booleanas

### **Módulo Clasificación Documental**
- ✅ Controladores completamente optimizados con ApiResponseTrait
- ✅ Sistema de versiones TRD con estados y workflow de aprobación
- ✅ Validaciones jerárquicas robustas con Form Requests
- ✅ Importación masiva desde Excel con PhpSpreadsheet
- ✅ Estadísticas avanzadas con análisis comparativo y métricas estadísticas
- ✅ Modelos mejorados con scopes, relaciones y métodos de utilidad
- ✅ Rutas organizadas y documentadas con prefijos lógicos
- ✅ Sistema de estadísticas con rankings, medianas y desviaciones estándar

### **Módulo Calidad**
- ✅ Gestión completa de organigramas con estructura jerárquica
- ✅ Soporte para relaciones padre-hijo recursivas
- ✅ Endpoint optimizado para listar dependencias en estructura de árbol
- ✅ Validaciones robustas para nodos del organigrama
- ✅ Estadísticas detalladas del organigrama
- ✅ Sistema de scopes para filtrado por tipo y nivel

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
