# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

API backend de **OCOBO** (SGDEA, gestión documental): Ventanilla Única/radicados, PQRSD, TRD/TVD, Mi Bandeja, Archivo, Workflows, Firma electrónica. El frontend Next.js está en otro repo (`C:\Users\Jhon\Desktop\ocobo-frond`) y se autentica por cookies con Sanctum SPA.

Código, mensajes de validación, comentarios y commits en **español**.

**Fuentes de reglas.**
- `AGENTS.md` es la fuente más actualizada de reglas y flujos de negocio. Léelo antes de tocar Pase/custodio, Correspondencia Interna, Documentos colaborativos (Yjs) o Grupos colaborativos (radicado polimórfico `radicado_id` + `radicado_tipo`, sin FK).
- `.cursor/rules/*.mdc` contiene las convenciones por capa (rutas, controladores, requests, modelos, migraciones, tests).

## Stack
- PHP ^8.1 (local 8.3), **Laravel 12**, MySQL (Laragon). Los providers se registran en `config/app.php`; no existe `bootstrap/providers.php`.
- Sanctum 4 en **modo SPA con cookies HttpOnly (no tokens, no JWT)**. Spatie Permission v6 (guard `web`). Laravel Reverb para broadcasting.
- Paquetes relevantes: dompdf, phpword, phpspreadsheet, fpdi, intervention/image, mews/purifier, google2fa (2FA TOTP), imapengine (radicación por email).

## Comandos

```bash
composer install && php artisan key:generate
php artisan migrate && php artisan db:seed      # DatabaseSeeder incluye todos los Permisos/*Seeder
composer run pint                               # Laravel Pint (PSR-12)
composer run test                               # php artisan test
php artisan test tests/Feature/MiBandeja/GrupoColaborativoFirmaTest.php
php artisan test --filter=test_nombre_metodo
composer run routes                             # route:list
composer run cache-clear                        # optimize:clear
php artisan reverb:start                        # WebSockets
php artisan schedule:run                        # en Windows lo lanza schedule_run.bat
php artisan ocr start|stop|status               # microservicio OCR (PaddleOCR)
php artisan tests:cleanup                       # limpia datos residuales de tests
```

- Laragon sirve el repo en `http://ocobo-back.test`. El front corre en `http://ocobo.test:3000` y hace proxy de `/api`, `/sanctum` y `/broadcasting/auth` hacia aquí. Las cookies dependen de `SESSION_DOMAIN=ocobo.test` y `SANCTUM_STATEFUL_DOMAINS`.
- En local `QUEUE_CONNECTION=sync`: no hace falta worker.

### Tests
- PHPUnit 11. **Usan MySQL real, BD `ocobo_back_testing`** (forzada en `phpunit.xml`; nunca `ocobo_back`). Esa BD tiene que existir, estar migrada y tener los seeds: los permisos Spatie son necesarios para los tests de autorización.
- Usar `DatabaseTransactions`, **no** `RefreshDatabase` (borraría la BD de testing). Autenticar con `actingAs($user)` / `Sanctum::actingAs`. Métodos `test_...`.
- Hoy solo hay tests en `tests/Feature/MiBandeja/`.

## Arquitectura

### Módulos y rutas
Capas de cada módulo: `routes/<modulo>.php` → `app/Http/Controllers/<Modulo>/` → `app/Http/Requests/<Modulo>/` → `app/Services/<Modulo>/` → `app/Models/<Modulo>/` → `app/Http/Resources/<Modulo>/`.

Todos los archivos de rutas (incluidos `api.php` y `web.php`) se registran **solo** en **`app/Providers/RouteServiceProvider.php`**; `bootstrap/app.php` únicamente carga `console.php` y `/up`. con `Route::middleware('api')->prefix('api/<prefijo>')`. Un archivo de rutas nuevo **tiene que** agregarse ahí. Prefijos:
- `api` (login, logout, 2FA, getme, check-permissions)
- `api/control-acceso`, `api/calidad`, `api/config`, `api/clasifica-documental`, `api/gestion`
- `api/ventanilla`: archivos `ventanilla-recibida`, `-enviada`, `-interno`, `-pqrs`, `-email-radicados`
- `api/transversal` (firma, etc.)
- `api/mi-bandeja[/recibidos|/enviados|/internos]`
- `api/archivo`, `api/archivo/dashboard`, `api/workflows`, `api/reportes`, `api/dashboard`

