# OCOBO-BACK

Aplicación gestora del proceso de gestión documental desarrollada en Laravel.

![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat-square&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?style=flat-square&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
![Version](https://img.shields.io/badge/Version-2.1-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-En%20Desarrollo-yellow?style=flat-square)

**Versión**: 2.2  
**Última actualización**: Abril 2026  
**Estado**: En desarrollo activo

## 📋 Descripción

OCOBO-BACK es una aplicación web desarrollada en Laravel que gestiona procesos documentales de manera eficiente y organizada. El sistema proporciona una API RESTful robusta para la gestión de usuarios, roles, permisos, configuración del sistema, gestión documental, clasificación documental y control de calidad.

## 🚀 Características Principales

- **Autenticación y Autorización**: Sistema completo de autenticación con Sanctum (cookies HttpOnly) y control de acceso basado en roles
- **Gestión de Usuarios**: CRUD completo de usuarios con gestión de archivos (avatars, firmas)
- **Control de Acceso**: Sistema de roles y permisos con Spatie Laravel-Permission
- **Gestión de Cargos**: Sistema completo de asignación de cargos a usuarios con historial y estadísticas
- **Gestión de Terceros**: CRUD de terceros con filtros y estadísticas
- **Configuración del Sistema**: Módulos de configuración para división política, sedes, listas, etc.
- **Gestión Documental**: Procesos de radicación y clasificación documental
- **Clasificación Documental**: Sistema completo de TRD (Tabla de Retención Documental) con versiones y datos de prueba
- **Control de Calidad**: Gestión de organigramas y estructuras organizacionales
- **Ventanilla Única**: Sistema completo de gestión de ventanillas y radicaciones
- **Calendario Días No Hábiles**: Gestión de festivos con cálculo de vencimientos (ISO 27001)
- **Semáforo de Vencimientos**: Sistema configurable de alertas visuales para términos legales
- **API RESTful**: Endpoints bien documentados y estructurados
- **Validaciones Robustas**: Form Request classes para validaciones centralizadas
- **Manejo de Errores**: Sistema consistente de respuestas de error
- **Estadísticas Avanzadas**: Análisis detallado de datos y métricas
- **Trazabilidad ISO 27001**: Sistema de logs de autenticación y actividad

---

## 🔐 Sistema de Autenticación (BFF Pattern)

### Arquitectura de Sesión

El sistema implementa autenticación mediante **Laravel Sanctum en modo SPA** con cookies HttpOnly:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        FLUJO DE AUTENTICACIÓN CON COOKIES                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  FRONTEND (Next.js)              PROXY (Next.js)         BACKEND (Laravel)  │
│                                                                              │
│  1. /sanctum/csrf-cookie  ──→  /sanctum/*  ──→  GET /sanctum/csrf-cookie    │
│     ←────────────────────  ←──────────────  ←──  XSRF-TOKEN cookie         │
│                                                                              │
│  2. POST /api/login  ──────→  /api/login  ──→  POST /api/login              │
│     + X-XSRF-TOKEN                        + Validate credentials            │
│                                         ←──  laravel_session (HttpOnly)    │
│                                                                              │
│  3. Peticiones subsiguientes incluyen laravel_session automáticamente      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Endpoints de Autenticación

```bash
# CSRF Cookie (obligatorio antes de cualquier POST)
GET /sanctum/csrf-cookie

# Login
POST /api/login
Content-Type: application/json
{
    "email": "admin@admin.com",
    "password": "123456"
}

# Logout
POST /api/logout

# Obtener usuario autenticado
GET /api/getme

# Registro
POST /api/register
```

### Configuración de Seguridad

```php
// config/cors.php
'supports_credentials' => true,
'allowed_origins' => [
    'http://localhost:3000',
    'http://ocobo.test:3000',
    'http://ocobo.test',
],

// config/sanctum.php
'stateful' => explode(',', 'localhost,localhost:3000,ocobo.test,ocobo.test:3000'),
```

### Cumplimiento ISO 27001

| Control | Implementación |
|---------|----------------|
| **A.9.4.1** - Información de identificación | Session Fixation prevention (`session()->regenerate()`) |
| **A.9.4.2** - Gestión de derechos de acceso | Principio de privilegio mínimo en UserResource |
| **A.12.4.1** - Registro de eventos | `UsersAuthenticationLog` con IP y User-Agent |
| **A.12.4.2** - Protección de información de logs | Solo lectura para usuarios autorizados |

---

## 🏗️ Arquitectura del Proyecto

### Estructura de Directorios

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/              # Controladores de autenticación
│   │   ├── Configuracion/     # Módulos de configuración
│   │   ├── ControlAcceso/     # Gestión de usuarios y permisos
│   │   ├── Calidad/           # Gestión de calidad
│   │   ├── ClasificacionDocumental/
│   │   ├── Gestion/
│   │   └── VentanillaUnica/
│   ├── Middleware/
│   │   └── VerifySession.php # Middleware de verificación (deshabilitado temporalmente)
│   ├── Requests/             # Form Request validations
│   └── Resources/            # API Resources
├── Models/
├── Services/                 # Lógica de negocio
│   └── Configuracion/
│       └── BusinessDaysService.php  # Servicio de días hábiles con caché
├── Helpers/
│   └── CalendarioHelper.php  # Helper para cálculo de vencimientos
├── Traits/
│   └── Loggable.php          # Trait para logging de actividad
├── Listeners/
│   ├── StoreUserSession.php  # Listener de login
│   └── StoreUserLogout.php    # Listener de logout
└── Providers/
    └── RouteServiceProvider.php

database/
├── migrations/
│   ├── 2026_04_11_000000_enrich_users_sessions_table.php
│   ├── 2026_04_11_000001_create_users_activity_logs_table.php
│   └── 2026_04_11_000002_create_users_authentication_logs_table.php
└── seeders/

routes/
├── api.php                   # Rutas principales de autenticación
├── configuracion.php         # Rutas de configuración
├── controlAcceso.php         # Rutas de control de acceso
├── calidad.php               # Rutas de calidad
├── clasifica_documental.php  # Rutas de clasificación documental
├── ventanilla-recibida.php   # Rutas de radicación recibida
├── ventanilla-enviada.php    # Rutas de radicación enviada
└── ventanilla-interno.php    # Rutas de radicación interna
```

### Modelos de Auditoría

- **UsersSession**: Control de sesiones de usuarios (dispositivo, navegador, OS, IP)
- **UsersAuthenticationLog**: Logs de autenticación (login exitoso/fallido, logout)
- **UsersActivityLog**: Logs de actividad (crear, actualizar, eliminar)

---

## 📦 Instalación

### Requisitos Previos
- PHP 8.4+
- Composer
- MySQL/MariaDB
- Node.js y NPM

### Pasos de Instalación

1. **Clonar e instalar**
```bash
git clone [url-del-repositorio]
cd ocobo-back
composer install
```

2. **Configurar variables de entorno**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Ejecutar migraciones**
```bash
php artisan migrate
php artisan db:seed
```

4. **Configurar Sanctum**
```bash
# Configurar dominios en .env
SANCTUM_STATEFUL_DOMAINS=localhost:3000,ocobo.test:3000,ocobo.test

# Limpiar caché
php artisan config:clear
php artisan cache:clear
```

---

## 📚 Documentación de la API

### Respuestas Estándar

```json
// Éxito
{
    "status": true,
    "message": "Mensaje descriptivo",
    "data": { ... }
}

// Error
{
    "status": false,
    "message": "Mensaje de error",
    "error": "Código de error"
}
```

### Códigos de Estado HTTP

- `200` - OK
- `201` - Created
- `401` - Unauthorized (no autenticado)
- `403` - Forbidden (sin permisos)
- `422` - Validation Error
- `500` - Server Error

---

## 🛠️ Módulos del Sistema

### Control de Acceso
- Gestión de usuarios (CRUD completo)
- Roles y permisos (Spatie)
- Asignación de cargos
- Asignación de sedes
- Control de sesiones

### Configuración
- División Política (Países, Departamentos, Municipios)
- Sedes y ventanillas
- Listas maestras
- Configuraciones varias
- Numeración de radicados

### Calidad
- Organigramas
- Estructura organizacional

### Clasificación Documental
- TRD (Tabla de Retención Documental)
- Series, Subseries, Tipos documentales
- Versiones TRD
- **Días de vencimiento configurables por elemento TRD** (herencia jerárquica)
- Integración con CalendarioHelper para cálculo de vencimientos

### Ventanilla Única
- Radicación recibida
- Radicación enviada
- Radicación interna
- Gestión de archivos
- Asignación de responsables

### Calendario Días No Hábiles (ISO 27001)
- Gestión de días festivos y no hábiles
- Cálculo automático de vencimientos
- Service `BusinessDaysService` con caché
- Festivos de Colombia pre-configurados
- Integración con `ConfigVarias` para configuración de días hábiles
- Trait `Loggable` para auditoría ISO 27001

### Semáforo de Vencimientos
- Configuración de umbrales (verde, amarillo, rojo)
- Cálculo de días hábiles restantes
- Integración con `CalendarioHelper`

---

## 🛡️ Seguridad

### Medidas Implementadas

1. **Cookies HttpOnly**: No exposición de tokens al JavaScript
2. **CSRF Protection**: Token automático con Sanctum
3. **Session Fixation Prevention**: Regeneración de sesión en login
4. **Rate Limiting**: 60 requests/minuto
5. **CORS Configurado**: Orígenes permitidos explícitos
6. **Logs de Auditoría**: Registro de eventos de seguridad
7. **Principio de Privilegio Mínimo**: Datos mínimos en respuestas

---

## 📋 Changelog

### v2.2 (Abril 2026)
- ✅ Calendario de Días No Hábiles con FullCalendar
- ✅ BusinessDaysService con caché para cálculo de días hábiles
- ✅ Generación automática de festivos de Colombia (Ley Emiliani)
- ✅ Trait Loggable en ConfigCalendarioFestivo para auditoría ISO 27001
- ✅ Configuraciones de semáforo (verde, amarillo, rojo)
- ✅ Helper CalendarioHelper integrado con ConfigVarias
- ✅ Endpoint batch para actualizar múltiples configuraciones
- ✅ Refactorización ConfigVariasController

### v2.1 (Abril 2026)
- ✅ Sistema de autenticación con cookies HttpOnly (BFF Pattern)
- ✅ Laravel Sanctum configurado para SPA
- ✅ Logs de auditoría ISO 27001 (UsersAuthenticationLog)
- ✅ Control de sesiones con dispositivo/navegador/OS
- ✅ Middleware VerifySession (deshabilitado temporalmente)
- ✅ Rate limiting en rutas públicas de autenticación

---

## ⚙️ Configuración de Desarrollo

### Dominios Configurados

```env
# Frontend
http://localhost:3000
http://ocobo.test:3000

# Backend
http://ocobo.test (puerto 80)
```

### Hosts (Windows)
```
127.0.0.1    ocobo.test
127.0.0.1    ocobo-back.test
```