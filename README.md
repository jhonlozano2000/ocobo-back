# OCOBO-BACK

Aplicación gestora del proceso de gestión documental desarrollada en Laravel.

**Versión**: 2.0  
**Última actualización**: Diciembre 2024  
**Estado**: En desarrollo activo

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## 📑 Tabla de Contenidos

- [Descripción](#-descripción)
- [Características Principales](#-características-principales)
- [Arquitectura del Proyecto](#️-arquitectura-del-proyecto)
  - [Control de Acceso](#-control-de-acceso)
  - [Configuración](#️-configuración)
  - [Calidad](#-calidad)
  - [Clasificación Documental](#-clasificación-documental)
  - [Ventanilla Única](#-ventanilla-única)
  - [Gestión](#-gestión)
- [Tecnologías Utilizadas](#️-tecnologías-utilizadas)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Documentación de la API](#-documentación-de-la-api)
- [Stack Tecnológico](#️-stack-tecnológico)
- [Características Avanzadas](#-características-avanzadas)
- [Testing](#-testing)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Optimizaciones Recientes](#-optimizaciones-recientes)
- [Troubleshooting](#-troubleshooting)
- [Seguridad](#-seguridad)
- [Performance](#-performance)
- [Modelo de Datos](#-modelo-de-datos)
- [Deployment](#-deployment)
- [Comandos Artisan](#-comandos-artisan-útiles)
- [Monitoreo y Logging](#-monitoreo-y-logging)
- [Backups](#-backups)
- [Ejemplos de Integración](#-ejemplos-de-integración)
- [FAQ](#-faq-preguntas-frecuentes)
- [Changelog](#-changelog)
- [Contribución](#-contribución)
- [Roadmap](#️-roadmap)

## 📋 Descripción

OCOBO-BACK es una aplicación web desarrollada en Laravel que gestiona procesos documentales de manera eficiente y organizada. El sistema proporciona una API RESTful robusta para la gestión de usuarios, roles, permisos, configuración del sistema, gestión documental, clasificación documental y control de calidad.

## 🚀 Características Principales

- **Autenticación y Autorización**: Sistema completo de autenticación con Sanctum y control de acceso basado en roles
- **Gestión de Usuarios**: CRUD completo de usuarios con gestión de archivos (avatars, firmas)
- **Control de Acceso**: Sistema de roles y permisos con Spatie Laravel-Permission
- **Gestión de Cargos**: Sistema completo de asignación de cargos a usuarios con historial y estadísticas
- **Gestión de Terceros**: CRUD de terceros con filtros y estadísticas
- **Configuración del Sistema**: Módulos de configuración para división política, sedes, listas, etc.
- **Gestión Documental**: Procesos de radicación y clasificación documental
- **Clasificación Documental**: Sistema completo de TRD (Tabla de Retención Documental) con versiones y datos de prueba
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
- **Datos de Prueba**: Seeders completos con datos de prueba para todos los módulos

#### 📊 **Gestión**
- **GestionTerceroController**: Gestión de terceros con estadísticas y filtros

**Endpoints principales:**
```
# Terceros
GET    /api/gestion/terceros                         # Listar terceros
POST   /api/gestion/terceros                         # Crear tercero
GET    /api/gestion/terceros/{id}                    # Obtener tercero
PUT    /api/gestion/terceros/{id}                    # Actualizar tercero
DELETE /api/gestion/terceros/{id}                    # Eliminar tercero
GET    /api/gestion/terceros-estadistica             # Estadísticas de terceros
GET    /api/gestion/terceros-filter                  # Filtrar terceros
```

#### 🔐 **Autenticación**
- **AuthController**: Sistema completo de autenticación con Sanctum

**Endpoints principales:**
```
# Autenticación (públicos)
POST   /api/register                                  # Registrar nuevo usuario
POST   /api/login                                     # Iniciar sesión

# Autenticación (requiere token)
GET    /api/user                                      # Obtener usuario autenticado
GET    /api/getme                                     # Obtener información completa del usuario (roles, permisos, cargo, oficina, dependencia)
POST   /api/refresh                                   # Refrescar token
POST   /api/logout                                    # Cerrar sesión
```

## 🏗️ Arquitectura del Proyecto

### Módulos Optimizados

#### 🔐 **Control de Acceso**
- **UserController**: Gestión completa de usuarios con CRUD, estadísticas, perfil y contraseñas
- **RoleController**: Administración de roles y permisos
- **UserVentanillaController**: Gestión de asignación de usuarios a ventanillas con estadísticas
- **UserSessionController**: Control de sesiones de usuarios
- **NotificationSettingsController**: Configuración de notificaciones
- **UserSedeController**: Gestión de relación muchos a muchos entre usuarios y sedes
- **UserCargoController**: Gestión de asignación de cargos a usuarios

**Endpoints principales:**
```
# Usuarios
GET    /api/control-acceso/users                                    # Listar usuarios
POST   /api/control-acceso/users                                    # Crear usuario
GET    /api/control-acceso/users/{id}                               # Obtener usuario
PUT    /api/control-acceso/users/{id}                               # Actualizar usuario
DELETE /api/control-acceso/users/{id}                               # Eliminar usuario
GET    /api/control-acceso/users/stats/estadisticas                 # Estadísticas de usuarios
GET    /api/control-acceso/users/usuarios-con-cargos                 # Usuarios con cargos asignados
GET    /api/control-acceso/users/usuarios-activos-con-oficina-dependencia # Usuarios activos con oficina y dependencia
GET    /api/control-acceso/users/usuarios-con-cargos-activos        # Usuarios con cargos activos
PUT    /api/control-acceso/user/profile-information                  # Actualizar información de perfil

# Endpoints de Debug (solo desarrollo)
GET    /api/control-acceso/users/debug-relaciones                    # Debug de relaciones de usuarios
GET    /api/control-acceso/users/debug-oficinas-cargos               # Debug de oficinas y cargos
GET    /api/control-acceso/users/debug-organigrama-estructura        # Debug de estructura de organigrama
PUT    /api/control-acceso/user/changePassword                       # Cambiar contraseña
POST   /api/control-acceso/user/activar-inactivar                   # Activar/desactivar cuenta

# Roles y Permisos
GET    /api/control-acceso/roles                                    # Listar roles
POST   /api/control-acceso/roles                                    # Crear rol
GET    /api/control-acceso/roles/{id}                               # Obtener rol
PUT    /api/control-acceso/roles/{id}                               # Actualizar rol
DELETE /api/control-acceso/roles/{id}                               # Eliminar rol
GET    /api/control-acceso/roles-usuarios                           # Roles con usuarios asignados
GET    /api/control-acceso/roles-y-permisos                         # Listar roles y permisos
GET    /api/control-acceso/permisos                                 # Listar permisos

# Sesiones de Usuario
GET    /api/control-acceso/user/recent-devices                     # Dispositivos recientes del usuario autenticado
GET    /api/control-acceso/users/{userId}/sessions                  # Sesiones de un usuario
DELETE /api/control-acceso/user/sessions/{sessionId}                # Cerrar sesión específica

# Configuración de Notificaciones
GET    /api/control-acceso/users/notification-settings              # Configuración de notificaciones del usuario autenticado
PUT    /api/control-acceso/users/notification-settings             # Actualizar configuración de notificaciones
GET    /api/control-acceso/users/{userId}/notification-settings    # Configuración de notificaciones de un usuario
PUT    /api/control-acceso/users/{userId}/notification-settings    # Actualizar configuración de notificaciones de un usuario

# Gestión de ventanillas por usuario
GET    /api/control-acceso/users-ventanillas/estadisticas           # Estadísticas de asignaciones
GET    /api/control-acceso/users-ventanillas                        # Listar asignaciones
POST   /api/control-acceso/users-ventanillas                       # Crear asignación
PUT    /api/control-acceso/users-ventanillas/{id}                   # Actualizar asignación
DELETE /api/control-acceso/users-ventanillas/{id}                   # Eliminar asignación

# Gestión de sedes por usuario
GET    /api/control-acceso/user-sedes                               # Listar relaciones usuario-sede
POST   /api/control-acceso/user-sedes                               # Crear relación
GET    /api/control-acceso/user-sedes/{id}                          # Obtener relación
PUT    /api/control-acceso/user-sedes/{id}                          # Actualizar relación
DELETE /api/control-acceso/user-sedes/{id}                          # Eliminar relación
GET    /api/control-acceso/users/{userId}/sedes                     # Sedes de un usuario
GET    /api/control-acceso/sedes/{sedeId}/users                     # Usuarios de una sede

# Gestión de Cargos de Usuarios
GET    /api/control-acceso/user-cargos                              # Listar asignaciones de cargos
POST   /api/control-acceso/user-cargos/asignar                      # Asignar cargo a usuario
PUT    /api/control-acceso/user-cargos/finalizar/{asignacionId}     # Finalizar asignación de cargo
GET    /api/control-acceso/user-cargos/usuario/{userId}/activo      # Cargo activo de un usuario
GET    /api/control-acceso/user-cargos/usuario/{userId}/historial   # Historial de cargos de un usuario
GET    /api/control-acceso/user-cargos/cargo/{cargoId}/usuarios     # Usuarios de un cargo
GET    /api/control-acceso/user-cargos/estadisticas                # Estadísticas de asignaciones de cargos
GET    /api/control-acceso/user-cargos/cargos-disponibles           # Cargos disponibles para asignar
```

#### ⚙️ **Configuración**
- **ConfigDiviPoliController**: Gestión de división política (países, departamentos, municipios)
- **ConfigSedeController**: Administración de sedes con estadísticas y relación con división política
- **ConfigListaController**: Gestión de listas maestras
- **ConfigListaDetalleController**: Detalles de listas maestras
- **ConfigServerArchivoController**: Configuración de servidores de archivos
- **ConfigVariasController**: Configuraciones varias del sistema (incluye numeración unificada e información empresarial)
- **ConfigNumRadicadoController**: Configuración de numeración de radicados
- **ConfigVentanillasController**: Configuración de ventanillas con estadísticas

**Endpoints principales:**
```
# División Política
GET    /api/config/division-politica                                # Listar divisiones políticas
POST   /api/config/division-politica                                # Crear división política
GET    /api/config/division-politica/{id}                           # Obtener división política
PUT    /api/config/division-politica/{id}                           # Actualizar división política
DELETE /api/config/division-politica/{id}                           # Eliminar división política
GET    /api/config/division-politica/estadisticas                   # Estadísticas de división política
GET    /api/config/division-politica/{id}/recursivo                 # Cargar división política recursivamente
GET    /api/config/division-politica/list/divi-poli-completa       # Estructura jerárquica completa
GET    /api/config/division-politica/list/paises                    # Listar países
GET    /api/config/division-politica/list/departamentos/{paisId}    # Departamentos por país
GET    /api/config/division-politica/list/municipios/{departamentoId} # Municipios por departamento
GET    /api/config/division-politica/list/por-tipo/{tipo}           # Listar por tipo (País, Departamento, Municipio)

# Sedes
GET    /api/config/sedes                                            # Listar sedes
POST   /api/config/sedes                                            # Crear sede
GET    /api/config/sedes/{id}                                       # Obtener sede
PUT    /api/config/sedes/{id}                                       # Actualizar sede
DELETE /api/config/sedes/{id}                                       # Eliminar sede
GET    /api/config/sedes-estadisticas                               # Estadísticas de sedes

# Listas Maestras
GET    /api/config/listas                                           # Listar listas maestras
POST   /api/config/listas                                           # Crear lista maestra
GET    /api/config/listas/{id}                                       # Obtener lista maestra
PUT    /api/config/listas/{id}                                       # Actualizar lista maestra
DELETE /api/config/listas/{id}                                      # Eliminar lista maestra
GET    /api/config/listas-con-detalle                               # Listas con sus detalles
GET    /api/config/listas-cabeza                                    # Solo listas (cabezas) sin detalles
GET    /api/config/listas-detalles/activas/{lista_id}               # Detalles activos de una lista

# Detalles de Listas
GET    /api/config/listas-detalles                                  # Listar detalles de listas
POST   /api/config/listas-detalles                                 # Crear detalle de lista
GET    /api/config/listas-detalles/{id}                             # Obtener detalle de lista
PUT    /api/config/listas-detalles/{id}                             # Actualizar detalle de lista
DELETE /api/config/listas-detalles/{id}                             # Eliminar detalle de lista
GET    /api/config/listas-detalles/estadisticas                     # Estadísticas de detalles de listas

# Servidores de Archivos
GET    /api/config/servidores-archivos                              # Listar servidores de archivos
POST   /api/config/servidores-archivos                             # Crear servidor de archivos
GET    /api/config/servidores-archivos/{id}                        # Obtener servidor de archivos
PUT    /api/config/servidores-archivos/{id}                        # Actualizar servidor de archivos
DELETE /api/config/servidores-archivos/{id}                        # Eliminar servidor de archivos
GET    /api/config/servidores-archivos/estadisticas                 # Estadísticas de servidores de archivos

# Configuraciones varias (incluye información empresarial)
GET    /api/config/config-varias                                    # Configuraciones varias
POST   /api/config/config-varias                                    # Crear configuración
PUT    /api/config/config-varias/{clave}                            # Actualizar configuración

# Numeración unificada
GET    /api/config/config-varias/numeracion-unificada               # Obtener configuración de numeración unificada
PUT    /api/config/config-varias/numeracion-unificada               # Actualizar numeración unificada

# Configuración de numeración de radicados
GET    /api/config/config-num-radicado                              # Configuración de numeración
PUT    /api/config/config-num-radicado                              # Actualizar numeración

# Ventanillas de configuración
GET    /api/config/config-ventanillas/estadisticas                  # Estadísticas de ventanillas
GET    /api/config/config-ventanillas                               # Listar ventanillas
POST   /api/config/config-ventanillas                               # Crear ventanilla
GET    /api/config/config-ventanillas/{id}                          # Obtener ventanilla
PUT    /api/config/config-ventanillas/{id}                          # Actualizar ventanilla
DELETE /api/config/config-ventanillas/{id}                          # Eliminar ventanilla

# Ventanillas dentro de Sedes
GET    /api/config/sedes/{sedeId}/ventanillas                      # Listar ventanillas de una sede
POST   /api/config/sedes/{sedeId}/ventanillas                      # Crear ventanilla en una sede
GET    /api/config/sedes/{sedeId}/ventanillas/{id}                 # Obtener ventanilla de una sede
PUT    /api/config/sedes/{sedeId}/ventanillas/{id}                 # Actualizar ventanilla de una sede
DELETE /api/config/sedes/{sedeId}/ventanillas/{id}                 # Eliminar ventanilla de una sede

# Permisos de Ventanillas (en módulo Config)
POST   /api/config/ventanillas/{ventanilla}/permisos               # Asignar permisos a ventanilla
GET    /api/config/usuarios/{usuario}/ventanillas                  # Ventanillas permitidas para un usuario

# Tipos Documentales de Ventanillas (en módulo Config)
POST   /api/config/ventanillas/{ventanilla}/tipos-documentales      # Configurar tipos documentales
GET    /api/config/ventanillas/{ventanilla}/tipos-documentales      # Listar tipos documentales
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
GET    /api/clasifica-documental/trd                                # Listar elementos TRD
POST   /api/clasifica-documental/trd                                # Crear elemento TRD
GET    /api/clasifica-documental/trd/{id}                           # Obtener elemento TRD
PUT    /api/clasifica-documental/trd/{id}                           # Actualizar elemento TRD
DELETE /api/clasifica-documental/trd/{id}                           # Eliminar elemento TRD
GET    /api/clasifica-documental/trd/plantilla/descargar            # Descargar plantilla Excel para importar
POST   /api/clasifica-documental/trd/import-trd                    # Importar TRD desde Excel
GET    /api/clasifica-documental/trd/estadisticas/{dependenciaId}  # Estadísticas por dependencia
GET    /api/clasifica-documental/trd/dependencia/{dependenciaId}   # Listar por dependencia
GET    /api/clasifica-documental/trd/por-dependencia/{dependenciaId} # Clasificaciones por dependencia (estructura jerárquica)

# Estadísticas avanzadas
GET    /api/clasifica-documental/trd/estadisticas/totales          # Estadísticas totales del sistema
GET    /api/clasifica-documental/trd/estadisticas/por-dependencias  # Estadísticas detalladas por dependencias

# Versiones TRD
GET    /api/clasifica-documental/trd-versiones                      # Listar versiones TRD
POST   /api/clasifica-documental/trd-versiones                     # Crear nueva versión
GET    /api/clasifica-documental/trd-versiones/{id}                 # Obtener versión específica
PUT    /api/clasifica-documental/trd-versiones/{id}                 # Actualizar versión
DELETE /api/clasifica-documental/trd-versiones/{id}                # Eliminar versión
POST   /api/clasifica-documental/trd-versiones/aprobar/{dependenciaId} # Aprobar versión
GET    /api/clasifica-documental/trd-versiones/pendientes/aprobar   # Versiones pendientes por aprobar
GET    /api/clasifica-documental/trd-versiones/estadisticas/{dependenciaId} # Estadísticas de versiones
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
GET    /api/ventanilla/sedes/{sedeId}/ventanillas           # Listar ventanillas por sede
POST   /api/ventanilla/sedes/{sedeId}/ventanillas           # Crear ventanilla
GET    /api/ventanilla/sedes/{sedeId}/ventanillas/{id}      # Obtener ventanilla
PUT    /api/ventanilla/sedes/{sedeId}/ventanillas/{id}      # Actualizar ventanilla
DELETE /api/ventanilla/sedes/{sedeId}/ventanillas/{id}      # Eliminar ventanilla

# Tipos documentales
POST   /api/ventanilla/ventanillas/{id}/tipos-documentales  # Configurar tipos documentales
GET    /api/ventanilla/ventanillas/{id}/tipos-documentales  # Listar tipos documentales

# Permisos
POST   /api/ventanilla/ventanillas/{ventanillaId}/permisos  # Asignar permisos
GET    /api/ventanilla/ventanillas/{ventanillaId}/usuarios-permitidos # Listar usuarios permitidos
DELETE /api/ventanilla/ventanillas/{ventanillaId}/permisos/{usuarioId} # Revocar permisos de un usuario
GET    /api/ventanilla/usuarios/{usuarioId}/ventanillas-permitidas # Ventanillas permitidas por usuario

# Radicaciones
GET    /api/ventanilla/radica-recibida                      # Listar radicaciones
POST   /api/ventanilla/radica-recibida                      # Crear radicación
GET    /api/ventanilla/radica-recibida/{id}                 # Obtener radicación
PUT    /api/ventanilla/radica-recibida/{id}                # Actualizar radicación
DELETE /api/ventanilla/radica-recibida/{id}                 # Eliminar radicación
GET    /api/ventanilla/radica-recibida/estadisticas        # Estadísticas de radicaciones
GET    /api/ventanilla/radica-recibida-admin/listar        # Listado administrativo
PUT    /api/ventanilla/radica-recibida/{id}/update-asunto   # Actualizar asunto de radicación
PUT    /api/ventanilla/radica-recibida/{id}/update-fechas   # Actualizar fechas (vencimiento y documento)
PUT    /api/ventanilla/radica-recibida/{id}/update-clasificacion-documental # Actualizar clasificación documental
POST   /api/ventanilla/radica-recibida/{id}/notificar      # Enviar notificación por correo electrónico

# Archivos de radicaciones
POST   /api/ventanilla/radica-recibida/{id}/archivos/upload # Subir archivo principal
POST   /api/ventanilla/radica-recibida/{id}/archivos/upload-adjuntos # Subir archivos adjuntos
GET    /api/ventanilla/radica-recibida/{id}/archivos/download # Descargar archivo principal
DELETE /api/ventanilla/radica-recibida/{id}/archivos/delete # Eliminar archivo principal
GET    /api/ventanilla/radica-recibida/{id}/archivos/info   # Información del archivo principal
GET    /api/ventanilla/radica-recibida/{id}/archivos/adjuntos/listar # Listar archivos adjuntos
GET    /api/ventanilla/radica-recibida/{id}/archivos/adjuntos/descargar # Descargar archivo adjunto
DELETE /api/ventanilla/radica-recibida/{id}/archivos/adjuntos/eliminar # Eliminar archivo adjunto
GET    /api/ventanilla/radica-recibida/{id}/archivos/historial/archivos-eliminados # Historial de eliminaciones

# Responsables
GET    /api/ventanilla/responsables                         # Listar responsables
POST   /api/ventanilla/responsables                         # Crear responsable
GET    /api/ventanilla/responsables/{id}                    # Obtener responsable
PUT    /api/ventanilla/responsables/{id}                    # Actualizar responsable
DELETE /api/ventanilla/responsables/{id}                   # Eliminar responsable
GET    /api/ventanilla/radica-recibida/{radica_reci_id}/responsables # Responsables por radicación
POST   /api/ventanilla/radica-recibida/{radica_reci_id}/responsables # Asignar responsable a radicación
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
- **Correo Electrónico**: Sistema de notificaciones por correo (Laravel Mail)
- **Sesiones**: Control avanzado de sesiones de usuario con múltiples dispositivos
- **Logging**: Sistema de logs global con Laravel Log

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

4. **Configurar variables de entorno en .env**
```env
# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ocobo_back
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password

# Aplicación
APP_NAME="OCOBO-BACK"
APP_ENV=local
APP_KEY=base64:... # Generado con php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000

# Correo electrónico (para notificaciones)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@ocobo.com"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:3000

# Archivos
FILESYSTEM_DISK=local
```

5. **Ejecutar migraciones y seeders**
```bash
# Ejecutar todas las migraciones
php artisan migrate

# Ejecutar todos los seeders
php artisan db:seed

# O ejecutar seeders individuales
php artisan db:seed --class=Database\\Seeders\\ControlAcceso\\RoleSeeder
php artisan db:seed --class=Database\\Seeders\\ControlAcceso\\UsersSeeder
php artisan db:seed --class=Database\\Seeders\\Configuracion\\DiviPoliSeed
php artisan db:seed --class=Database\\Seeders\\Configuracion\\SedesSeeder
php artisan db:seed --class=Database\\Seeders\\Configuracion\\ListaSeed
php artisan db:seed --class=Database\\Seeders\\Calidad\\OrganigramaSeed
php artisan db:seed --class=Database\\Seeders\\Gestion\\TercerosSeed
php artisan db:seed --class=Database\\Seeders\\ClasificacionDocumental\\TRDSeed
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
- **`config/permission.php`**: Configuración de roles y permisos (Spatie)
- **`config/filesystems.php`**: Configuración de almacenamiento de archivos
- **`config/sanctum.php`**: Configuración de Laravel Sanctum
- **`config/mail.php`**: Configuración de correo electrónico
- **`config/cors.php`**: Configuración CORS para API
- **`config/logging.php`**: Configuración de logs

### Variables de Entorno Completas

#### Aplicación
```env
APP_NAME="OCOBO-BACK"
APP_ENV=local|production|testing
APP_KEY=base64:...
APP_DEBUG=true|false
APP_URL=http://localhost:8000
APP_TIMEZONE=UTC
APP_LOCALE=es
APP_FALLBACK_LOCALE=en
```

#### Base de Datos
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ocobo_back
DB_USERNAME=root
DB_PASSWORD=
```

#### Correo Electrónico
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@ocobo.com"
MAIL_FROM_NAME="${APP_NAME}"
```

#### Sanctum
```env
SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:3000
```

#### Archivos
```env
FILESYSTEM_DISK=local
```

#### Sesiones
```env
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

### Seeders Disponibles

El proyecto incluye seeders completos con datos de prueba:

- **RoleSeeder**: Crea roles y permisos básicos del sistema
- **UsersSeeder**: Crea usuarios de prueba con diferentes roles
- **DiviPoliSeed**: Crea datos de división política (países, departamentos, municipios)
- **SedesSeeder**: Crea sedes de prueba
- **ListaSeed**: Crea listas maestras y sus detalles
- **OrganigramaSeed**: Crea estructura de organigrama con dependencias y oficinas
- **TercerosSeed**: Crea terceros de prueba
- **TRDSeed**: Crea datos de TRD (2 Series, 3 SubSeries, 3 Tipos de Documento) con estructura jerárquica

### Configuración de Correo Electrónico

El sistema utiliza correo electrónico para notificaciones de radicaciones. Configura las variables de entorno:

```env
MAIL_MAILER=smtp
MAIL_HOST=tu_servidor_smtp
MAIL_PORT=587
MAIL_USERNAME=tu_usuario
MAIL_PASSWORD=tu_contraseña
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@ocobo.com
MAIL_FROM_NAME="OCOBO-BACK"
```

Para desarrollo local, puedes usar servicios como Mailtrap o MailHog.

### Estructura de Rutas

Las rutas están organizadas por módulos en archivos separados:
- `routes/controlAcceso.php` - Rutas de control de acceso
- `routes/configuracion.php` - Rutas de configuración
- `routes/calidad.php` - Rutas de calidad
- `routes/clasifica_documental.php` - Rutas de clasificación documental
- `routes/gestion.php` - Rutas de gestión
- `routes/ventanilla.php` - Rutas de ventanilla única

#### 📐 Convenciones y Estructura de Rutas

**Reglas para definir rutas en el proyecto:**

1. **Organización por módulos:**
   - Cada módulo tiene su propio archivo de rutas en `routes/`
   - El prefix del módulo se define en `RouteServiceProvider` (ej: `api/calidad`, `api/config`)

2. **Estructura estándar de rutas:**
   ```php
   Route::middleware('auth:sanctum')->group(function () {
       Route::prefix('recurso')->name('modulo.recurso.')->group(function () {
           // Rutas específicas ANTES del resource (para evitar conflictos)
           Route::get('/ruta-especifica', [Controller::class, 'metodo'])->name('ruta-especifica');
           
           // Resource route DESPUÉS de las rutas específicas
           Route::apiResource('', Controller::class)
               ->parameters(['' => 'recurso'])
               ->names([
                   'index' => 'index',
                   'store' => 'store',
                   'show' => 'show',
                   'update' => 'update',
                   'destroy' => 'destroy'
               ])->except('create', 'edit');
       });
   });
   ```

3. **Nomenclatura de nombres de rutas:**
   - Formato: `{modulo}.{recurso}.{accion}`
   - Ejemplo: `calidad.organigrama.index`, `config.sedes.estadisticas`
   - Permite buscar rutas por módulo: `php artisan route:list --name="calidad"`

4. **Orden de rutas:**
   - **SIEMPRE** definir rutas específicas ANTES del `apiResource`
   - Esto evita conflictos donde Laravel interpreta `/recurso/estadisticas` como `/recurso/{id}`

5. **Parámetros de rutas:**
   - Usar `->parameters(['' => 'nombreRecurso'])` en `apiResource` para nombres descriptivos
   - Ejemplo: `{organigrama}` en lugar de `{}`

6. **Ejemplo completo:**
   ```php
   /**
    * Rutas del módulo Calidad
    * Prefix aplicado desde RouteServiceProvider: /api/calidad
    * Rutas finales: /api/calidad/organigrama/*
    */
   Route::middleware('auth:sanctum')->group(function () {
       Route::prefix('organigrama')->name('calidad.organigrama.')->group(function () {
           // Rutas específicas (ANTES del resource)
           Route::get('/dependencias', [Controller::class, 'listDependencias'])->name('dependencias');
           Route::get('/estadisticas', [Controller::class, 'estadisticas'])->name('estadisticas');
           
           // Resource route (DESPUÉS de las rutas específicas)
           Route::apiResource('', Controller::class)
               ->parameters(['' => 'organigrama'])
               ->names([
                   'index' => 'index',
                   'store' => 'store',
                   'show' => 'show',
                   'update' => 'update',
                   'destroy' => 'destroy'
               ])->except('create', 'edit');
       });
   });
   ```

7. **Resultado final:**
   - Rutas: `/api/{modulo}/{recurso}/*`
   - Nombres: `{modulo}.{recurso}.{accion}`
   - Búsqueda: `php artisan route:list --name="{modulo}"`

## 📚 Documentación de la API

### Autenticación

La API utiliza Laravel Sanctum para autenticación. Todas las rutas (excepto login/register) requieren un token Bearer.

#### Login

```bash
POST /api/login
Content-Type: application/json

{
    "email": "usuario@example.com",
    "password": "password"
}
```

**Respuesta exitosa (200):**
```json
{
    "status": true,
    "message": "Login exitoso",
    "data": {
        "user": {
            "id": 1,
            "nombres": "Juan",
            "apellidos": "Pérez",
            "email": "juan.perez@example.com",
            "roles": [...],
            "permissions": [...],
            "cargo": {...},
            "oficina": {...},
            "dependencia": {...}
        },
        "access_token": "1|token...",
        "token_type": "Bearer"
    }
}
```

#### Registro

```bash
POST /api/register
Content-Type: application/json

{
    "num_docu": "1234567890",
    "nombres": "Juan",
    "apellidos": "Pérez",
    "email": "juan.perez@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "tel": "1234567890",
    "movil": "0987654321",
    "dir": "Dirección",
    "role": "Usuario" // Opcional
}
```

#### Obtener Usuario Autenticado

```bash
GET /api/getme
Authorization: Bearer {token}
```

Retorna información completa del usuario incluyendo:
- Datos personales
- Roles y permisos
- Cargo activo
- Oficina y dependencia
- Configuración de notificaciones

#### Usar Token en Requests

Todas las rutas protegidas requieren el header:
```
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

- `200` - OK (Operación exitosa)
- `201` - Created (Recurso creado exitosamente)
- `400` - Bad Request (Solicitud incorrecta)
- `401` - Unauthorized (No autenticado)
- `403` - Forbidden (Sin permisos)
- `404` - Not Found (Recurso no encontrado)
- `422` - Validation Error (Error de validación)
- `500` - Server Error (Error interno del servidor)

### Parámetros de Query Comunes

Muchos endpoints soportan parámetros de query para filtrado y paginación:

```bash
# Filtros comunes
?search=texto                    # Búsqueda por texto
?solo_activos=true               # Solo registros activos
?incluir_cargos=true             # Incluir información de cargos
?con_oficina=true                # Incluir información de oficina
?page=1                          # Número de página
?per_page=15                     # Registros por página
?sort=nombre&order=asc           # Ordenamiento
```

### Ejemplos de Uso

#### Listar usuarios con filtros
```bash
GET /api/control-acceso/users?solo_activos=true&incluir_cargos=true&search=Juan
```

#### Crear radicación con archivo
```bash
POST /api/ventanilla/radica-recibida
Content-Type: multipart/form-data

{
    "asunto": "Solicitud de información",
    "fecha_documento": "2024-12-01",
    "ventanilla_id": 1,
    "archivo": [archivo]
}
```

## 🛠️ Stack Tecnológico

### Backend
- **Framework**: Laravel 10.x
- **PHP**: 8.1+
- **Base de datos**: MySQL/MariaDB
- **Autenticación**: Laravel Sanctum 3.2
- **Autorización**: Spatie Laravel-Permission 6.9
- **Validaciones**: Form Request Classes
- **API**: RESTful con ApiResponseTrait

### Dependencias Principales
```json
{
  "laravel/framework": "^10.10",
  "laravel/sanctum": "3.2",
  "spatie/laravel-permission": "^6.9",
  "phpoffice/phpspreadsheet": "^3.4",
  "jenssegers/agent": "^2.6",
  "guzzlehttp/guzzle": "^7.2"
}
```

### Frontend Assets
- **Vite**: 5.0.0 (Build tool)
- **Axios**: 1.6.4 (HTTP client)
- **Laravel Vite Plugin**: 1.0.0

### Funcionalidades Técnicas
- **Migraciones**: Control de versiones de BD con seeders
- **Modelos Eloquent**: Relaciones complejas y scopes avanzados
- **Helpers Personalizados**: ArchivoHelper para gestión de archivos
- **Logging**: Sistema de logs avanzado con Laravel Log
- **Importación**: PhpSpreadsheet para archivos Excel
- **Estructuras Jerárquicas**: Relaciones recursivas padre-hijo
- **Configuración Dinámica**: Sistema de configuraciones centralizadas
- **Rate Limiting**: 60 requests por minuto por usuario/IP

### Características de Desarrollo
- **Request Classes**: Validaciones centralizadas y reutilizables
- **Traits**: Código reutilizable (ApiResponseTrait)
- **Scopes**: Filtros de consulta reutilizables en modelos
- **Seeders**: Datos de prueba y configuración inicial
- **Documentación**: PHPDoc completo en controladores
- **Estructura Modular**: Organización por módulos funcionales
- **PSR Standards**: Código siguiendo estándares PSR

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
- **Archivos de Radicaciones**: Gestión de archivos principales y adjuntos con historial
- **Descarga Segura**: Sistema de descarga de archivos con validaciones de permisos

### 📧 **Sistema de Notificaciones**

- **Notificaciones por Correo**: Envío automático de notificaciones de radicaciones
- **Configuración por Usuario**: Cada usuario puede configurar sus preferencias de notificación
- **RadicadoNotification**: Clase de correo personalizada para notificaciones de radicaciones
- **Plantillas de Correo**: Sistema de plantillas para correos electrónicos
- **Historial de Notificaciones**: Registro de notificaciones enviadas

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
- **Datos de Prueba TRD**: Seeder completo con 8 registros (2 Series, 3 SubSeries, 3 Tipos de Documento)
- **Estructura Jerárquica**: Datos organizados en jerarquía padre-hijo para pruebas completas

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

### Ejecutar Tests

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests específicos
php artisan test --filter UserControllerTest

# Ejecutar tests con cobertura
php artisan test --coverage

# Ejecutar tests en modo verbose
php artisan test -v
```

### Tipos de Tests

- **Unit Tests**: Pruebas de unidades individuales (modelos, helpers)
- **Feature Tests**: Pruebas de funcionalidades completas (endpoints, flujos)
- **Integration Tests**: Pruebas de integración entre componentes

### Estructura de Tests

```
tests/
├── Feature/          # Tests de funcionalidades
│   └── ExampleTest.php
└── Unit/            # Tests unitarios
    └── ExampleTest.php
```

### Mejores Prácticas

- Escribir tests antes de implementar nuevas funcionalidades (TDD)
- Mantener cobertura de código alta (>80%)
- Usar factories para datos de prueba
- Limpiar base de datos después de cada test

## 📁 Estructura del Proyecto

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                   # Controlador de autenticación
│   │   ├── ControlAcceso/          # Controladores de control de acceso
│   │   ├── Configuracion/          # Controladores de configuración
│   │   ├── Calidad/                # Controladores de calidad
│   │   ├── ClasificacionDocumental/ # Controladores de clasificación documental
│   │   ├── VentanillaUnica/        # Controladores de ventanilla única
│   │   ├── Gestion/                # Controladores de gestión
│   │   └── LogGlobalController.php # Controlador de logs globales
│   ├── Requests/                   # Form Request classes (validaciones)
│   │   ├── Auth/                   # Requests de autenticación
│   │   ├── ControlAcceso/          # Requests de control de acceso
│   │   ├── Configuracion/          # Requests de configuración
│   │   ├── Calidad/                # Requests de calidad
│   │   ├── ClasificacionDocumental/ # Requests de clasificación documental
│   │   ├── VentanillaUnica/        # Requests de ventanilla única
│   │   └── Gestion/                # Requests de gestión
│   ├── Resources/                  # API Resources (transformaciones)
│   └── Traits/                     # Traits compartidos (ApiResponseTrait)
├── Models/                         # Modelos Eloquent
│   ├── ControlAcceso/              # Modelos de control de acceso
│   ├── Configuracion/              # Modelos de configuración
│   ├── Calidad/                    # Modelos de calidad
│   ├── ClasificacionDocumental/    # Modelos de clasificación documental
│   ├── VentanillaUnica/            # Modelos de ventanilla única
│   ├── Gestion/                    # Modelos de gestión
│   └── User.php                    # Modelo de usuario principal
├── Helpers/                        # Helpers personalizados
│   └── ArchivoHelper.php           # Helper para gestión de archivos
├── Mail/                           # Clases de correo electrónico
│   └── RadicadoNotification.php    # Notificación de radicaciones
├── Listeners/                      # Event Listeners
│   └── StoreUserSession.php        # Listener para almacenar sesiones
└── Providers/                      # Service Providers
    └── RouteServiceProvider.php    # Configuración de rutas

database/
├── migrations/                     # Migraciones de base de datos
├── seeders/                        # Seeders de datos de prueba
│   ├── ControlAcceso/              # Seeders de control de acceso
│   ├── Configuracion/              # Seeders de configuración
│   ├── Calidad/                    # Seeders de calidad
│   ├── ClasificacionDocumental/    # Seeders de clasificación documental
│   ├── Gestion/                    # Seeders de gestión
│   └── DatabaseSeeder.php          # Seeder principal
└── factories/                      # Factories para testing

routes/
├── api.php                         # Rutas de autenticación
├── controlAcceso.php               # Rutas de control de acceso
├── configuracion.php               # Rutas de configuración
├── calidad.php                     # Rutas de calidad
├── clasifica_documental.php        # Rutas de clasificación documental
├── gestion.php                     # Rutas de gestión
├── ventanilla.php                  # Rutas de ventanilla única
└── web.php                         # Rutas web
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
- ✅ Corrección de rutas para evitar conflictos (estadísticas en `/users/stats/estadisticas`)
- ✅ Sistema completo de gestión de cargos de usuarios (UserCargoController)
- ✅ Endpoints para usuarios con cargos, cargos activos y relaciones organizacionales
- ✅ Gestión de sesiones de usuarios con control de dispositivos
- ✅ Sistema de configuración de notificaciones por usuario
- ✅ Endpoints de roles y permisos mejorados

### **Módulo Configuración**
- ✅ Migración de `numeracion_unificada` de `config_sedes` a `config_varias`
- ✅ Implementación de información empresarial en `config_varias`
- ✅ Sistema de gestión de logos empresariales con ArchivoHelper
- ✅ Configuración de backups automáticos y frecuencia
- ✅ Optimización de ConfigVariasController con métodos simplificados
- ✅ Validaciones mejoradas para archivos y configuraciones
- ✅ Sistema de almacenamiento con múltiples discos
- ✅ Endpoints específicos para numeración unificada con validaciones booleanas
- ✅ Gestión completa de servidores de archivos con estadísticas
- ✅ Endpoints mejorados de división política (recursivo, por tipo)
- ✅ Gestión de listas maestras con detalles y estados activos
- ✅ Ventanillas integradas dentro del módulo de configuración

### **Módulo Clasificación Documental**
- ✅ Controladores completamente optimizados con ApiResponseTrait
- ✅ Sistema de versiones TRD con estados y workflow de aprobación
- ✅ Validaciones jerárquicas robustas con Form Requests
- ✅ Importación masiva desde Excel con PhpSpreadsheet
- ✅ Descarga de plantilla Excel para importación
- ✅ Estadísticas avanzadas con análisis comparativo y métricas estadísticas
- ✅ Modelos mejorados con scopes, relaciones y métodos de utilidad
- ✅ Rutas organizadas y documentadas con prefijos lógicos
- ✅ Sistema de estadísticas con rankings, medianas y desviaciones estándar
- ✅ Endpoint para clasificaciones por dependencia en estructura jerárquica
- ✅ **Datos de Prueba TRD**: Seeder completo con 8 registros (2 Series, 3 SubSeries, 3 Tipos de Documento)
- ✅ **Estructura Jerárquica**: Datos organizados en jerarquía padre-hijo para pruebas completas

### **Módulo Calidad**
- ✅ Gestión completa de organigramas con estructura jerárquica
- ✅ Soporte para relaciones padre-hijo recursivas
- ✅ Endpoint optimizado para listar dependencias en estructura de árbol
- ✅ Validaciones robustas para nodos del organigrama
- ✅ Estadísticas detalladas del organigrama
- ✅ Sistema de scopes para filtrado por tipo y nivel

### **Módulo Ventanilla Única**
- ✅ Gestión completa de radicaciones recibidas con estadísticas
- ✅ Sistema de actualización parcial (asunto, fechas, clasificación documental)
- ✅ Notificaciones por correo electrónico de radicaciones
- ✅ Gestión de archivos principales y adjuntos
- ✅ Historial de eliminaciones de archivos
- ✅ Sistema de responsables por radicación
- ✅ Endpoints mejorados de permisos y tipos documentales

### **Módulo Gestión**
- ✅ Gestión completa de terceros con CRUD
- ✅ Sistema de filtrado avanzado de terceros
- ✅ Estadísticas de terceros

## 🐛 Troubleshooting

### Problemas Comunes

#### Error: "SQLSTATE[HY000] [2002] Connection refused"
**Solución**: Verifica que MySQL esté corriendo y que las credenciales en `.env` sean correctas.

#### Error: "Class 'App\...' not found"
**Solución**: Ejecuta `composer dump-autoload` para regenerar el autoloader.

#### Error: "419 Page Expired" o problemas con tokens CSRF
**Solución**: 
- Verifica que `APP_KEY` esté configurado en `.env`
- Ejecuta `php artisan key:generate`
- Limpia la caché: `php artisan cache:clear`

#### Error: "Storage link not found"
**Solución**: Ejecuta `php artisan storage:link` para crear el enlace simbólico.

#### Problemas con permisos de archivos
**Solución**: Asegúrate de que las carpetas `storage/` y `bootstrap/cache/` tengan permisos de escritura:
```bash
chmod -R 775 storage bootstrap/cache
```

#### Error al enviar correos electrónicos
**Solución**: 
- Verifica la configuración de correo en `.env`
- Para desarrollo, usa Mailtrap o MailHog
- Revisa los logs en `storage/logs/laravel.log`

#### Token de Sanctum expirado
**Solución**: 
- Usa el endpoint `/api/refresh` para renovar el token
- O inicia sesión nuevamente con `/api/login`

#### Problemas con seeders
**Solución**: 
- Asegúrate de ejecutar las migraciones primero: `php artisan migrate`
- Si hay errores de foreign keys, ejecuta los seeders en orden
- Revisa que las relaciones entre modelos estén correctamente definidas

### Comandos Útiles

```bash
# Limpiar todas las cachés
php artisan optimize:clear

# Regenerar autoloader
composer dump-autoload

# Ver rutas disponibles
php artisan route:list

# Ver rutas de un módulo específico
php artisan route:list --name="calidad"

# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Ejecutar migraciones con rollback
php artisan migrate:rollback

# Verificar estado de la aplicación
php artisan about
```

## 🤝 Contribución

### Proceso de Contribución

1. **Fork el proyecto** y clónalo localmente
2. **Crear una rama** para tu feature:
   ```bash
   git checkout -b feature/nombre-de-la-feature
   ```
3. **Hacer cambios** siguiendo los estándares del proyecto
4. **Commit tus cambios** con mensajes descriptivos:
   ```bash
   git commit -m 'feat: Agregar nueva funcionalidad X'
   ```
5. **Push a la rama**:
   ```bash
   git push origin feature/nombre-de-la-feature
   ```
6. **Abrir un Pull Request** con descripción detallada

### Estándares de Código

- Seguir **PSR-12** (PHP Coding Standards)
- Usar **Conventional Commits** para mensajes de commit:
  - `feat:` Nueva funcionalidad
  - `fix:` Corrección de bug
  - `docs:` Documentación
  - `style:` Formato de código
  - `refactor:` Refactorización
  - `test:` Tests
  - `chore:` Tareas de mantenimiento

### Checklist Antes de PR

- [ ] Código sigue los estándares PSR-12
- [ ] Tests pasan (`php artisan test`)
- [ ] Documentación actualizada
- [ ] Sin errores de linting
- [ ] Código comentado donde sea necesario
- [ ] Sin código comentado o deprecado
- [ ] Variables de entorno documentadas (si aplica)

### Code Review

- Todos los PRs requieren revisión
- Responder a comentarios de revisión
- Mantener el PR actualizado con la rama principal

## 🔒 Seguridad

### Prácticas de Seguridad Implementadas

- **Autenticación**: Laravel Sanctum con tokens seguros
- **Autorización**: Control de acceso basado en roles (RBAC)
- **Validación**: Validación estricta de entrada con Form Requests
- **Protección CSRF**: Middleware CSRF en todas las rutas web
- **Sanitización**: Sanitización de archivos subidos
- **Encriptación**: Contraseñas encriptadas con bcrypt
- **Rate Limiting**: 60 requests por minuto por usuario/IP
- **SQL Injection**: Protección mediante Eloquent ORM
- **XSS Protection**: Escapado automático en vistas Blade
- **Headers de Seguridad**: Headers HTTP de seguridad configurados

### Recomendaciones

- Nunca commitear archivos `.env` con credenciales
- Usar contraseñas fuertes en producción
- Mantener dependencias actualizadas
- Revisar logs regularmente
- Implementar backups regulares
- Usar HTTPS en producción

## 📊 Performance

### Optimizaciones Implementadas

- **Eager Loading**: Carga optimizada de relaciones para evitar N+1 queries
- **Índices de BD**: Índices en campos frecuentemente consultados
- **Caché**: Sistema de caché para consultas frecuentes
- **Lazy Loading**: Carga diferida de recursos pesados
- **Compresión**: Compresión de respuestas HTTP
- **Optimización de Consultas**: Consultas optimizadas con select específicos

### Mejores Prácticas

- Usar `with()` para cargar relaciones necesarias
- Implementar paginación en listados grandes
- Optimizar consultas con `select()` específico
- Usar índices en campos de búsqueda frecuente
- Limitar resultados con `take()` o `limit()`

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Soporte

Para soporte técnico o preguntas sobre el proyecto, contactar al equipo de desarrollo.

## 🗺️ Roadmap

### Próximas Características

- [ ] Sistema de reportes avanzados
- [ ] Dashboard de métricas en tiempo real
- [ ] Integración con servicios externos
- [ ] API de webhooks
- [ ] Sistema de auditoría completo
- [ ] Exportación de datos a múltiples formatos
- [ ] Mejoras en el sistema de notificaciones
- [ ] Optimizaciones de performance adicionales

### Versiones Futuras

- **v2.1**: Mejoras en UI/UX y nuevas funcionalidades
- **v2.2**: Integraciones adicionales
- **v3.0**: Refactorización mayor y nuevas arquitecturas

## 📊 Modelo de Datos

### Entidades Principales

#### Usuarios y Autenticación
- **users**: Usuarios del sistema
- **roles**: Roles del sistema (Spatie Permission)
- **permissions**: Permisos del sistema
- **model_has_roles**: Relación usuarios-roles
- **model_has_permissions**: Relación usuarios-permisos
- **users_sessions**: Sesiones de usuarios
- **user_notification_settings**: Configuración de notificaciones

#### Organización
- **calidad_organigrama**: Estructura organizacional (Dependencias, Oficinas, Cargos)
- **users_cargos**: Asignación de cargos a usuarios con historial
- **config_sedes**: Sedes de la organización
- **users_sedes**: Relación muchos a muchos usuarios-sedes
- **config_division_politica**: División política (Países, Departamentos, Municipios)

#### Configuración
- **config_varias**: Configuraciones varias del sistema
- **config_listas**: Listas maestras
- **config_listas_detalles**: Detalles de listas maestras
- **config_server_archivos**: Servidores de archivos
- **config_ventanillas**: Ventanillas de configuración
- **config_num_radicado**: Configuración de numeración de radicados

#### Clasificación Documental
- **clasificacion_documental_trd**: Elementos TRD (Series, SubSeries, Tipos de Documento)
- **clasificacion_documental_trd_versions**: Versiones de TRD

#### Ventanilla Única
- **ventanilla_unica**: Ventanillas únicas por sede
- **ventanilla_permisos**: Permisos de usuarios a ventanillas
- **ventanilla_radica_reci**: Radicaciones recibidas
- **ventanilla_radica_reci_archivos**: Archivos de radicaciones
- **ventanilla_radica_reci_archivos_eliminados**: Historial de archivos eliminados
- **ventanilla_radica_reci_responsables**: Responsables de radicaciones

#### Gestión
- **gestion_terceros**: Terceros del sistema

#### Relaciones Principales

```
User
├── hasMany: UserCargo (cargos asignados)
├── belongsToMany: ConfigSede (sedes)
├── belongsToMany: configVentanilla (ventanillas)
├── belongsToMany: VentanillaUnica (ventanillas permitidas)
├── hasMany: UsersSession (sesiones)
├── hasOne: UserNotificationSetting (configuración notificaciones)
└── hasMany: VentanillaRadicaReci (radicaciones)

CalidadOrganigrama
├── hasMany: CalidadOrganigrama (children - estructura jerárquica)
├── belongsTo: CalidadOrganigrama (parent)
├── hasMany: ClasificacionDocumentalTRD (TRDs asociadas)
└── hasMany: UserCargo (asignaciones de usuarios)

ClasificacionDocumentalTRD
├── belongsTo: CalidadOrganigrama (dependencia)
├── belongsTo: ClasificacionDocumentalTRD (parent - jerarquía)
└── hasMany: ClasificacionDocumentalTRD (children)

VentanillaRadicaReci
├── belongsTo: VentanillaUnica (ventanilla)
├── belongsTo: ClasificacionDocumentalTRD (clasificación)
├── hasMany: VentanillaRadicaReciArchivo (archivos)
└── hasMany: VentanillaRadicaReciResponsa (responsables)
```

## 🚀 Deployment

### Requisitos de Producción

- PHP 8.1+ con extensiones: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- MySQL 5.7+ o MariaDB 10.3+
- Composer 2.0+
- Node.js 18+ y NPM (para assets)
- Servidor web: Nginx o Apache
- SSL/HTTPS configurado

### Pasos de Deployment

1. **Preparar servidor**
   ```bash
   # Actualizar sistema
   sudo apt update && sudo apt upgrade -y
   
   # Instalar PHP y extensiones
   sudo apt install php8.1-fpm php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl
   
   # Instalar MySQL
   sudo apt install mysql-server
   
   # Instalar Nginx
   sudo apt install nginx
   ```

2. **Configurar aplicación**
   ```bash
   # Clonar repositorio
   git clone [repo-url] /var/www/ocobo-back
   cd /var/www/ocobo-back
   
   # Instalar dependencias
   composer install --optimize-autoloader --no-dev
   npm install && npm run build
   
   # Configurar .env
   cp .env.example .env
   nano .env  # Configurar variables de producción
   
   # Generar key
   php artisan key:generate
   
   # Ejecutar migraciones
   php artisan migrate --force
   
   # Optimizar para producción
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Configurar Nginx**
   ```nginx
   server {
       listen 80;
       server_name tu-dominio.com;
       root /var/www/ocobo-back/public;
       
       add_header X-Frame-Options "SAMEORIGIN";
       add_header X-Content-Type-Options "nosniff";
       
       index index.php;
       
       charset utf-8;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location = /favicon.ico { access_log off; log_not_found off; }
       location = /robots.txt  { access_log off; log_not_found off; }
       
       error_page 404 /index.php;
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
       
       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```

4. **Configurar permisos**
   ```bash
   sudo chown -R www-data:www-data /var/www/ocobo-back
   sudo chmod -R 755 /var/www/ocobo-back
   sudo chmod -R 775 /var/www/ocobo-back/storage
   sudo chmod -R 775 /var/www/ocobo-back/bootstrap/cache
   ```

5. **Configurar SSL (Let's Encrypt)**
   ```bash
   sudo apt install certbot python3-certbot-nginx
   sudo certbot --nginx -d tu-dominio.com
   ```

### Variables de Entorno de Producción

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ocobo_production
DB_USERNAME=usuario_seguro
DB_PASSWORD=contraseña_segura

MAIL_MAILER=smtp
MAIL_HOST=servidor-smtp.com
MAIL_PORT=587
MAIL_USERNAME=usuario
MAIL_PASSWORD=contraseña
MAIL_ENCRYPTION=tls

SANCTUM_STATEFUL_DOMAINS=tu-dominio.com,www.tu-dominio.com
```

### Optimizaciones de Producción

```bash
# Cachear configuración
php artisan config:cache

# Cachear rutas
php artisan route:cache

# Cachear vistas
php artisan view:cache

# Optimizar autoloader
composer install --optimize-autoloader --no-dev

# Optimizar opcache (en php.ini)
opcache.enable=1
opcache.memory_consumption=256
```

### Rollback

```bash
# Revertir migraciones
php artisan migrate:rollback --step=1

# Limpiar cachés
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## 📝 Comandos Artisan Útiles

### Desarrollo

```bash
# Limpiar todas las cachés
php artisan optimize:clear

# Ver rutas disponibles
php artisan route:list

# Ver rutas de un módulo específico
php artisan route:list --name="calidad"

# Tinker (consola interactiva)
php artisan tinker

# Ver información del sistema
php artisan about
```

### Base de Datos

```bash
# Ejecutar migraciones
php artisan migrate

# Revertir última migración
php artisan migrate:rollback

# Revertir todas las migraciones
php artisan migrate:reset

# Refrescar base de datos
php artisan migrate:fresh

# Refrescar y ejecutar seeders
php artisan migrate:fresh --seed

# Crear nueva migración
php artisan make:migration nombre_migracion

# Crear seeder
php artisan make:seeder NombreSeeder
```

### Caché

```bash
# Limpiar caché de aplicación
php artisan cache:clear

# Limpiar caché de configuración
php artisan config:clear

# Cachear configuración
php artisan config:cache

# Limpiar caché de rutas
php artisan route:clear

# Cachear rutas
php artisan route:cache

# Limpiar caché de vistas
php artisan view:clear

# Cachear vistas
php artisan view:cache
```

### Testing

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests con cobertura
php artisan test --coverage

# Ejecutar tests específicos
php artisan test --filter NombreTest
```

## 📧 Monitoreo y Logging

### Sistema de Logs

El sistema utiliza Laravel Log para registro de eventos:

- **Ubicación**: `storage/logs/laravel.log`
- **Niveles**: emergency, alert, critical, error, warning, notice, info, debug
- **Rotación**: Automática diaria
- **Retención**: 30 días (configurable)

### Ver Logs en Tiempo Real

```bash
# Linux/Mac
tail -f storage/logs/laravel.log

# Windows PowerShell
Get-Content storage/logs/laravel.log -Wait
```

### Niveles de Log

```php
// En controladores
Log::info('Usuario creado', ['user_id' => $user->id]);
Log::warning('Intento de acceso no autorizado');
Log::error('Error al procesar radicación', ['error' => $e->getMessage()]);
```

### Configuración de Logging

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
LOG_DEPRECATIONS_CHANNEL=null
```

### Monitoreo Recomendado

- **Errores**: Monitorear `storage/logs/laravel.log` para errores críticos
- **Performance**: Revisar tiempos de respuesta de endpoints
- **Base de Datos**: Monitorear consultas lentas
- **Espacio en Disco**: Monitorear `storage/` para archivos subidos

## 💾 Backups

### Estrategia de Backups

#### Base de Datos

```bash
# Backup manual de MySQL
mysqldump -u usuario -p ocobo_back > backup_$(date +%Y%m%d).sql

# Restaurar backup
mysql -u usuario -p ocobo_back < backup_20241201.sql
```

#### Archivos

```bash
# Backup de storage
tar -czf storage_backup_$(date +%Y%m%d).tar.gz storage/

# Backup completo
tar -czf ocobo_backup_$(date +%Y%m%d).tar.gz \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='.git' \
    .
```

### Backups Automáticos

Crear script de backup automático (`backup.sh`):

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/ocobo"

# Crear directorio si no existe
mkdir -p $BACKUP_DIR

# Backup de base de datos
mysqldump -u usuario -pcontraseña ocobo_back > $BACKUP_DIR/db_$DATE.sql

# Backup de archivos
tar -czf $BACKUP_DIR/files_$DATE.tar.gz storage/

# Eliminar backups antiguos (más de 30 días)
find $BACKUP_DIR -type f -mtime +30 -delete

echo "Backup completado: $DATE"
```

Agregar a crontab:
```bash
# Backup diario a las 2 AM
0 2 * * * /ruta/al/script/backup.sh
```

### Configuración de Backups en ConfigVarias

El sistema permite configurar frecuencia de backups desde `config_varias`:
- `backup_frecuencia`: Diario, Semanal, Mensual
- `backup_automatico`: true/false

## 🔗 Ejemplos de Integración

### Integración con Frontend (React)

```javascript
// Configuración de API
const API_BASE_URL = 'http://localhost:8000/api';

// Servicio de autenticación
class AuthService {
  async login(email, password) {
    const response = await fetch(`${API_BASE_URL}/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
    const data = await response.json();
    if (data.status) {
      localStorage.setItem('token', data.data.access_token);
    }
    return data;
  }

  async getMe() {
    const token = localStorage.getItem('token');
    const response = await fetch(`${API_BASE_URL}/getme`, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    return await response.json();
  }
}

// Uso en componente
const { data } = await authService.getMe();
console.log(data.user);
```

### Integración con Axios

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

// Interceptor para agregar token
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Ejemplo de uso
const getUsers = async () => {
  const response = await api.get('/control-acceso/users');
  return response.data;
};
```

### Colección Postman

Importar colección de Postman con:
- Variables de entorno
- Pre-request scripts para autenticación
- Tests automáticos
- Ejemplos de requests

## ❓ FAQ (Preguntas Frecuentes)

### ¿Cómo resetear la contraseña de un usuario?

```bash
php artisan tinker
$user = User::where('email', 'usuario@example.com')->first();
$user->password = Hash::make('nueva_contraseña');
$user->save();
```

### ¿Cómo crear un usuario administrador?

```bash
php artisan tinker
$user = User::create([
    'nombres' => 'Admin',
    'apellidos' => 'Sistema',
    'email' => 'admin@example.com',
    'password' => Hash::make('password'),
    'estado' => 1
]);
$user->assignRole('Administrador');
```

### ¿Cómo limpiar tokens expirados de Sanctum?

```bash
php artisan tinker
DB::table('personal_access_tokens')
    ->where('expires_at', '<', now())
    ->delete();
```

### ¿Cómo regenerar todas las cachés?

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### ¿Cómo ver las rutas disponibles?

```bash
php artisan route:list
php artisan route:list --name="calidad"
php artisan route:list --path="api/ventanilla"
```

### ¿Cómo importar datos masivos de TRD?

1. Descargar plantilla: `GET /api/clasifica-documental/trd/plantilla/descargar`
2. Llenar plantilla Excel con datos
3. Importar: `POST /api/clasifica-documental/trd/import-trd`

### ¿Cómo configurar correo para notificaciones?

Editar `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=tu-servidor-smtp.com
MAIL_PORT=587
MAIL_USERNAME=usuario
MAIL_PASSWORD=contraseña
MAIL_ENCRYPTION=tls
```

### ¿Cómo solucionar error 419 (CSRF Token)?

- Verificar que `APP_KEY` esté configurado
- Ejecutar `php artisan key:generate`
- Limpiar caché: `php artisan cache:clear`

### ¿Cómo aumentar el tamaño máximo de archivos?

Editar `php.ini`:
```ini
upload_max_filesize = 50M
post_max_size = 50M
```

Y en `.env`:
```env
MAX_FILE_SIZE=52428800  # 50MB en bytes
```

## 📋 Changelog

### Versión 2.0 (Diciembre 2024)

#### Nuevas Características
- ✅ Sistema completo de gestión de usuarios con cargos
- ✅ Sistema de sesiones de usuarios con múltiples dispositivos
- ✅ Configuración de notificaciones por usuario
- ✅ Sistema de TRD con versiones y aprobación
- ✅ Importación masiva de TRD desde Excel
- ✅ Sistema de radicaciones con archivos y responsables
- ✅ Notificaciones por correo electrónico
- ✅ Estadísticas avanzadas en todos los módulos
- ✅ Sistema de organigrama con estructura jerárquica
- ✅ Gestión de terceros con filtros avanzados

#### Mejoras
- ✅ Optimización de consultas con Eager Loading
- ✅ Validaciones robustas con Form Requests
- ✅ Sistema de respuestas estandarizado (ApiResponseTrait)
- ✅ Documentación PHPDoc completa
- ✅ Seeders con datos de prueba

#### Correcciones
- ✅ Corrección de conflictos de rutas
- ✅ Optimización de relaciones de modelos
- ✅ Mejora en manejo de errores

---

**Desarrollado con ❤️ usando Laravel**
