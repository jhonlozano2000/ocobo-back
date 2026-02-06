---
name: ocobo-tests
description: Guía para crear y ejecutar tests PHPUnit en OCOBO-BACK, incluyendo tests de endpoints con Sanctum, aserciones del shape de ApiResponseTrait y recomendaciones de RefreshDatabase. Usar cuando el usuario pida “tests”, “phpunit”, “feature test”, “probar endpoint”, o falle un test.
---

# OCOBO tests (PHPUnit)

## Dónde probar

- Endpoints/API: `tests/Feature`
- Lógica pura: `tests/Unit`

## Ejecutar

- Todo: `composer test`
- Filtrar: `composer test -- --filter NombreDelTest`

## Patrón para endpoints con Sanctum

1. Crear usuario (factory si existe, o crear directo si no).
2. Autenticar:
   - `\Laravel\Sanctum\Sanctum::actingAs($user);`
3. Llamar endpoint y validar:
   - HTTP status
   - JSON shape: `status`, `message`, `data|error`

## Plantilla mínima (Feature)

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class EndpointTest extends TestCase
{
    public function test_endpoint_retorna_shape_estandar(): void
    {
        $user = User::first() ?? User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/...');

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['status', 'message', 'data']);
    }
}
```

