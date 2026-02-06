---
name: ocobo-rutas
description: Estandariza cómo crear y registrar rutas API por módulo en OCOBO-BACK (Laravel 10), incluyendo prefijos en RouteServiceProvider, orden correcto de rutas (rutas específicas antes de apiResource) y convención de nombres. Usar cuando se agreguen endpoints o nuevos archivos en routes/.
---

# OCOBO rutas (Laravel)

## Objetivo

Mantener rutas **modulares**, consistentes y sin colisiones.

## Dónde van las rutas

- `routes/api.php`: solo autenticación/base (`/register`, `/login`, `/getme`, etc.).
- `routes/<modulo>.php`: rutas del módulo (ej. `controlAcceso.php`, `ventanilla.php`, `calidad.php`, ...).
- Registro de cada archivo modular: `app/Providers/RouteServiceProvider.php` (prefijo `api/<modulo>`).

## Checklist al agregar rutas

1. Elegir el módulo y **reutilizar** su archivo en `routes/`.
2. Todas las rutas privadas deben estar dentro de `Route::middleware('auth:sanctum')->group(...)`.
3. Agrupar por recurso con `Route::prefix()` y, si aplica, `->name()`.
4. **Regla crítica de orden** (para evitar que Laravel interprete `/estadisticas` como `/{id}`):
   - primero: rutas específicas **sin** parámetros
   - segundo: rutas específicas **con** parámetros
   - último: `Route::apiResource(...)`
5. Si creas un **nuevo archivo** `routes/<nuevoModulo>.php`, también debes registrarlo en `RouteServiceProvider` con su prefijo.

## Plantilla recomendada (archivo de módulo)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Modulo\RecursoController;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('recurso')->name('modulo.recurso.')->group(function () {
        // 1) Específicas SIN parámetros
        Route::get('/estadisticas', [RecursoController::class, 'estadisticas'])->name('estadisticas');

        // 2) Específicas CON parámetros
        Route::get('/por-sede/{sedeId}', [RecursoController::class, 'listarPorSede'])->name('por-sede');

        // 3) Resource AL FINAL
        Route::apiResource('/', RecursoController::class)->except('create', 'edit');
    });
});
```

## Plantilla de registro (RouteServiceProvider)

```php
Route::middleware('api')
    ->prefix('api/modulo')
    ->group(base_path('routes/modulo.php'));
```

