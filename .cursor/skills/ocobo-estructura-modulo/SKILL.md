---
name: ocobo-estructura-modulo
description: Define y aplica la estructura estándar de un módulo en OCOBO-BACK (Laravel 10): crear archivo de rutas, registrarlo en RouteServiceProvider con prefijo api/<modulo>, crear carpetas de Controllers/Requests/Models, y checklist para recursos. Usar cuando el usuario diga “crear módulo”, “nuevo módulo”, “estructura del módulo” o “agregar módulo”.
---

# OCOBO estructura de módulo

## Objetivo

Crear módulos consistentes (misma estructura y wiring de rutas) y evitar inconsistencias de prefijos/namespaces.

## Datos que se deben definir siempre

- **slug del módulo** (para URL/prefijo): ejemplo `control-acceso`, `ventanilla`, `calidad`.
- **archivo de rutas**: ejemplo `routes/controlAcceso.php`.
- **namespace de controladores/requests/modelos**: `App\Http\Controllers\<Modulo>\`, etc.

## Workflow: crear módulo nuevo

1. Crear `routes/<modulo>.php`.
2. Registrar en `app/Providers/RouteServiceProvider.php` con:

```php
Route::middleware('api')
    ->prefix('api/<modulo>')
    ->group(base_path('routes/<modulo>.php'));
```

3. Crear directorios:
   - `app/Http/Controllers/<Modulo>/`
   - `app/Http/Requests/<Modulo>/`
   - (si aplica) `app/Models/<Modulo>/`

## Workflow: agregar recurso al módulo

- Rutas:
  - `auth:sanctum` si es privado
  - rutas específicas antes de `apiResource`
- Controller:
  - `use ApiResponseTrait`
  - `try/catch` y transacción si aplica
- Requests:
  - `rules/messages/attributes` en español
  - `failedValidation()` con 422 uniforme cuando el módulo lo requiera

