# OCOBO-BACK

Aplicación gestora del proceso de gestión documental desarrollada en Laravel.

![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
![Version](https://img.shields.io/badge/Version-2.7-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Producci%C3%B3n-green?style=flat-square)

**Versión**: 2.8  
**Última actualización**: Octubre 2026  
**Estado**: Producción — Auditoría integral 2026-10 completada en sus niveles críticos: **100% de hallazgos P0 (bloqueantes), P1 (altos) y P2 (medios-altos) completamente resueltos y verificados** (157 hallazgos corregidos, suite automatizada con 310 pruebas en verde; ver `ocobo-documents/AUDITORIA-INTEGRAL-OCOBO-2026-10.md`).

## 📋 Descripción

OCOBO-BACK es una aplicación web desarrollada en Laravel que gestiona procesos documentales de manera eficiente y organizada. El sistema proporciona una API RESTful robusta para la gestión de usuarios, roles, permisos, configuración del sistema, gestión documental, clasificación documental y control de calidad.

## 🚀 Características Principales

- **Autenticación**: Sanctum SPA con cookie HttpOnly, CSRF, 2FA TOTP, bloqueo de cuenta (5 intentos → 15 min), sesión máxima de 12 h, expulsión inmediata de usuarios desactivados o sesiones revocadas y aviso de sesiones simultáneas
- **Autorización**: RBAC con Spatie Permission (permiso por módulo y acción en cada ruta con principio de menor privilegio) + ABAC jerárquico por dependencia (`AbacHierarquico`, middleware `acceso_radicado`, que responde 404 a registros ajenos)
- **Control de Acceso**: Usuarios, roles, permisos directos ("otros permisos"), cargos y sedes; optimización N+1 en árbol de cargos disponibles
- **Configuración**: División política, sedes, listas maestras, numeración de radicados, semáforo, SMTP/IMAP (credencial cifrada)
- **Clasificación Documental**: TRD versionada (TEMP → ACTIVO → HISTÓRICO) con aprobación del Jefe de Archivo, segregación (quien elabora no aprueba) e inmutabilidad de versiones aprobadas; importación desde Excel; TVD con aprobación por dependencia
- **Ventanilla Única**: Radicación recibida, enviada, interna y PQRS con consecutivo atómico (`RadicadoConsecutivoService`), líneas de tiempo, acuses digitales, anulación con aprobación, relación enviados↔recibidos; `created_at` es la fecha oficial de radicación; los internos guardan `dependencia_origen_id` (dependencia del custodio principal, mostrada como «Dependencia / Oficina»); **inmutabilidad y conservación física en Recibidos (`SoftDeletes`)** bajo Ley 594/2000 y Acuerdo AGN 060/2001
- **Gestión de Terceros y Habeas Data**: Cumplimiento de la Ley 1581/2012 y Decreto 1377/2013 con consentimiento explícito de tratamiento de datos (`autoriza_tratamiento_datos`, `fecha_autorizacion_datos`), validación estricta de unicidad de correo y teléfono, cálculo automático del Dígito de Verificación DIAN (Módulo 11) para NIT de personas jurídicas, y **Blind Index HMAC SHA-256 (`num_docu_nit_hash`)** para búsquedas exactas sin exposición de datos en claro
- **PQRS**: Términos de la Ley 1755 por tipo en días hábiles, prioridad Tutela, prórroga única (no aplica a tutelas ni fallos judiciales), notificación por modo, reserva de identidad en denuncias y PQRS anónimas (`es_anonimo` + permiso `Radicar -> PQRSF -> Ver identidad reservada`)
- **Firma Electrónica**: OTP ligado al firmante designado y al documento (hash, 5 min, 5 intentos, un solo uso); sello de texto en el PDF con FPDI, hash SHA-256 antes/después, estampa TSA RFC 3161 sobre canal seguro HTTPS nativo (`https://timestamp.digicert.com`) con verificación criptográfica (PKIStatus, firma CMS, cadena hasta raíz con EKU timeStamping, messageImprint y nonce); DOCX con estampa en grupos colaborativos; firma de PQRS atómica con hash del contenido; firmantes de internos marcados al firmar (sin doble firma)
- **Bandeja de Correo**: Lectura del buzón IMAP en vivo, radicación manual de un correo como recibido/enviado, PDF del correo, rótulo PNG con código de barras y respuesta al remitente con el rótulo
- **Gestión de Archivo**: Expedientes con foliado automático, índice electrónico con hash por folio, cierre sellado (`hash_indice`), préstamos, transferencias y eliminaciones con precondiciones TRD y aprobador distinto (AGN 003/2015)
- **Mi Bandeja**: Grupos colaborativos (proyector, revisor, aprobador, firmante), plantillas DOCX con variables, check-in/check-out, notas en tiempo real, Mi Disco; listado de internos con dependencia de origen y `puede_firmar` por fila
- **Workflows**: Diseñador visual, instancias, tareas, checklists, tablero Kanban y reportes
- **Uploads seguros**: `InspectorArchivos` (contenido real, extensiones y MIME peligrosos, zip-bomb) vía regla `ArchivoSeguro`, también en adjuntos IMAP; nombres de adjunto sin path traversal
- **OCR**: Microservicio PaddleOCR 3 en entorno virtual propio (`start.bat` lo instala), autenticado por token y solo en loopback, con fallback a Tesseract; PDF con texto embebido y OCR solo en páginas escaneadas; búsqueda por contenido
- **Notificaciones en Tiempo Real (Reverb)**: WebSockets mediante Laravel Reverb con actualización instantánea de la campana de notificaciones en toda la entidad y polling de respaldo cada 2 min; correos a responsables y terceros, acuse de recibo automático, alertas de vencimiento (diario) y de retención TRD (30/7/1 días)
- **Calendario Días No Hábiles**: 18 festivos de Colombia por año (fijos, Ley Emiliani y Semana Santa), todo en hora de Colombia
- **Auditoría ISO 27001**: Canal `audit` con retención de 365 días, logs de autenticación con IP y user agent, `VentanillaAuditTrait` por documento
- **Exportaciones**: Excel/CSV/PDF en servidor protegidas contra inyección de fórmulas (`ExportSeguroHelper`)
- **Respuestas API homogéneas**: `ApiResponseTrait` con métodos semánticos (ver tabla en Documentación de la API)

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
    "email": "usuario@entidad.gov.co",
    "password": "********"
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
│   │   ├── Calidad/           # Organigrama
│   │   ├── ClasificacionDocumental/
│   │   ├── Gestion/
│   │   └── VentanillaUnica/
│   ├── Middleware/
│   │   ├── Authenticate.php   # Sesión: usuario activo, sesión no revocada, vida máxima 12 h
│   │   └── AccesoRadicado.php # ABAC por id de radicado (404 si no es accesible)
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
- PHP 8.2+ (Laravel 12)
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

Todos los controladores responden con los métodos semánticos de `app/Http/Traits/ApiResponseTrait.php`; el front asigna el color del toast según el código (`toastFromStatus`).

| Método | Código | Toast | Uso |
|---|---|---|---|
| `successResponse` / `createdResponse` | 200 / 201 | verde | Operación exitosa |
| `badRequestResponse` | 400 | amarillo | Solicitud mal formada |
| `unauthorizedResponse` | 401 | amarillo | No autenticado |
| `forbiddenResponse` | 403 | rojo | Sin permiso |
| `notFoundResponse` | 404 | azul | No existe o no es accesible (ABAC) |
| `conflictResponse` | 409 | amarillo | Conflicto con el estado del recurso |
| `validationResponse` / `warningResponse` | 422 | amarillo | Validación y reglas de negocio |
| `tooManyRequestsResponse` | 429 | amarillo | Límite de peticiones |
| `serverErrorResponse` | 500 | rojo | Error inesperado |

Las violaciones de reglas de negocio (p. ej. aprobar lo que uno mismo elaboró) se responden con `warningResponse` (422), no con 403.

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
- Organigrama (árbol de dependencias, cargos y unidades)
- Validación contra ciclos `parent` y recorridos de jerarquía acotados

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
- Lectura del buzón IMAP en vivo (`directorytree/imapengine`); no hay sincronización programada, la radicación es manual
- Radicado de emails recibidos con los mismos formularios de radicación
- Radicado de emails enviados con conversión PDF
- Generación de rótulo PNG con código de barras y hash SHA-256
- Respuesta al remitente con el rótulo del radicado de ese correo (botón «Responder»)
- Búsqueda de terceros por email
- Adjuntos inspeccionados (`InspectorArchivos`) y registrados en BD; nombre seguro sin path traversal ni sobrescritura
- Marca de correos ya radicados en la bandeja
- Comando `archivo:conciliar-adjuntos-correo` para registrar adjuntos antiguos y restaurarlos de cuarentena

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
- Generación de los 18 festivos de Colombia (fijos, Ley Emiliani y Semana Santa) desde la UI o con `php artisan festivos:sincronizar-colombia`; corrige fechas del generador anterior
- "Hoy" e inicio de términos en hora de Colombia
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
1. El usuario abre la bandeja (lectura IMAP en vivo) y elige «Radicar»
2. Buscar tercero por email en gestion_terceros
3. Si no existe, crear tercero automáticamente
4. Crear radicado (recibido o enviado)
5. Guardar adjuntos en {disk}/{num_radicado}/
6. Convertir email a PDF → guardar en {disk}/{num_radicado}/
7. Generar rótulo PNG con código de barras → guardar en {disk}/{num_radicado}/rotulos/
8. (Opcional) «Responder» al remitente con el rótulo adjunto
```

#### Seguridad ISO 27001
- Hash SHA-256 calculado desde contenido del email
- Almacenamiento centralizado por número de radicado
- Log de operaciones en ventanilla_email_radicados_log
- Permisos: Listar, Crear, Ver, Actualizar, Eliminar por módulo

### OCR - Extracción de Texto (PaddleOCR)
- Extracción automática de texto desde documentos escaneados
- Motor **PaddleOCR** (más preciso para español) con fallback a Tesseract
- Microservicio Python (FastAPI) en `storage/app/ocr-service/`, escucha solo en 127.0.0.1 y exige token compartido (`config/services.php`)
- Instalación: `storage/app/ocr-service/start.bat install` crea `.venv`, instala `requirements.txt` (solo si cambió) y descarga los modelos (~180 MB en `%USERPROFILE%\.paddlex`). En Linux: `python3 -m venv .venv && .venv/bin/pip install -r requirements.txt`
- Gestionado con comando Artisan: `php artisan ocr {start|stop|status}` (usa el Python de `.venv`, log en `storage/logs/ocr-service.log`, espera hasta 180 s el primer arranque)
- PaddleOCR 3 (`predict()`), con oneDNN desactivado por defecto (`OCR_ENABLE_MKLDNN=1` lo activa)
- Soporte para PDF, PNG, JPG, TIFF, BMP; en PDF se usa el texto embebido y las páginas sin texto se renderizan a 200 dpi para OCR (PyMuPDF)
- Extracción de datos estructurados (implementada en el servicio, aún no expuesta por ningún endpoint):
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

1. **Cookies HttpOnly** (SameSite Lax, `secure` en HTTPS) y **CSRF** con Sanctum SPA
2. **Session fixation**: regeneración de sesión en login
3. **2FA TOTP**, **bloqueo de cuenta** (5 intentos fallidos → 15 min) y aviso de **sesiones simultáneas**
4. **Sesión**: inactividad de 480 min, vida absoluta de 12 h; usuario desactivado o sesión revocada → 401 (SES-001/002)
5. **RBAC** (`can:` en las rutas) + **ABAC jerárquico** en listados y rutas por id de Recibidas, Enviadas, Internas, PQRS y Expedientes
6. **Rate limiting** (`AppServiceProvider`): login 5/min, firma 5/min, OTP de firma 20/min, uploads 10/min; API general 120/min (`RouteServiceProvider`)
7. **Uploads**: `InspectorArchivos` + regla `ArchivoSeguro` (contenido real, MIME/extensiones peligrosos, zip-bomb)
8. **Firma**: OTP hasheado, ligado a firmante + documento, 5 intentos, un solo uso; enmascarado (`***` + 3 dígitos) en eventos
9. **Exportaciones** sin inyección de fórmulas (`ExportSeguroHelper`)
10. **Headers**: nosniff, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy; CSP y HSTS en producción (`SecurityHeadersMiddleware`)
11. **Auditoría**: canal `audit` 365 días, `auth` 30 días; contraseñas y tokens nunca se registran
12. **Credencial SMTP cifrada** en `config_varias`

Pendientes conocidos (ver auditoría): los datos personales de terceros **no** están cifrados en BD; la CSP del backend conserva un placeholder de nonce.

---

## 📋 Changelog

### v2.7 (Octubre 2026) — Remediación de la auditoría integral

Rama `fix/auditoria-p1`. Detalle y estado de cada hallazgo en `ocobo-documents/AUDITORIA-INTEGRAL-OCOBO-2026-10.md`.

- ✅ **Autorización**: `can:` en usuarios, cargos, configuración y terceros (RBAC-001, M14-001, M15-01/02, M16-01/02, M17-01); ABAC en Recibidas, Enviadas, Internas, PQRS, Expedientes y búsqueda OCR (M01-01/05, M02-01/02/03, M03-03, M04-01/02, M08-03/04/05, M12-01)
- ✅ **Sesiones**: usuarios desactivados y sesiones revocadas pierden la cookie (SES-001/002)
- ✅ **Firma**: OTP ligado al firmante designado y al documento, límites de peticiones (M06-02/03/04/06/07); firma de PQRS atómica con `hash_original` (M04-04); verificación criptográfica del sello TSA y OID SHA-256 corregido en la solicitud (M06-01); eliminado el flujo legado `/api/transversal/firma-electronica` que no estampaba (M06-11); firmante de internos marcado y sin doble firma (M03-19)
- ✅ **Uploads**: `InspectorArchivos` en Recibidas, Enviadas, Internas, PQRS, grupos y adjuntos IMAP; nombre de adjunto seguro (M01-04, M02-04, M03-10, M05-05/06)
- ✅ **Correo**: respuesta solo con el rótulo del propio radicado; adjuntos registrados en BD + comando de conciliación (M05-01, M08-01)
- ✅ **Archivo**: folios y números de expediente nunca se reutilizan; expedientes cerrados bloqueados; precondiciones TRD en eliminación y transferencia (M08-07/08, M08-10/11)
- ✅ **TRD/TVD**: inmutabilidad de versiones aprobadas, segregación elaborador/aprobador, aprobación de TVD por dependencia (M10-01/02, M11-03)
- ✅ **PQRS**: reserva de identidad en denuncias y PQRS anónimas, migración `es_anonimo` (M04-03); prórroga atómica, única y bloqueada en tutelas y fallos judiciales (M04-05)
- ✅ **Radicados**: `created_at` como fecha oficial (se elimina `ventanilla_radica_reci.fec_radicado`); historial 360 de terceros sin 500
- ✅ **Internos / Mi Bandeja**: dependencia de origen guardada al radicar (`dependencia_origen_id`) y expuesta en Mi Bandeja; `puede_firmar` por radicado
- ✅ **Calendario**: 18 festivos con Ley Emiliani, scheduler y vencimientos en hora de Colombia (M13-01/02/03/04)
- ✅ **Integridad**: `num_radicado` y autoría no editables desde el cliente (M01-02, M02-07); consecutivo de Enviadas con contador bloqueado (M02-08); exportaciones sin inyección de fórmulas (M11-01); organigrama sin ciclos (M17-02)
- ✅ **OCR**: microservicio versionado, autenticado por token y solo en loopback (M12-02); compatible con PaddleOCR 3, OCR de PDF escaneados, entorno virtual con autoinstalación y detección del servicio por conexión al puerto
- ✅ **Bugs**: eliminar respuesta de recibido, `config-num-radicado`, búsqueda de terceros sin ruta (M01-09, M15-03, M16-08)
- ✅ Pruebas feature con `DatabaseTransactions` sobre BD de pruebas separada (144 pasan, 10 omitidas por la BD de pruebas desactualizada)

### v2.6 (Julio 2026) — In-App Notifications en Tiempo Real (Reverb)

- ✅ **M16 In-App Notifications** — Sistema de notificaciones push en tiempo real
- ✅ **Notificacion model** — `notifications` table con user_id, type, title, message, data, read_at
- ✅ **NotificationPushed event** — Broadcast a PrivateChannel('user.{id}') con Laravel Reverb
- ✅ **InAppNotificationController** — 4 endpoints: index, unreadCount, markAsRead, markAllAsRead
- ✅ **InAppNotificationResource** — Serialización con formato ISO para fechas
- ✅ **Rutas API** — `GET|POST|PATCH /api/transversal/in-app-notifications/*`
- ✅ **Integración Reverb** — WebSocket push en tiempo real a cada usuario
- ✅ **Fallo a polling** — El frontend consulta cada 2 min como respaldo
- ✅ **Migración** — `create_notifications_table` con índices y FK

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
- ✅ Middleware VerifySession (luego reemplazado por la verificación en `Authenticate`, v2.7)
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

Después de hacer login, en el navegador deberías ver:
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