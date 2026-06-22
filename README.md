# OCOBO-BACK

Aplicación gestora del proceso de gestión documental desarrollada en Laravel.

![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat-square&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?style=flat-square&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
![Version](https://img.shields.io/badge/Version-2.5-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Producci%C3%B3n-green?style=flat-square)

**Versión**: 2.5  
**Última actualización**: Junio 2026  
**Estado**: Producción — 18/18 módulos completados (100%)

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
- **Clasificación Documental**: Sistema completo de TRD/TVD con versiones
- **Control de Calidad**: Gestión de organigramas y estructuras organizacionales
- **Ventanilla Única**: Sistema completo de gestión de ventanillas y radicaciones (recibidos, enviados, internos, PQRS)
- **Firma Electrónica**: OTP + PDF stamping (FPDI) + registro de eventos ISO 27001 para recibidos, enviados, internos y PQRS (Ley 527/1999)
- **Anulación de PQRS**: Flujo de anulación con motivo + cambio de estado a Vencida
- **Bandeja de Correo**: Integración IMAP, generación PDF, rótulos PNG, respuesta automática (ISO 27001)
- **Gestión de Archivo**: Expedientes electrónicos, préstamos, transferencias, eliminaciones, reportes, dashboard (AGN 003/2015)
- **Notificaciones y Alertas**: Alertas de vencimiento por email (diario 7am), endpoint de pendientes para badge del frontend (Ley 1437/2011)
- **Depuración Digital**: Comando semanal de cuarentena de archivos huérfanos (AGN 060, ISO 27001 A.8.3)
- **Metadata Extendida**: Write endpoint + sugerencia automática de TRD por palabras clave del asunto
- **Scheduler**: 5 comandos programados (marcar PQRS vencidas, sync email, alertas vencimiento, depuración recibidos/enviados)
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
├── ventanilla-interno.php    # Rutas de radicación interna
├── gestion-archivo.php       # Rutas de gestión de archivo (26 rutas)
├── ventanilla-email-radicados.php  # Rutas de bandeja de correo (9 rutas)
└── ventanilla-email-radicados-api.php  # API pública para radicados desde correo
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

### Bandeja de Correo (Email Radicados)
- Conexión IMAP a buzón de correo electrónico
- Sincronización automática de emails entrantes
- Radicado de emails recibidos con formularios EXACTOS de radicación
- Radicado de emails enviados con conversión PDF
- Generación automática de rótulo PNG con código de barras
- Respuesta automática al remitente con rótulo adjunto
- Búsqueda de terceros por email
- Gestión de adjuntos (descarga, previsualización)
- Estadísticas de bandeja (pendientes, radicados, respondidos, errores)
- Integración ISO 27001 — hash SHA-256 para integridad de documentos

### Mi Bandeja - Documentos Colaborativos

Sistema de gestión de documentos colaborativos para comunicaciones recibidas. Permite crear, editar y colaborar en documentos en tiempo real.

#### Modelo Documento (`App\Models\MiBandeja\TempDocumentosRecibidos\Documento`)

Modelo Eloquent para documentos colaborativos vinculado a comunicaciones recibidas.

**Tabla**: `mi_bandeja_temp_reci_documentos`

**Campos**:
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Primary key |
| `radica_reci_id` | foreignId | Vinculación a radicado (nullable) |
| `user_id` | foreignId | Usuario creador (required) |
| `titulo` | string | Título del documento |
| `estado` | enum | `borrador`, `en_revision`, `firmado` |
| `notas` | text | Notas adicionales (nullable) |
| `es_publico` | boolean | Visibilidad para otros usuarios |
| `created_at` | timestamp | Fecha de creación |
| `updated_at` | timestamp | Última modificación |

**Relaciones**:
- `creador()` → `BelongsTo(User)` - Usuario que creó el documento
- `radicado()` → `BelongsTo(VentanillaRadicaReci)` - Radicado asociado
- `usuarios()` → `HasMany(DocumentoUsuario)` - Usuarios asignados
- `contenido()` → `HasOne(Contenido)` - Contenido Yjs del editor
- `versiones()` → `HasMany(Version)` - Historial de versiones
- `comentarios()` → `HasMany(Comentario)` - Comentarios
- `cursores()` → `HasMany(Cursor)` - Cursores colaborativos en tiempo real

**Métodos**:
- `tieneAcceso(User)` - Verifica si un usuario tiene acceso
- `puedeEditar(User)` - Verifica si un usuario puede editar
- `puedeFirmar(User)` - Verifica si un usuario puede firmar

#### Controlador DocumentoController

`App\Http\Controllers\MiBandeja\TempDocumentosRecibidos\DocumentoController`

**Endpoints**:

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/mi-bandeja/comunicaciones-recibidas/documentos` | Lista de documentos del usuario |
| POST | `/api/mi-bandeja/comunicaciones-recibidas/documentos` | Crear nuevo documento |
| GET | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}` | Ver documento |
| PUT | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}` | Actualizar documento |
| DELETE | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}` | Eliminar (solo creador) |
| POST | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}/sincronizar` | Sincronizar contenido Yjs |
| GET | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}/contenido` | Obtener contenido |
| POST | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}/versiones` | Crear versión |
| GET | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}/versiones` | Listar versiones |
| POST | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}/versiones/{versionId}/restaurar` | Restaurar versión |
| POST | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}/usuarios` | Asignar usuarios |
| GET | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}/cursores` | Obtener cursores activos |
| POST | `/api/mi-bandeja/comunicaciones-recibidas/documentos/{id}/comentarios` | Agregar comentario |

**Resource**: `App\Http\Resources\MiBandeja\TempReci\DocumentoResource`

Transforma el modelo a JSON incluyendo:
- `id`, `titulo`, `estado`, `notas`, `es_publico`
- `creador` (whenLoaded) → `{ id, name }`
- `usuarios` (whenLoaded) → `{ user_id, rol, nombre, color }`
- `contenido` (whenLoaded) → `{ contenido_yjs, hash, actualizado_por }`
- `cursores` (whenLoaded) → posiciones de usuarios en tiempo real

**Nota**: El campo `creador.name` se construye desde `nombres + apellidos` del modelo User.

#### Modelo Contenido (`App\Models\MiBandeja\TempDocumentosRecibidos\Contenido`)

Almacena el contenido Yjs del editor colaborativo.

**Tabla**: `mi_bandeja_temp_reci_contenidos`

**Campos**:
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | Primary key |
| `documento_id` | foreignId | Documento asociado |
| `contenido_yjs` | json | Contenido del editor Yjs |
| `hash_contenido` | string | Hash SHA256 del contenido |
| `actualizado_por` | foreignId | Usuario que actualizó |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Método**: `actualizarContenido($contenido, $usuario)` - Actualiza contenido y hash

#### Modelo Version (`App\Models\MiBandeja\TempDocumentosRecibidos\Version`)

Control de versiones para restaurar contenido anterior.

**Tabla**: `mi_bandeja_temp_reci_versiones`

**Método**: `crearVersion($documento, $contenido, $usuario, $descripcion)` - Crea snapshot

#### Políticas de Acceso

- **Ver documento**: Usuario asignado o documento público
- **Editar documento**: Creador o usuario con rol (firmante, responsable, proyector)
- **Eliminar documento**: Solo el creador
- **Asignar usuarios**: Solo el creador

#### Eventos Broadcasting

- `ContenidoActualizado` - Cuando se sincroniza contenido
- `UsuarioConectado` - Cuando un usuario entra al documento
- `UsuarioDesconectado` - Cuando un usuario sale del documento

#### Rutas del Módulo

Archivo: `routes/mi-bandeja-temp-recibidos.php`

```php
Route::middleware('auth:sanctum')->prefix('comunicaciones-recibidas')->group(function () {
    // CRUD básico
    Route::get('/documentos', [DocumentoController::class, 'index']);
    Route::post('/documentos', [DocumentoController::class, 'store']);

    // Verificación de permisos
    Route::middleware('can:ver,documento')->group(function () {
        Route::get('/documentos/{documento}', [DocumentoController::class, 'show']);
        // ... endpoints de solo lectura
    });

    // Edición (requiere rol)
    Route::middleware('can:editar,documento')->group(function () {
        Route::put('/documentos/{documento}', [DocumentoController::class, 'update']);
        Route::post('/documentos/{documento}/sincronizar', [DocumentoController::class, 'sincronizar']);
        // ... endpoints de edición
    });

    // Gestión de usuarios (solo creador)
    Route::middleware('can:gestionarUsuarios,documento')->group(function () {
        Route::post('/documentos/{documento}/usuarios', [DocumentoController::class, 'asignarUsuarios']);
    });

    // Eliminación (solo creador)
    Route::middleware('can:eliminar,documento')->group(function () {
        Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy']);
    });
});
```

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

### Bandeja de Correo - Arquitectura Backend

#### Servicios
- **ImapEmailService** — Conexión IMAP, descarga de emails, conversión HTML→texto plano
- **EmailRadicacionService** — Orquestación: sync → buscar tercero → radicar → adjuntos → PDF → rótulo → respuesta automática
- **RadicacionReciService** — CRUD de radicados recibidos (reutilizado por email)
- **RadicacionEnviadaService** — CRUD de radicados enviados (reutilizado por email)
- **PdfService** — Generación de PDF con dompdf, HTML→texto plano para contenido de email
- **RotuloPngService** — Generación de rótulo PNG con código de barras (Intervention Image + Picqer)
- **ArchivoHelper** — Almacenamiento centralizado en `{disk}/{num_radicado}/`

#### Controladores
- **EmailRadicadosController** — CRUD de tracking de emails, sync IMAP, búsqueda, estadísticas
- **VentanillaRadicaReciController** — CRUD de radicados recibidos (reutilizado por email)
- **VentanillaRadicaEnviadaController** — CRUD de radicados enviados (reutilizado por email)
- **TerceroSearchController** — Búsqueda de terceros por email o número de documento

#### Mailable
- **RespuestaRadicadoMail** — Email automático con rótulo PNG adjunto enviado al remitente

#### Validación
- **RadicarEmailRequest** — Validación centralizada para radicado de emails (recibido/enviado)

#### Migraciones
- **ventanilla_email_radicados** — Tracking de emails (id, num_expediente, asunto, remitente, destinatario, estado, radicado_id, radicado_type, fecha_radicacion, error_mensaje)
- **ventanilla_email_radicados_adjuntos** — Adjuntos de emails
- **ventanilla_email_radicados_log** — Log de operaciones
- **drop_radicado_id_foreign** — FK constraint eliminada (puede apuntar a recibidos O enviados)

#### Flujo de Radicado desde Email
```
1. Sync IMAP → Descargar emails nuevos
2. Buscar tercero por email en gestion_terceros
3. Si no existe, crear tercero automáticamente
4. Crear radicado (recibido o enviado)
5. Guardar adjuntos en {disk}/{num_radicado}/
6. Convertir email a PDF → guardar en {disk}/{num_radicado}/
7. Generar rótulo PNG con código de barras → guardar en {disk}/{num_radicado}/rotulos/
8. Respuesta automática al remitente con rótulo adjunto
```

#### Seguridad ISO 27001
- Hash SHA-256 calculado desde contenido del email
- Almacenamiento centralizado por número de radicado
- Log de operaciones en ventanilla_email_radicados_log
- Permisos: Listar, Crear, Ver, Actualizar, Eliminar por módulo

### OCR - Extracción de Texto (PaddleOCR)
- Extracción automática de texto desde documentos escaneados
- Motor **PaddleOCR** (más preciso para español) con fallback a Tesseract
- Microservicio Python en `storage/app/ocr-service/`
- Gestionado con comando Artisan: `php artisan ocr {start|stop|status}`
- Soporte para PDF, PNG, JPG, TIFF, BMP
- Extracción de datos estructurados:
  - Números de identificación (CC, NIT, RC, CE, Pasaporte, TI)
  - Fechas (DD/MM/YYYY, YYYY-MM-DD, Mes DD, YYYY)
  - Correos electrónicos
  - Teléfonos (fijos y celulares Colombia)
  - Direcciones físicas
  - Códigos (facturas, contratos, guías, órdenes, radicados)
- Integración automática al subir archivo digital
- Endpoint para re-aplicar OCR manualmente
- `OcrHttpService` para PaddleOCR, `OcrService` para Tesseract (fallback)

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

### v2.5 (Junio 2026) — Firma Electrónica + Notificaciones + Mejoras
- ✅ **M13 Firma Electrónica** — OTP + PDF stamping (FPDI) + FirmaEvento (ISO 27001) para recibidos, enviados, internos y PQRS (Ley 527/1999)
- ✅ **AnularPqrs** — Endpoint con motivo, estado_tramite → Vencida
- ✅ **Migración estado_firma/fecha_firma** en ventanilla_radica_reci, _enviados, _internos
- ✅ **FirmaEventosController** — `GET /transversal/firma-eventos/{tipo}/{id}` historial de firmas
- ✅ **MetadataController::store()** — PUT write endpoint para metadata extendida
- ✅ **MetadataController::sugerirClasificacion()** — sugerencia TRD por palabras clave del asunto
- ✅ **M16 Notificaciones** — `AlertasVencimientoCommand` (diario 7am, recibidos+enviados+PQRS), endpoint `/transversal/notificaciones/pendientes`
- ✅ **M17 Depuración** — `DepuracionDigitalCommand` (semanal domingos, cuarentena archivos huérfanos)
- ✅ **Scheduler completo** — 5 comandos programados en routes/console.php + bootstrap/app.php
- ✅ **Bootstrap fix** — commands: registrado en withRouting() para activar scheduler
- ✅ **FirmaElectronicaController** reparado — usa FirmaService para email OTP
- ✅ **Permisos firma** creados para Cores. Recibida / Enviada / Interna / PQRS
- ✅ **40+ rutas backend** (ventanilla + firma + notificaciones + metadata)
- ✅ **511 archivos PHP — 0 fallos Pint**

### v2.4 (Junio 2026)
- ✅ Bandeja de Correo — Conexión IMAP, sync automática de emails
- ✅ EmailRadicacionService — Orquestación completa de radicado desde email
- ✅ RadicarEmailRequest — Validación centralizada para radicado de emails
- ✅ EmailRadicadosController — CRUD de tracking de emails con permisos
- ✅ TerceroSearchController — Búsqueda de terceros por email
- ✅ RotuloPngService — Generación de rótulo PNG con código de barras
- ✅ PdfService — Conversión de emails a PDF con dompdf
- ✅ RespuestaRadicadoMail — Email automático con rótulo adjunto
- ✅ Gestión de Archivo — Expedientes electrónicos, préstamos, transferencias
- ✅ M11-M15 — Préstamo, Transferencia/Eliminación, Reportes, Dashboard
- ✅ 35 rutas backend (26 gestión-archivo + 9 email-radicados)
- ✅ Race condition fix — DB::transaction + lockForUpdate() en obtención de número
- ✅ Almacenamiento centralizado en {disk}/{num_radicado}/
- ✅ FK constraint eliminada en radicado_id (puede apuntar a recibidos O enviados)

### v2.3 (Mayo 2026)
- ✅ Módulo Mi Bandeja - Documentos Colaborativos
- ✅ Modelo Documento con relaciones (creador, contenido, versiones, cursores)
- ✅ Controlador DocumentoController con CRUD completo
- ✅ DocumentoResource con transformación de datos
- ✅ Modelo Contenido para almacenar contenido Yjs
- ✅ Modelo Version para control de versiones
- ✅ Modelo Cursor para cursores colaborativos en tiempo real
- ✅ Modelo DocumentoUsuario para asignación de roles
- ✅ Políticas de acceso (ver, editar, eliminar, gestionar)
- ✅ Endpoints de sincronización Yjs con hash SHA256
- ✅ Broadcast events para colaboración en tiempo real
- ✅ Importación y exportación de documentos (PDF, DOCX, HTML, TXT)

### v2.2 (Abril 2026)
- ✅ Calendario de Días No Hábiles con FullCalendar
- ✅ BusinessDaysService con caché para cálculo de días hábiles
- ✅ Generación automática de festivos de Colombia (Ley Emiliani)
- ✅ Trait Loggable en ConfigCalendarioFestivo para auditoría ISO 27001
- ✅ Configuraciones de semáforo (verde, amarillo, rojo)
- ✅ Helper CalendarioHelper integrado con ConfigVarias
- ✅ Endpoint batch para actualizar múltiples configuraciones
- ✅ Refactorización ConfigVariasController
- ✅ OCR con PaddleOCR (microservicio Python) + fallback Tesseract
- ✅ Campos `ocr` y `ocr_aplicado` en radicados
- ✅ `OcrHttpService` + `OcrService` con extracción de datos estructurados

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
# Frontend (Next.js debe iniciar con -H ocobo.test)
http://ocobo.test:3000

# Backend
http://ocobo.test (puerto 80)
```

