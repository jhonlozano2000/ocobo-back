<?php

namespace Tests\Feature\Gestion;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * La vista 360 del tercero lista sus radicados con la fecha de radicación
 * (created_at) y el estado de trabajo; antes pedía columnas inexistentes
 * (fec_radicado en enviados, estado en ambas tablas) y respondía 500.
 */
class HistorialTerceroTest extends TestCase
{
    use DatabaseTransactions;

    public function test_historial_del_tercero_lista_radicados_con_fecha_y_estado(): void
    {
        $permiso = 'Gestión - Terceros -> Mostrar';
        Permission::findOrCreate($permiso, 'web');
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo($permiso);

        $tercero = DB::table('gestion_terceros')->insertGetId([
            'num_docu_nit' => 'TST-HIST-001', 'nom_razo_soci' => 'Tercero Historial', 'tipo' => 'Natural',
        ]);
        // El medio de recepción no es relevante para esta prueba
        Schema::disableForeignKeyConstraints();
        DB::table('ventanilla_radica_reci')->insert([
            'num_radicado' => 'HIST-'.uniqid(), 'asunto' => 'Entrada', 'medio_recep_id' => 1,
            'usuario_crea' => $user->id, 'tercero_id' => $tercero, 'estado_trabajo' => 'RECIBIDO',
            'created_at' => '2026-10-11 17:29:32', 'updated_at' => '2026-10-11 17:29:32',
        ]);
        Schema::enableForeignKeyConstraints();

        $respuesta = $this->actingAs($user)->getJson('/api/archivo/terceros/TST-HIST-001/historial');

        $respuesta->assertOk()
            ->assertJsonPath('data.radicados_recibidos.0.estado_trabajo', 'RECIBIDO')
            ->assertJsonPath('data.radicados_enviados', []);
        $this->assertStringStartsWith('2026-10-11', $respuesta->json('data.radicados_recibidos.0.created_at'));
    }
}