Nombres de carpeta no siempre simétricos:
- Controllers, Models y Services usan `VentanillaUnica/` (subcarpetas Recibidos, Enviados, Internos, Pqrs); Requests usa `Ventanilla/`.
- Gestión de archivo = `OfiArchivo`.


### Flujo de una petición
Form Request (valida) → Controller (`use ApiResponseTrait`, `findOrFail`, transacción) → Service con la lógica de negocio (`RadicacionReciService`, `PqrsService`, `PaseHistorialService`, `FirmaService`, `WorkflowService`…) → Resource → `$this->successResponse(...)`.

### Envoltorio de respuesta (obligatorio)
- **Prohibido `response()->json(...)` en controladores.** Solo `App\Http\Traits\ApiResponseTrait`:

  | Método | HTTP |
  |---|---|
  | `successResponse` | 200 |
  | `createdResponse` | 201 |
  | `noContentResponse` | 204 |
  | `badRequestResponse` | 400 |
  | `unauthorizedResponse` | 401 |
  | `forbiddenResponse` | 403 |
  | `notFoundResponse` | 404 |
  | `conflictResponse` | 409 |
  | `validationResponse` / `warningResponse` | 422 |
  | `tooManyRequestsResponse` | 429 |
  | `serverErrorResponse` | 500 |

- Forma: `{status, success, message, data?|errors?}`. Si a `successResponse` le pasas un paginador (o una ResourceCollection paginada), deja los items en `data` y agrega `pagination` (`total, per_page, current_page, last_page`).
- **El código HTTP define el toast del frontend:**
  - 422 → warning.
  - 403 y 5xx → error.
  - 404 → info.

  Una regla de negocio que bloquea la acción ("ya fue firmado", "ya es responsable") → `warningResponse` (422), nunca 500.
- **422 de validación:** `message` debe ser el **primer error concreto**, no un texto genérico.
  - `bootstrap/app.php` ya renderiza `ValidationException`, 401, 403, 404, 429 y Throwable en este formato para `api/*`.
  - `SanitizedFormRequest::failedValidation` devuelve el mismo envoltorio.
- En `catch (\Exception $e)` → `$this->serverErrorResponse('Mensaje', $e->getMessage())`.
- Los Resources de PQRS/radicados incluyen `vuexy_badges` (`badge-light-{warning|info|success|danger}`); ver AGENTS.md.

### Auth y permisos
- Login: `GET /sanctum/csrf-cookie` → `POST /api/login`.
- El grupo `api` incluye `EnsureFrontendRequestsAreStateful`, `StartSession` y una cadena de seguridad: SecurityHeaders, AuditLog, ValidateContentType, ValidateRequestSize, DataIntegrity, CombinedSecurity, SanitizeResponse, throttle.
- Las rutas privadas van bajo `auth:sanctum`. La autorización usa `->middleware('can:<permiso>')` con strings literales como `'Radicar -> Cores. Recibida -> Listar'` o `'Mi Bandeja - Internos -> Ver'`.
  - **Deben coincidir exactamente con `database/seeders/Permisos/*Seeder.php`**, incluidos los typos existentes (p. ej. `'Atualizar fechas de radicados'`).
  - Permiso nuevo → agrégalo al seeder correspondiente. El frontend filtra menú y acciones con estos mismos strings.
- Las Policies están en `AuthServiceProvider` (VentanillaRadicaReci, User, Documento, Workflow, Tarea) y usan `hasPermissionTo()`.
- ABAC jerárquico: trait `app/Traits/AbacHierarquico.php`, con scopes `paraUsuario`, `paraMiDependencia`, `paraMisSubordinados` y `conPermisoJerarquico` sobre `cod_organico`.
  - `Radicar -> Ver Todos` salta el filtro.
  - Radicados internos: `VentanillaRadicaInterno::tieneAccesoUsuario($user)`.
