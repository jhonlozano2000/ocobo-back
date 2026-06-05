# AGENTS.md — OCOBO Backend

## Stack
- PHP 8.1+, Laravel 12, Sanctum 4.2 (SPA cookies, NO JWT), Spatie Permission v6, Laravel Reverb
- Base de datos: MySQL vía Laragon

## Dev Commands
```bash
composer run pint          # Laravel Pint (PSR-12)
composer run test          # PHPUnit
php artisan test --filter=NombreClase   # Test específico
php artisan route:list     # Ver rutas registradas
composer run cache-clear   # php artisan optimize:clear
```

## Arquitectura por Módulo
```
routes/{modulo}.php → Controllers/{Modulo}/ → Requests/{Modulo}/ → Models/{Modulo}/ → Services/ → Resources/
```

Reglas fijas:
- Rutas específicas SIEMPRE antes de `Route::apiResource()` (si no, Laravel las interpreta como `{id}`)
- Prefijo URL: `api/<modulo>` registrado en `RouteServiceProvider`
- Controladores usan `ApiResponseTrait` (respuesta: `{status, message, data|error}`)
- Form Requests en español con `rules()`, `messages()`, `attributes()`
- Transacciones: `DB::beginTransaction()` + `try/catch` + `DB::rollBack()` en toda operación multi-tabla

## Módulos (routes/*.php)
- `ventanilla-recibida.php` — Radicados recibidos (~30 endpoints con rate limiting diferenciado: api, radicacion, uploads, search)
- `ventanilla-enviada.php` — Radicados enviados
- `ventanilla-interno.php` — Radicados internos
- `ventanilla-pqrs.php` — PQRSD vinculados a radicados recibidos
- `configuracion.php` — Configuración general, listas, servidores
- `controlAcceso.php` — Usuarios, roles, permisos
- `clasifica_documental.php` — TRD (Series, Subseries, Tipos Documentales)

## Flujo Crítico: Radicado Recibido → PQRSD
- `VentanillaRadicaReciController@store()` crea radicado + PQRS en **misma transacción atómica**
- Si `crear_pqrs=true` → `PqrsService::crearDesdeRadicado()` se ejecuta dentro de la transacción
- **Estado:** ✅ HABILITADO - código PQRS activo desde 2026-06-03
- Si falla creación de PQRS → rollback automático del radicado (atomicidad garantizada)
- Relación: `VentanillaRadicaReci hasOne VentanillaPqrs` via `ventanilla_radica_reci_id`
- La PQRS hereda datos del tercero, asunto y clasificación documental del radicado
- Plazos de respuesta según tipo PQRS (Petición 15d, Tutela 2d, etc.)
- **Request validation:** `StoreRadicadoReciboRequest` valida `crear_pqrs`, `tipo_pqrs_id`, `prioridad` cuando `crear_pqrs=true`
- **Response:** incluye `data.radicado` y `data.pqrs` (null si no se creó PQRS)
- **Cache:** se limpian ambos caches `ventanilla_recibidos_estadisticas` y `ventanilla_pqrs_estadisticas`

## ABAC (Attribute-Based Access Control)
- Policies en `app/Policies/` verifican con `$user->hasPermissionTo()`
- Middleware `can:` en rutas (ej: `can:Radicar -> Cores. Recibida -> Crear`)
- Scopes locales en modelos para filtros comunes (`scopeActivo`, `scopeEstadoTrabajo`, `scopeVencidos`)
- **✅ ABAC Jerárquico Automático** implementado desde 2026-06-03:
  - Trait `AbacHierarquico` en `app/Traits/AbacHierarquico.php`
  - Modelos: `VentanillaRadicaReci`, `VentanillaRadicaReciOptimizedView`
  - Scopes: `paraUsuario()`, `paraMiDependencia()`, `paraMisSubordinados()`, `conPermisoJerarquico()`
  - Filtrado automático por jerarquía organizacional (cod_organico)
  - Usuarios ven: sus propios registros + registros de su dependencia + subordinados
  - Permiso especial `Radicar -> Ver Todos` bypassa filtrado ABAC
  - Tests de integración en `tests/Feature/VentanillaUnica/FlujoPqrsAtomicTest.php`

## Convenciones
- Modelos: `$fillable`, relaciones Eloquent explícitas, scopes para filtros
- Archivos: usar `ArchivoHelper` (no manipular `Storage` directamente)
- Hash SHA-256 para integridad documental (campo `hash_sha256`)
- Códigos de verificación: 10 dígitos con `random_int()`
- Notificaciones: `AcuseReciboHelper` + Mails (RadicadoNotification, PqrsNotificacionEmail)
- Eventos en tiempo real: Laravel Reverb para broadcasting
- **Vuexy Badges**: API Resources deben incluir `vuexy_badges` con clases semánticas:
  - Estados PQRS: `badge-light-warning` (Pendiente), `badge-light-info` (En Trámite), `badge-light-success` (Respondida), `badge-light-danger` (Vencida)
  - Prioridades: `badge-light-success` (Normal), `badge-light-warning` (Urgente), `badge-light-danger` (Tutela)
  - Vencimiento: `badge-light-danger` (Crítico ≤2d), `badge-light-warning` (Urgente ≤5d), `badge-light-info` (En término)

## Testing
- PHPUnit v11 (Unit + Feature en `tests/Unit/` y `tests/Feature/`)
- `phpunit.xml`: `APP_ENV=testing`, `CACHE_DRIVER=array`, `QUEUE_CONNECTION=sync`
- DB_CONNECTION comentado — usa BD real (no sqlite)
- Spatie Permission: requiere ejecutar seeds antes de tests de autorización
- Usar `DatabaseTransactions` trait para rollback automático entre tests

