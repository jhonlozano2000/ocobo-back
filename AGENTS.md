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

## Flujo de Pase y Transferencia de Custodio (Recibidos e Internos)
- **Servicios Atómicos:** `PaseHistorialService` y `PaseHistorialInternoService`.
- **Regla de Negocio (Opción 1):**
  - Cada radicado posee funcionarios con un solo cargo activo asignado (`users_cargos`).
  - Al realizar un **Pase**, el funcionario que transfiere se desvincula de los responsables activos (`ventanilla_radica_reci_responsa` / `ventanilla_radica_interno_responsa`).
  - Si el funcionario que realiza el pase es el **custodio** (`custodio = 1`), la condición de custodia se hereda y transfiere automáticamente al nuevo responsable asignado (`custodio = 1`). Si no es custodio, se asigna con `custodio = 0`.
  - Se registra el evento en la tabla histórica (`ventanilla_radica_reci_pase_historial` / `ventanilla_radica_interno_pase_historial`).
- **Validaciones Preventivas:**
  - Endpoint rechaza con `HTTP 422` si se intenta transferir a uno mismo o a un usuario que ya es responsable activo.
  - El frontend excluye dinámicamente mediante `excludeIds` al usuario logueado y a los responsables actuales.

## Correspondencia Interna (VentanillaRadicaInterno)
- **Autorización Dual y Contextual de Consulta (Ventanilla Única vs Mi Bandeja):**
  - Los endpoints de consulta y detalle (`show`, `lineaTiempo`, sub-recursos de `responsables`, `destinatarios`, `proyectores`, `firmantes`, `respuestas`, archivos `digital` y `adjuntos`) admiten acceso tanto para usuarios con permiso de Ventanilla (`Radicar -> Cores. Interna -> Mostrar`) como para usuarios de bandeja personal (`Mi Bandeja - Internos -> Ver`).
  - Cuando un usuario no posee el rol/permiso de ventanilla pero sí el de bandeja (`Mi Bandeja - Internos -> Ver`), el modelo `VentanillaRadicaInterno` evalúa la autorización contextual mediante el método `tieneAccesoUsuario($user)`.
  - Se autoriza la lectura si el usuario es: creador, responsable activo, destinatario, proyector, firmante, o funcionario dentro de la jerarquía organizacional del usuario consultante (jefe de dependencia/oficina). Si no cumple ninguna de estas condiciones, se rechaza con `HTTP 403`.
  - El permiso `can:... -> Editar` se reserva estrictamente para endpoints de mutación (`POST`, `PUT`, `DELETE`).
- **Detalle de Radicados Internos (`show`):**
  - Incluye documentos relacionados (`getDocumentosRelacionados(true)`), información de clasificación documental con jerarquía, responsables, custodios, estado de trabajo y URL pública/firmada de archivo digital (`getUrlArchivoDigital()`).
  - El método de URL para el archivo digital principal debe invocarse vía `$this->getUrlArchivoDigital()` y no mediante llamadas sin argumentos a `$this->getArchivoUrl()`.
- **Firma Electrónica e Integridad Transversal:**
  - El modelo `VentanillaRadicaInterno` implementa la relación polimórfica `firmaEventos()` (`morphMany(FirmaEvento::class, 'documentable')`).
  - El servicio `FirmaValidacionService` y el controlador `FirmaValidacionController` validan la integridad verificando el `hash_firmado` del último `FirmaEvento` contra el `hash_sha256` actual del documento. Si no existe evento de firma, se retorna HTTP 404 con mensaje explicativo.
- **Mi Bandeja Internos (Ordenamiento de Consultas):**
  - El endpoint de listado (`/api/mi-bandeja/internos/mis-radicados`) aplica ordenamiento descendente explícito por fecha de creación e id: `$query->orderBy('created_at', 'desc')->orderBy('id', 'desc');` para garantizar estabilidad y coherencia cronológica en la bandeja personal.

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
- **Documentación de Código (PHPDoc / DocBlocks)**:
  - Métodos en Controllers, Services, Repositories y Helpers deben incluir PHPDoc explicando el propósito del negocio (el *por qué*), parámetros `@param`, tipo de retorno `@return`, y excepciones lanzadas `@throws`.
  - En Models: documentar propiedades mágicas (`@property`), relaciones (`@method`) y scopes para permitir autocompletado estático y claridad de negocio.

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

## Mi Bandeja — Grupos Colaborativos (MiBandejaTemp)
- **Vinculación Polimórfica de Radicado**:
  - `mi_bandeja_temp` vincula radicados mediante `radicado_id` y `radicado_tipo` (`recibido`, `enviado`, `interno`).
  - No debe poseer foreign key rígida en base de datos hacia `ventanilla_radica_reci` (eliminada en migración `2026_10_01_160551_drop_foreign_radicado_id_on_mi_bandeja_temp`).
  - La integridad referencial se garantiza dinámicamente en el Form Request (`StoreMiBandejaTempRequest`) validando contra la tabla correspondiente (`ventanilla_radica_internos`, `ventanilla_radica_reci` o `ventanilla_radica_envia`).
  - El modelo `MiBandejaTemp` resuelve la relación mediante el accessor `getRadicadoAttribute()`.
