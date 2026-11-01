<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pendientes por radicar se exportaba en el navegador con los campos crudos de la página cargada,
 * las fechas solo filtraban con ambas y per_page no tenía tope.
 */
class ExportPendientesRadicarTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1, 'nombres' => 'Zoraida', 'apellidos' => 'Quintero']);
        $this->user->givePermissionTo(Permission::findOrCreate('Radicar -> Cores. Enviada -> Listar', 'web'));
    }

    private function grupo(string $nombre, string $actualizado, string $tarea = 'cumplido'): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('mi_bandeja_temp')->insertGetId([
            'nombre' => $nombre, 'asunto' => "Asunto {$nombre}", 'radicado_id' => 999999, 'radicado_tipo' => 'recibido',
            'usua_crea_id' => $this->user->id, 'plantilla_cargada' => true,
            'created_at' => $actualizado, 'updated_at' => $actualizado,
        ]);
        DB::table('mi_bandeja_temp_grupo_proyectores')->insert([
            'grupo_id' => $id, 'user_id' => $this->user->id, 'estado_tarea' => $tarea,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function nombres(string $query): array
    {
        $respuesta = $this->actingAs($this->user)
            ->getJson('/api/ventanilla/radica-enviada/pendientes-radicar?per_page=100&search=PRTEST&'.$query)
            ->assertOk()->json();

        $nombres = array_column($respuesta['data']['data'] ?? $respuesta['data'], 'nombre');
        sort($nombres);

        return $nombres;
    }

    public function test_fechas_por_separado_y_hasta_inclusivo(): void
    {
        $this->grupo('PRTEST viejo', '2026-01-10 09:00:00');
        $this->grupo('PRTEST nuevo', '2026-03-20 17:30:00');
        $this->grupo('PRTEST en curso', '2026-03-20 17:30:00', 'pendiente');

        $this->assertSame(['PRTEST nuevo'], $this->nombres('fecha_desde=2026-02-01'));
        $this->assertSame(['PRTEST viejo'], $this->nombres('fecha_hasta=2026-01-10'));
        $this->assertSame(['PRTEST nuevo'], $this->nombres('fecha_desde=2026-03-20&fecha_hasta=2026-03-20'));
        $this->actingAs($this->user)->getJson('/api/ventanilla/radica-enviada/pendientes-radicar?per_page=5000')->assertStatus(422);
    }

    public function test_el_export_aplica_los_filtros_y_la_seleccion(): void
    {
        $viejo = $this->grupo('PRTEST viejo', '2026-01-10 09:00:00');
        $this->grupo('PRTEST nuevo', '2026-03-20 17:30:00');
        $this->grupo('PRTEST en curso', '2026-03-20 17:30:00', 'pendiente');

        $filas = function (string $query) {
            $contenido = $this->actingAs($this->user)
                ->get('/api/ventanilla/radica-enviada/pendientes-radicar/export?format=csv&search=PRTEST&'.$query)
                ->assertOk()->streamedContent();

            return array_map('str_getcsv', array_values(array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF")))));
        };

        $desde = $filas('fecha_desde=2026-02-01');
        $this->assertCount(2, $desde);
        $this->assertSame('Proyectores', $desde[0][5]);
        $this->assertSame(['PRTEST nuevo', 'Asunto PRTEST nuevo'], array_slice($desde[1], 0, 2));
        $this->assertSame('Zoraida Quintero', $desde[1][5]);
        $this->assertSame('2026-03-20 17:30', $desde[1][10]);

        $seleccion = $filas("ids[]={$viejo}");
        $this->assertCount(2, $seleccion);
        $this->assertSame('PRTEST viejo', $seleccion[1][0]);
    }
}
