---
name: ocobo-scaffold-endpoint
description: Scaffold guiado para crear un endpoint en OCOBO-BACK: decidir módulo/prefijo, agregar rutas en el archivo correcto, crear Form Requests, implementar controlador con ApiResponseTrait y actualizar modelo/migración cuando aplique. Usar cuando el usuario pida “crear endpoint”, “nuevo recurso”, “CRUD”, “ruta nueva” o “migración/modelo/request”.
---

# OCOBO scaffold endpoint

## Salida esperada (si aplica)

- Rutas en `routes/<modulo>.php` (orden correcto, `auth:sanctum`).
- Controller en `app/Http/Controllers/<Modulo>/`.
- Requests en `app/Http/Requests/<Modulo>/`.
- Modelo en `app/Models/<Modulo>/` (si aplica).
- Migración en `database/migrations/` (si aplica).

## Workflow

1. **Identificar módulo** según el prefijo existente:
   - control acceso → `routes/controlAcceso.php` (`/api/control-acceso`)
   - ventanilla → `routes/ventanilla.php` (`/api/ventanilla`)
   - calidad → `routes/calidad.php` (`/api/calidad`)
   - config → `routes/configuracion.php` (`/api/config`)
   - clasifica documental → `routes/clasifica_documental.php` (`/api/clasifica-documental`)
   - gestión → `routes/gestion.php` (`/api/gestion`)
2. **Rutas**:
   - `Route::middleware('auth:sanctum')->group(...)`
   - agrupar con `prefix()` y `name()`
   - **específicas antes de `apiResource`**
3. **Requests**:
   - crear `StoreXRequest` y `UpdateXRequest` (y `ListXRequest` si hay filtros)
   - incluir `messages()` + `attributes()` en español
   - si el módulo lo requiere, agregar `prepareForValidation()` (merge JSON) y `failedValidation()` (422 uniforme)
4. **Controlador**:
   - `use ApiResponseTrait`
   - `try/catch` + transacción cuando haya múltiples operaciones
   - `findOrFail($id)` (evitar inyección de modelo si el estándar del repo lo prohíbe)
5. **Modelo / migración** (si aplica):
   - definir `$table` si no coincide
   - `$fillable`, relaciones, scopes por `estado`
   - migración con FKs + índices + unique (si pivot) + comments (si el módulo ya los usa)

## Mini-plantillas

### Ruta (recordatorio de orden)

```php
Route::prefix('recurso')->name('modulo.recurso.')->group(function () {
    // específicas
    Route::get('/estadisticas', [RecursoController::class, 'estadisticas'])->name('estadisticas');
    // resource al final
    Route::apiResource('/', RecursoController::class)->except('create', 'edit');
});
```

### failedValidation (uniforme)

```php
protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
{
    $response = response()->json([
        'status'  => false,
        'message' => 'Errores de validación.',
        'errors'  => $validator->errors(),
    ], 422);

    throw new \Illuminate\Validation\ValidationException($validator, $response);
}
```

