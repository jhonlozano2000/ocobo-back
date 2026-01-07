# Reglas del Proyecto OCOBO-BACK

Este documento establece las reglas, estándares y convenciones que deben seguirse durante el desarrollo del proyecto OCOBO-BACK.

## 📋 Índice

1. [Estándares de Código](#estándares-de-código)
2. [Estructura de Archivos](#estructura-de-archivos)
3. [Convenciones de Nomenclatura](#convenciones-de-nomenclatura)
4. [Arquitectura y Patrones](#arquitectura-y-patrones)
5. [APIs y Respuestas](#apis-y-respuestas)
6. [Base de Datos](#base-de-datos)
7. [Validaciones](#validaciones)
8. [Rutas](#rutas)
9. [Documentación](#documentación)
10. [Seguridad](#seguridad)
11. [Testing](#testing)
12. [Git y Commits](#git-y-commits)

---

## 1. Estándares de Código

### 1.1 PHP Coding Standards

- **Estándar**: Seguir **PSR-12** (PHP Coding Standards)
- **IDE**: Configurar el IDE para aplicar PSR-12 automáticamente
- **Linter**: Usar Laravel Pint (`php artisan pint`) antes de commits

### 1.2 Formato de Código

- **Indentación**: 4 espacios (NO tabs)
- **Líneas**: Máximo 120 caracteres por línea
- **Líneas en blanco**: 
  - Después de `namespace`
  - Después de `use` statements
  - Entre métodos
  - Antes de `return` cuando hay lógica compleja

### 1.3 Type Hints y Return Types

```php
// ✅ CORRECTO
public function store(StoreUserRequest $request): JsonResponse
{
    // código
}

// ❌ INCORRECTO
public function store($request)
{
    // código
}
```

### 1.4 Declaraciones de Tipos

- **Siempre** usar type hints en parámetros
- **Siempre** declarar return types en métodos públicos
- **Usar** tipos estrictos cuando sea posible

---

## 2. Estructura de Archivos

### 2.1 Organización Modular

El proyecto está organizado por **módulos funcionales**:

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                    # Autenticación
│   │   ├── ControlAcceso/           # Control de acceso y usuarios
│   │   ├── Configuracion/           # Configuración del sistema
│   │   ├── Calidad/                 # Gestión de calidad
│   │   ├── ClasificacionDocumental/ # Clasificación documental
│   │   ├── VentanillaUnica/         # Ventanilla única
│   │   └── Gestion/                 # Gestión general
│   ├── Requests/                    # Form Request classes
│   │   └── [Mismo orden de módulos]
│   └── Traits/
├── Models/
│   └── [Mismo orden de módulos]
└── ...
```

### 2.2 Ubicación de Archivos

- **Controladores**: `app/Http/Controllers/{Modulo}/`
- **Form Requests**: `app/Http/Requests/{Modulo}/`
- **Modelos**: `app/Models/{Modulo}/`
- **Seeders**: `database/seeders/{Modulo}/`
- **Migraciones**: `database/migrations/`

### 2.3 Nombres de Archivos

- **Controladores**: PascalCase, sufijo `Controller` (ej: `UserController.php`)
- **Form Requests**: PascalCase, sufijo `Request` (ej: `StoreUserRequest.php`)
- **Modelos**: PascalCase, singular (ej: `User.php`)
- **Migraciones**: snake_case con timestamp (ej: `2024_12_01_create_users_table.php`)

---

## 3. Convenciones de Nomenclatura

### 3.1 Clases

- **PascalCase**: `UserController`, `StoreUserRequest`, `CalidadOrganigrama`

### 3.2 Métodos

- **camelCase**: `getUser()`, `createRadicacion()`, `listDependencias()`

### 3.3 Variables

- **camelCase**: `$userId`, `$configVarias`, `$numRadicado`

### 3.4 Constantes

- **UPPER_SNAKE_CASE**: `MAX_FILE_SIZE`, `DEFAULT_PER_PAGE`

### 3.5 Tablas de Base de Datos

- **snake_case**, plural: `users`, `config_varias`, `ventanilla_radica_reci`

### 3.6 Columnas de Base de Datos

- **snake_case**: `num_radicado`, `fecha_documento`, `user_register`

### 3.7 Rutas

- **kebab-case**: `/api/control-acceso/users`, `/api/config/config-varias`

---

## 4. Arquitectura y Patrones

### 4.1 Estructura de Controladores

**TODOS** los controladores deben:

1. Extender `Controller`
2. Usar `ApiResponseTrait` para respuestas estandarizadas
3. Usar Form Requests para validaciones
4. Usar transacciones de BD cuando sea necesario

```php
<?php

namespace App\Http\Controllers\ControlAcceso;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\ControlAcceso\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Lógica del método

            DB::commit();
            return $this->successResponse($user, 'Usuario creado exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear el usuario', $e->getMessage(), 500);
        }
    }
}
```

### 4.2 Métodos de Controlador Estándar

Cada controlador de recursos debe implementar:

- `index()` - Listar recursos
- `store()` - Crear recurso
- `show()` - Mostrar recurso específico
- `update()` - Actualizar recurso
- `destroy()` - Eliminar recurso

### 4.3 Métodos Adicionales Comunes

- `estadisticas()` - Estadísticas del módulo
- Métodos específicos según necesidad del módulo

### 4.4 Uso de Traits

- **ApiResponseTrait**: OBLIGATORIO en todos los controladores de API
- **SoftDeletes**: Cuando sea necesario en modelos
- Otros traits según necesidad

---

## 5. APIs y Respuestas

### 5.1 Formato de Respuesta Estándar

**TODAS** las respuestas JSON deben usar `ApiResponseTrait`:

#### Respuesta Exitosa (200/201)

```php
return $this->successResponse($data, 'Mensaje de éxito', 201);
```

```json
{
    "status": true,
    "message": "Usuario creado exitosamente",
    "data": { ... }
}
```

#### Respuesta de Error

```php
return $this->errorResponse('Mensaje de error', $errorDetails, 400);
```

```json
{
    "status": false,
    "message": "Error al crear el usuario",
    "error": "Detalles del error"
}
```

### 5.2 Códigos HTTP

- `200` - OK (operación exitosa)
- `201` - Created (recurso creado)
- `400` - Bad Request (solicitud incorrecta)
- `401` - Unauthorized (no autenticado)
- `403` - Forbidden (sin permisos)
- `404` - Not Found (recurso no encontrado)
- `422` - Validation Error (error de validación)
- `500` - Server Error (error interno)

### 5.3 Manejo de Errores

**SIEMPRE** usar try-catch en métodos que:
- Acceden a base de datos
- Procesan archivos
- Realizan operaciones que pueden fallar

```php
try {
    // Operación
} catch (\Exception $e) {
    DB::rollBack();
    return $this->errorResponse('Mensaje de error', $e->getMessage(), 500);
}
```

---

## 6. Base de Datos

### 6.1 Transacciones

**SIEMPRE** usar transacciones para operaciones que:
- Crean múltiples registros relacionados
- Actualizan múltiples tablas
- Realizan operaciones críticas

```php
DB::beginTransaction();

try {
    // Operaciones
    DB::commit();
    return $this->successResponse($data, 'Operación exitosa');
} catch (\Exception $e) {
    DB::rollBack();
    return $this->errorResponse('Error', $e->getMessage(), 500);
}
```

### 6.2 Eager Loading

**SIEMPRE** usar eager loading para evitar N+1 queries:

```php
// ✅ CORRECTO
$users = User::with('roles', 'cargo')->get();

// ❌ INCORRECTO
$users = User::all();
foreach ($users as $user) {
    $user->roles; // N+1 query
}
```

### 6.3 Consultas Optimizadas

- Usar `select()` para limitar columnas cuando sea necesario
- Usar índices en campos frecuentemente consultados
- Paginar listados grandes

```php
$users = User::select('id', 'nombres', 'email')
    ->with('roles:id,name')
    ->paginate(15);
```

### 6.4 Migraciones

- **Nombres descriptivos**: `create_users_table`, `add_avatar_to_users_table`
- **Timestamps**: Incluir `created_at` y `updated_at` cuando sea apropiado
- **Soft Deletes**: Usar cuando sea necesario para borrado lógico
- **Foreign Keys**: Definir relaciones y constraints

### 6.5 Seeders

- Organizar por módulo
- Usar factories cuando sea posible
- Comentar seeders en `DatabaseSeeder.php`

---

## 7. Validaciones

### 7.1 Form Request Classes

**TODAS** las validaciones deben estar en Form Request classes:

```php
<?php

namespace App\Http\Requests\ControlAcceso;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización se maneja a través de middleware
    }

    public function rules(): array
    {
        return [
            'nombres' => 'required|string|max:70',
            'email' => 'required|email|unique:users,email|max:70',
            'password' => 'required|min:6|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'nombres.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'El correo electrónico ya está en uso.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombres' => 'nombre',
            'email' => 'correo electrónico',
        ];
    }
}
```

### 7.2 Reglas de Validación Comunes

- **Nombres**: `required|string|max:70`
- **Email**: `required|email|unique:table,column|max:70`
- **Documentos**: `required|string|unique:table,column|max:20`
- **Contraseñas**: `required|min:6|confirmed`
- **Fechas**: `required|date|date_format:Y-m-d`
- **Booleanos**: `required|boolean`
- **Archivos**: `nullable|file|mimes:pdf,doc,docx|max:10240`

### 7.3 Validaciones Personalizadas

Para lógica de validación compleja, usar closures:

```php
'parent' => [
    'nullable',
    'integer',
    'exists:calidad_organigrama,id',
    function ($attribute, $value, $fail) {
        // Lógica de validación personalizada
    }
]
```

---

## 8. Rutas

### 8.1 Organización de Rutas

Las rutas están organizadas por módulos en archivos separados:

- `routes/api.php` - Autenticación
- `routes/controlAcceso.php` - Control de acceso
- `routes/configuracion.php` - Configuración
- `routes/calidad.php` - Calidad
- `routes/clasifica_documental.php` - Clasificación documental
- `routes/ventanilla.php` - Ventanilla única
- `routes/gestion.php` - Gestión

### 8.2 Estructura de Rutas

**REGLA CRÍTICA**: Rutas específicas **ANTES** de `apiResource`:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('recurso')->name('modulo.recurso.')->group(function () {
        // ✅ Rutas específicas ANTES del resource
        Route::get('/estadisticas', [Controller::class, 'estadisticas'])->name('estadisticas');
        
        // ✅ Resource route DESPUÉS de las rutas específicas
        Route::apiResource('', Controller::class)
            ->parameters(['' => 'recurso'])
            ->names([
                'index' => 'index',
                'store' => 'store',
                'show' => 'show',
                'update' => 'update',
                'destroy' => 'destroy'
            ])->except('create', 'edit');
    });
});
```

### 8.3 Nomenclatura de Rutas

- **Formato**: `{modulo}.{recurso}.{accion}`
- **Ejemplo**: `calidad.organigrama.index`, `config.sedes.estadisticas`

### 8.4 Prefijos de Rutas

Los prefijos se configuran en `RouteServiceProvider`:
- `/api/control-acceso` - Control de acceso
- `/api/config` - Configuración
- `/api/calidad` - Calidad
- `/api/clasifica-documental` - Clasificación documental
- `/api/ventanilla` - Ventanilla única
- `/api/gestion` - Gestión

### 8.5 Middleware

- **Autenticación**: `auth:sanctum` en todas las rutas protegidas
- **Autorización**: Middleware adicional según necesidad
- **Rate Limiting**: 60 requests por minuto (configurado globalmente)

---

## 9. Documentación

### 9.1 PHPDoc en Controladores

**TODOS** los métodos públicos deben tener PHPDoc completo:

```php
/**
 * Obtiene un listado de usuarios del sistema.
 *
 * Este método retorna un listado paginado de usuarios con sus relaciones
 * (roles, cargo, oficina, dependencia) según los filtros proporcionados.
 *
 * @param Request $request La solicitud HTTP con parámetros de filtrado
 * @return JsonResponse Respuesta JSON con el listado de usuarios
 *
 * @queryParam search string Buscar por nombre, email o documento. Example: "Juan"
 * @queryParam solo_activos boolean Filtrar solo usuarios activos. Example: true
 * @queryParam per_page integer Número de elementos por página. Example: 15
 *
 * @response 200 {
 *   "status": true,
 *   "message": "Listado obtenido exitosamente",
 *   "data": [...]
 * }
 */
public function index(Request $request): JsonResponse
```

### 9.2 PHPDoc en Modelos

Documentar relaciones y métodos importantes:

```php
/**
 * Relación con los roles del usuario.
 *
 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
 */
public function roles(): BelongsToMany
```

### 9.3 Comentarios en Código

- **Evitar** comentarios obvios
- **Usar** comentarios para explicar lógica compleja
- **Mantener** comentarios actualizados

---

## 10. Seguridad

### 10.1 Autenticación

- **TODAS** las rutas protegidas deben usar `auth:sanctum`
- Tokens Bearer en header: `Authorization: Bearer {token}`
- Tokens expirables según configuración

### 10.2 Autorización

- Usar Spatie Laravel-Permission para roles y permisos
- Verificar permisos en middleware o controladores

### 10.3 Validación de Entrada

- **SIEMPRE** validar entrada de usuario con Form Requests
- **NUNCA** confiar en datos del cliente
- Sanitizar datos antes de guardar

### 10.4 Protección CSRF

- Middleware CSRF en rutas web
- Excluir rutas API cuando sea apropiado

### 10.5 Archivos

- Validar tipo MIME y extensión
- Validar tamaño máximo
- Almacenar fuera de directorio público
- Usar nombres únicos para evitar conflictos

### 10.6 SQL Injection

- **SIEMPRE** usar Eloquent o Query Builder (parametrizado)
- **NUNCA** usar concatenación de strings para queries

```php
// ✅ CORRECTO
User::where('email', $email)->first();

// ❌ INCORRECTO
DB::select("SELECT * FROM users WHERE email = '$email'");
```

---

## 11. Testing

### 11.1 Estructura de Tests

- **Feature Tests**: `tests/Feature/`
- **Unit Tests**: `tests/Unit/`
- Organizar tests por módulo

### 11.2 Convenciones

- Nombres descriptivos: `test_can_create_user()`
- Usar factories para datos de prueba
- Limpiar datos después de cada test

### 11.3 Ejecutar Tests

```bash
# Todos los tests
php artisan test

# Tests específicos
php artisan test --filter UserControllerTest

# Con cobertura
php artisan test --coverage
```

---

## 12. Git y Commits

### 12.1 Convenciones de Commits

Usar **Conventional Commits**:

```
feat: Agregar sistema de notificaciones por correo
fix: Corregir validación de archivos en radicaciones
docs: Actualizar documentación de API
style: Aplicar PSR-12 con Laravel Pint
refactor: Optimizar consultas en UserController
test: Agregar tests para UserCargoController
chore: Actualizar dependencias de composer
```

### 12.2 Tipos de Commits

- `feat`: Nueva funcionalidad
- `fix`: Corrección de bug
- `docs`: Documentación
- `style`: Formato de código
- `refactor`: Refactorización
- `test`: Tests
- `chore`: Tareas de mantenimiento

### 12.3 Branching Strategy

- `main` / `master`: Código de producción
- `develop`: Código de desarrollo
- `feature/nombre-feature`: Nuevas funcionalidades
- `fix/nombre-fix`: Correcciones de bugs

### 12.4 Pre-commit

Antes de commit:
- Ejecutar `php artisan pint`
- Ejecutar tests relevantes
- Verificar que no haya errores de linting

---

## 13. Recursos Adicionales

### 13.1 Helpers Personalizados

- **ArchivoHelper**: Para gestión de archivos
- Ubicación: `app/Helpers/ArchivoHelper.php`

### 13.2 Comandos Útiles

```bash
# Limpiar cachés
php artisan optimize:clear

# Ver rutas
php artisan route:list --name="calidad"

# Regenerar autoloader
composer dump-autoload

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed
```

### 13.3 Referencias

- [Laravel 10.x Documentation](https://laravel.com/docs/10.x)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [Spatie Laravel-Permission](https://spatie.be/docs/laravel-permission)
- [Laravel Sanctum](https://laravel.com/docs/10.x/sanctum)

---

## 14. Checklist para Nuevas Funcionalidades

Al agregar una nueva funcionalidad, verificar:

- [ ] Controlador creado en el módulo correcto
- [ ] Form Request creado para validaciones
- [ ] Modelo creado con relaciones y scopes necesarios
- [ ] Migración creada con timestamps apropiados
- [ ] Rutas definidas en archivo de rutas del módulo
- [ ] Rutas específicas ANTES de apiResource
- [ ] Uso de ApiResponseTrait en controlador
- [ ] Transacciones de BD cuando sea necesario
- [ ] PHPDoc completo en métodos públicos
- [ ] Eager loading en consultas
- [ ] Manejo de errores con try-catch
- [ ] Middleware de autenticación aplicado
- [ ] Tests creados (si aplica)

---

**Última actualización**: Diciembre 2024  
**Versión del documento**: 1.0

