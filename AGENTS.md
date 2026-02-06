# OCOBO-BACK (guía rápida para IA)

## Estructura por módulos

- **Rutas**: `routes/<modulo>.php` (prefijo aplicado en `app/Providers/RouteServiceProvider.php`)
  - `/api/control-acceso` → `routes/controlAcceso.php`
  - `/api/calidad` → `routes/calidad.php`
  - `/api/config` → `routes/configuracion.php`
  - `/api/clasifica-documental` → `routes/clasifica_documental.php`
  - `/api/gestion` → `routes/gestion.php`
  - `/api/ventanilla` → `routes/ventanilla.php`
- **Controladores**: `app/Http/Controllers/{Modulo}/`
- **Requests**: `app/Http/Requests/{Modulo}/`
- **Modelos**: `app/Models/{Modulo}/`
- **Migraciones**: `database/migrations/`

## Reglas críticas del proyecto

- **Rutas**: definir rutas específicas **ANTES** de `Route::apiResource(...)` para evitar colisiones (`/recurso/estadisticas` vs `/recurso/{id}`).
- **Auth**: rutas privadas con `auth:sanctum` (públicas fuera del group).
- **Respuestas JSON**: usar `App\Http\Traits\ApiResponseTrait`.
- **Validación**: siempre con Form Requests; mensajes en español. Donde aplique, usar `failedValidation()` con respuesta 422 uniforme.
- **Archivos**: usar `App\Helpers\ArchivoHelper`.

## Comandos (composer)

- `composer pint`
- `composer test`
- `composer routes`
- `composer cache-clear`

## Tests (PHPUnit)

- Ejecutar todo: `composer test`
- Ejecutar por filtro: `composer test -- --filter NombreDelTest`
- Para endpoints con `auth:sanctum`: usar `Laravel\Sanctum\Sanctum::actingAs($user)` en `tests/Feature`.

## Portabilidad (Windows vs Linux)

- Mantener **case** consistente entre **archivo** y **clase** (PSR-4). Evitar introducir nuevos archivos/clases con nombres tipo `configVentanilla.php` / `class configVentanilla`.

## Ruido en CLI (PHP sqlsrv/pdo_sqlsrv)

Si al ejecutar comandos aparece:
`Unable to load dynamic library 'php_pdo_sqlsrv...'` / `php_sqlsrv...`

Solución (Laragon):
- Editar tu `php.ini` del PHP activo y **comentar** las líneas `extension=sqlsrv` / `extension=pdo_sqlsrv` si no usas SQL Server, **o**
- Instalar los binarios correctos de `sqlsrv`/`pdo_sqlsrv` para tu versión exacta de PHP (8.3, x64, nts/ts) y habilitarlos.

