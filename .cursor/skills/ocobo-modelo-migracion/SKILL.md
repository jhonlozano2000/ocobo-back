---
name: ocobo-modelo-migracion
description: Estandariza cómo crear modelos Eloquent y migraciones en OCOBO-BACK (Laravel 10): módulos en app/Models/{Modulo}, fillable/table/casts/relaciones/scopes/eventos, y migraciones con FKs, índices, constraints y comentarios. Usar cuando se creen modelos o migraciones.
---

# OCOBO modelos y migraciones

## Modelos (Eloquent)

### Ubicación y nombres

- Ubicación: `app/Models/{Modulo}/` (o `app/Models/` si es transversal).
- Clases: PascalCase.
- Si la tabla no coincide con convención de Laravel, definir `protected $table`.

### Checklist de modelo

- `protected $fillable = [...]` con campos que se asignan por `create()`/`fill()`.
- `protected $casts = [...]` cuando aplique (fechas, boolean, etc.).
- Relaciones explícitas (`belongsTo`, `hasMany`, `belongsToMany`) con llaves foráneas reales del esquema.
- Scopes simples cuando existan flags (ej. `scopeActivo()`/`scopeInactivo()`).
- Si el modelo maneja archivos: usar `ArchivoHelper` y exponer método genérico `getArchivoUrl($campo, $disk)`.

### Plantilla breve (modelo con archivo)

```php
<?php

namespace App\Models\Modulo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Helpers\ArchivoHelper;

class Recurso extends Model
{
    use HasFactory;

    protected $table = 'tabla_real';

    protected $fillable = [
        // ...
        'archivo',
    ];

    public function getArchivoUrl(string $campo, string $disk): ?string
    {
        return ArchivoHelper::obtenerUrl($this->{$campo} ?? null, $disk);
    }
}
```

## Migraciones

### Estándar del repo

- Archivo en `database/migrations/` con timestamp.
- Usar **clase anónima**:
  - `return new class extends Migration { ... };`
- Incluir `up(): void` y `down(): void`.
- Agregar:
  - claves foráneas (`foreign(...)`)
  - índices (especialmente sobre FKs y filtros)
  - unique constraints si evita duplicados
  - comments si ya es el patrón de la tabla

### Plantilla breve (create table)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabla', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabla');
    }
};
```