## Archivos de Configuración Clave
- `.env` — `APP_URL=http://ocobo.test`, `SANCTUM_STATEFUL_DOMAINS=ocobo.test:3000`, `SESSION_DRIVER=cookie`
- `routes/ventanilla-recibida.php` — rate limits: `api` (60/min), `radicacion` (30/min), `uploads` (10/min), `search` (30/min)
- `.cursor/rules/` — ~20 reglas MDC detalladas (estructura módulo, naming, tests, migraciones, rutas, archivos, modelos)

## Mi Bandeja — Documentos Colaborativos (TempDocumentosRecibidos)

### Estructura
```
routes/mi-bandeja-temp-recibidos.php
├── Models/MiBandeja/TempDocumentosRecibidos/
│   ├── Documento.php          # Modelo principal con config de página, estados, roles
│   ├── Contenido.php          # Contenido Yjs con hash SHA256
│   ├── Version.php            # Control de versiones (máx 50 por doc)
│   ├── Comentario.php         # Comentarios anidados con selección de texto
│   ├── Cursor.php             # Cursores en tiempo real
│   ├── DocumentoUsuario.php   # Relación usuario-rol (firmante, responsable, proyector)
│   └── Sugerencia.php         # Modo revisión/track changes (nuevo)
├── Controllers/MiBandeja/TempDocumentosRecibidos/
│   ├── DocumentoController    # CRUD + sincronización + versiones + config página
│   ├── VersionController      # Listar, mostrar, comparar, restaurar versiones
│   ├── ComentarioController   # CRUD + resolver/desresolver comentarios
│   ├── CursorController       # Obtener/actualizar cursores
│   ├── SugerenciaController   # CRUD + aceptar/rechazar sugerencias (nuevo)
│   ├── DocumentoExportController  # Exportar a PDF, DOCX, HTML, TXT
│   └── DocumentoImportController  # Importar desde DOCX, HTML, TXT
├── Services/MiBandeja/TempDocumentosRecibidos/
│   ├── DocumentoExportService # Exportación con PhpWord + DomPDF
│   ├── DocumentoImportService # Importación con conversión a TipTap/Yjs
│   └── CursorService          # Gestión de cursores
├── Events/MiBandeja/TempReci/
│   ├── ContenidoActualizado   # Broadcast de cambios de contenido
│   ├── UsuarioConectado       # Broadcast de conexión
│   ├── UsuarioDesconectado    # Broadcast de desconexión
│   ├── ComentarioCreado       # Broadcast de nuevo comentario
│   └── CursorActualizado      # Broadcast de movimiento de cursor
└── Resources/MiBandeja/TempReci/
    ├── DocumentoResource      # JSON con estadísticas integradas
    └── (otros resources)
```

### Funcionalidades Implementadas
- **Configuración de página**: tamaños (a4, carta, legal, oficio), orientación, márgenes, columnas, header/footer
- **Estados**: borrador → en_revision → firmado
- **Roles**: firmante, responsable, proyector (con colores diferenciados)
- **Sincronización Yjs**: contenido CRDT con hash SHA256 para detección de cambios
- **Versionado**: automático cada 5 min o cambios >100 chars, máximo 50 versiones por documento
- **Diff entre versiones**: endpoint GET `/documentos/{doc}/versiones/{vA}/comparar/{vB}`
- **Comentarios**: anidados, con selección de texto, resolución/desresolución
- **Sugerencias (modo revisión)**: inserción, eliminación, reemplazo, formato - aceptables/rechazables
- **Exportación**: PDF (DomPDF), DOCX (PhpWord), HTML, TXT
- **Importación**: DOCX, HTML, TXT → conversión automática a formato TipTap/Yjs
- **Cursores en tiempo real**: posición + selección, limpieza automática de inactivos
- **Rate limiting**: `documentos` (120/min), `sincronizacion` (60/min)

### Rutas Clave
```
GET    /comunicaciones-recibidas/documentos                    # Listar
POST   /comunicaciones-recibidas/documentos                    # Crear
GET    /comunicaciones-recibidas/documentos/{doc}              # Mostrar
PUT    /comunicaciones-recibidas/documentos/{doc}              # Actualizar
DELETE /comunicaciones-recibidas/documentos/{doc}              # Eliminar
POST   /comunicaciones-recibidas/documentos/{doc}/sincronizar  # Sync Yjs
PATCH  /comunicaciones-recibidas/documentos/{doc}/configuracion # Config página
POST   /comunicaciones-recibidas/documentos/{doc}/versiones    # Crear versión
GET    /comunicaciones-recibidas/documentos/{doc}/versiones/{vA}/comparar/{vB} # Diff
POST   /comunicaciones-recibidas/documentos/{doc}/versiones/{v}/restaurar # Restaurar
POST   /comunicaciones-recibidas/documentos/{doc}/sugerencias  # Crear sugerencia
POST   /comunicaciones-recibidas/documentos/{doc}/sugerencias/{s}/aceptar # Aceptar
POST   /comunicaciones-recibidas/documentos/{doc}/sugerencias/{s}/rechazar # Rechazar
GET    /comunicaciones-recibidas/documentos/{doc}/exportar/{formato} # Exportar
POST   /comunicaciones-recibidas/documentos/importar           # Importar archivo
```

### Reglas de Negocio
- Solo el creador puede eliminar documentos
- Solo el creador puede asignar usuarios
- Editores (firmante, responsable, proyector) pueden sincronizar contenido
- Versiones se crean automáticamente si: primera versión, >5 min desde última, o cambio >100 chars
- Sugerencias pendientes pueden aplicarse al contenido al ser aceptadas
- Cursores inactivos (>60 seg sin actividad) se limpian automáticamente
- Todas las operaciones multi-tabla usan `DB::transaction()`