- Todos los rate limiters se definen en `AppServiceProvider::configurarRateLimiting()` (`api` 120/min; `login`, `radicacion`, `uploads`, `search`, `firma`, `documentos`, `2fa-*`, etc.

### Archivos
- Siempre vía `App\Helpers\ArchivoHelper`, nunca `Storage` directo. Métodos: `guardarArchivo`, `guardarArchivoConHash`, `guardarMultiplesConHash`, `eliminarArchivo`, `obtenerUrl`, `validarArchivoSeguro`, `validarMagicBytes`.
- Los discos por dominio están en `config/filesystems.php`: `radicados_recibidos`, `radicados_enviados`, `radicados_internos`, `pqrs_archivos`, `archivo_expedientes`, `firmas`, `plantillas`, `mi_bandeja_temp`, `workflows_archivos`…
- Modelos con archivos:
  - Implementan `getArchivoUrl(string $campo, string $disk)`.
  - La integridad se guarda en `hash_sha256`.
  - Los archivos viejos se borran **después** del `DB::commit()`.
  - Al borrar el modelo: `static::deleted(...)` + `ArchivoHelper::eliminarArchivo`.
- Tamaño máximo y tipos permitidos salen de `config_varias` (`max_tamano_archivo`, `tipos_archivos_permitidos`).

### Scheduler (`routes/console.php`)
`pqrs:marcar-vencidas`, `alertas:vencimiento`, `alertas:retencion-trd`, `archivo:depurar-digital` (modo cuarentena), `grupos-colaborativos:liberar-bloqueos`, `reportes:generar-programados`.

## Reglas del proyecto

- **Rutas:**
  - Orden: primero las específicas sin parámetros, luego las específicas con parámetros, y al final `Route::apiResource(...)->except('create','edit')`.
  - Nombres `{modulo}.{recurso}.{accion}`; URLs en kebab-case.
- **Controladores:**
  - `Model::findOrFail($id)` (sin route-model binding).
  - Para actualizar: `if (!empty($validated)) { $m->fill($validated); $m->save(); } $m->refresh();`.
  - Los recursos principales exponen `estadisticas()`.
  - Eager loading con columnas explícitas (`'usuario:id,nombres,apellidos'`) y `select()` para evitar N+1.
- **Transacciones:**
  - Toda operación multi-tabla va dentro de `DB::beginTransaction()`/`commit`/`rollBack` o `DB::transaction()`.
  - Ejemplo crítico: `VentanillaRadicaReciController@store` crea radicado + PQRS (`PqrsService::crearDesdeRadicado`) en una sola transacción.
  - Si cambian conteos, invalida caches de estadísticas (`ventanilla_recibidos_estadisticas`, `ventanilla_pqrs_estadisticas`).
- **Form Requests:**
  - `XxxRequest` en `app/Http/Requests/<Modulo>/`.
  - Con `authorize()`, `rules()` (en array si usa `Rule::` o closures), y `messages()`/`attributes()` en español.
  - `SanitizedFormRequest` es la base disponible: sanitiza y usa `stopOnFirstFailure`.
- **Modelos:**
  - `$fillable`; `$table` explícito si no sigue la convención.
  - Relaciones con FKs reales; scopes `scopeActivo`; PHPDoc `@property`.
- **Migraciones:**
  - Clase anónima con `up(): void`/`down(): void`.
  - `unsignedBigInteger` + `foreign()`; índices en FKs y en filtros compuestos; `unique` en pivots.
- **Naming PSR-4:** el nombre del archivo coincide exactamente con la clase, en PascalCase. Hay modelos legados en camelCase en `app/Models/Configuracion/` (p. ej. `configVentanilla.php`): no los copies ni los renombres fuera de un refactor dedicado.
- **PHPDoc** obligatorio en métodos públicos de Controllers/Services/Helpers: explica el porqué de negocio, más `@param`, `@return` y `@throws`.
- **Seguridad:**
  - Nada de SQL concatenado.
  - Validación MIME con la regla `MagicMime` / `ArchivoHelper::validarArchivoSeguro`.
  - Códigos de verificación de 10 dígitos con `random_int()`.
- **Commits:** Conventional Commits en español (`feat(scope): …`, `fix(scope): …`).
- CI (`.github/workflows/security.yml`): `pint --test`, `composer audit` y `php artisan test` sobre MySQL con `migrate --seed`.
- No leas ni versiones `cookies.txt`. Los scripts `check_*.php` y `debug*.php` de la raíz son de depuración local (están en `.gitignore`).
