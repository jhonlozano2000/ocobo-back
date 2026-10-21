<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 19.29: el listado de Mis Documentos venía paginado de 20 en 20 pero sin datos de paginación,
 * así que el front solo mostraba los 20 más recientes. La búsqueda también se hace en el back.
 */
class MisDocumentosListadoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/comunicaciones-recibidas/documentos';

    private function documento(User $dueno, string $titulo, int $minutosAtras = 0): int
    {
        return DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $dueno->id, 'titulo' => $titulo, 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now()->subMinutes($minutosAtras),
        ]);
    }

    public function test_devuelve_la_paginacion_y_respeta_la_pagina_pedida(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $yo = User::factory()->create(['estado' => 1]);
        $otro = User::factory()->create(['estado' => 1]);
        for ($i = 1; $i <= 5; $i++) {
            $this->documento($yo, "Doc {$i}", $i);
        }
        $this->documento($otro, 'Ajeno');

        $this->actingAs($yo)->getJson(self::URL.'?per_page=2&page=3')
            ->assertOk()
            ->assertJsonPath('pagination', ['total' => 5, 'per_page' => 2, 'current_page' => 3, 'last_page' => 3])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Doc 5')
            ->assertJsonStructure(['data' => [['id', 'titulo', 'estado', 'creador' => ['id', 'name']]]]);
    }

    public function test_busca_por_titulo_o_creador_en_todos_los_documentos(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $yo = User::factory()->create(['estado' => 1, 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $otro = User::factory()->create(['estado' => 1, 'nombres' => 'Luis', 'apellidos' => 'Gómez']);
        for ($i = 1; $i <= 3; $i++) {
            $this->documento($yo, "Borrador {$i}");
        }
        $this->documento($yo, 'Acta de reunión', 60);
        $compartido = $this->documento($otro, 'Informe trimestral', 90);
        DB::table('mi_bandeja_temp_reci_usuarios')->insert([
            'documento_id' => $compartido, 'user_id' => $yo->id, 'rol' => 'responsable', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->documento($otro, 'Acta ajena');

        // El acta es la más antigua: con la búsqueda local en la primera página no aparecería
        $this->actingAs($yo)->getJson(self::URL.'?per_page=2&search=acta')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.titulo', 'Acta de reunión');

        $this->actingAs($yo)->getJson(self::URL.'?search=luis gómez')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.titulo', 'Informe trimestral');
    }

    public function test_rechaza_un_tamano_de_pagina_fuera_de_rango(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $yo = User::factory()->create(['estado' => 1]);

        $this->actingAs($yo)->getJson(self::URL.'?per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