### Importante: Mismo Dominio para Cookies HttpOnly

Para que el login funcione correctamente con **Laravel Sanctum en modo SPA** (cookies HttpOnly), el frontend y backend **DEBEN** estar en el mismo dominio.

**Cuando inicies Next.js, usa:**
```powershell
cd C:\Users\jhonl\Desktop\ocobo-frond
npm run dev -- -H ocobo.test
```

Esto hace que Next.js escuche en `http://ocobo.test:3000` en lugar de `localhost:3000`.

### Configuración de Sesión en .env

```env
SESSION_DRIVER=file
SESSION_LIFETIME=480
SESSION_DOMAIN=ocobo.test
SESSION_SAME_SITE=null
SESSION_SECURE_COOKIE=false
```

| Variable | Valor | Propósito |
|----------|-------|-----------|
| SESSION_DOMAIN | ocobo.test | Cookie válida para el dominio |
| SESSION_SAME_SITE | null | Permite cookies cross-origin dentro del mismo dominio |

### Hosts (Windows)
```
127.0.0.1    ocobo.test
127.0.0.1    ocobo-back.test
```

### Verificación de Cookies

Después de hacer login,，你应该在浏览器中看到：
- `ocobo_session` - Cookie de sesión (HttpOnly, dominio=ocobo.test)
- `XSRF-TOKEN` - Token CSRF (legible por JS, usado como header)

### Solución de Problemas de Login

**Síntoma**: Login funciona pero no se crean cookies
- Causa: Frontend y backend en dominios diferentes
- Solución: Asegúrate de iniciar Next.js con `-H ocobo.test` y acceder desde `http://ocobo.test:3000`

**Síntoma**: Página en blanco al acceder a ocobo.test:3000
- Causa: Next.js no está escuchando en ese host
- Solución: `npm run dev -- -H ocobo.test`

### Inicio Rápido

```powershell
# 1. Backend (Laravel)
cd C:\laragon\www\ocobo-back
php artisan config:clear

# 2. Frontend (Next.js) - IMPORTANTE: usar -H ocobo.test
cd C:\Users\jhonl\Desktop\ocobo-frond
npm run dev -- -H ocobo.test

# 3. Acceder desde
http://ocobo.test:3000/es/login
```