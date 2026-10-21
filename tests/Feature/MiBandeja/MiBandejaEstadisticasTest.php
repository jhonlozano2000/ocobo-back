<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 19.10/19.13/19.15/19.21: las tarjetas de Mi Bandeja cuentan los estados que asigna
 * calcularEstadoTrabajo() (RECIBIDO / EN_PROCESO / TRAMITADO), no PENDIENTE ni BORRADOR,
 * que nunca se asignan. Un radicado tramitado cuenta como finalizado y ya no vence.
 */
class MiBandejaEstadisticasTest extends TestCase
{
    use DatabaseTransactions;

    private const ESTADOS = ['RECIBIDO', null, 'EN_PROCESO', 'TRAMITADO', 'ANULADO'];

    private User $user;

    private int $userCargoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Enviados', 'Internos', 'Recibidos'] as $bandeja) {
            $this->user->givePermissionTo(Permission::findOrCreate("Mi Bandeja - {$bandeja} -> Ver", 'web'));
        }

        Schema::disableForeignKeyConstraints();
        $this->userCargoId = DB::table('users_cargos')->insertGetId([
            'user_id' => $this->user->id, 'cargo_id' => 999999, 'fecha_inicio' => now()->toDateString(), 'estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function estadisticas(string $bandeja): array
    {
        return $this->actingAs($this->user)->getJson("/api/mi-bandeja/{$bandeja}/mis-radicados/estadisticas")
            ->assertOk()->json('data');
    }

    public function test_enviados_cuentan_los_estados_reales(): void
    {
        foreach (self::ESTADOS as $i => $estado) {
            $id = DB::table('ventanilla_radica_enviados')->insertGetId([
                'num_radicado' => "M19E-{$i}-".uniqid(), 'clasifica_documen_id' => 999999, 'tercero_id' => 999999, 'medio_enviado_id' => 1, 'tipo_respuesta_id' => 1, 'asunto' => 'Tarjetas', 'usuario_crea' => $this->user->id,
                'estado_trabajo' => $estado, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ventanilla_radica_enviados_responsable')->insert([
                'radica_enviado_id' => $id, 'users_cargos_id' => $this->userCargoId, 'custodio' => 1, 'created_at' => now(),
            ]);
        }

        $this->assertSame(
            ['total' => 5, 'pendiente' => 2, 'enProceso' => 1, 'finalizado' => 1],
            $this->estadisticas('enviados')
        );
    }

    public function test_internos_cuentan_los_estados_reales(): void
    {
        foreach (self::ESTADOS as $i => $estado) {
            $id = DB::table('ventanilla_radica_internos')->insertGetId([
                'num_radicado' => "M19I-{$i}-".uniqid(), 'clasifica_documen_id' => 999999, 'asunto' => 'Tarjetas',
                'usuario_crea' => $this->user->id, 'estado_trabajo' => $estado, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ventanilla_radica_internos_responsa')->insert([
                'radica_interno_id' => $id, 'users_cargos_id' => $this->userCargoId, 'custodio' => 1, 'created_at' => now(),
            ]);
        }

        $this->assertSame(
            ['total' => 5, 'borrador' => 2, 'enProceso' => 1, 'finalizado' => 1],
            $this->estadisticas('internos')
        );
    }

    public function test_recibidos_tramitados_no_son_vigentes_ni_vencidos(): void
    {
        $vencida = now()->subMonth()->toDateString();
        foreach (self::ESTADOS as $i => $estado) {
            $id = DB::table('ventanilla_radica_reci')->insertGetId([
                'num_radicado' => "M19R-{$i}-".uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
                'estado_trabajo' => $estado, 'fec_venci' => in_array($estado, ['RECIBIDO', 'TRAMITADO'], true) ? $vencida : null,
                'archivo_digital' => 'x.pdf', 'created_at' => now(),
            ]);
            DB::table('ventanilla_radica_reci_responsa')->insert([
                'radica_reci_id' => $id, 'users_cargos_id' => $this->userCargoId, 'custodio' => 1,
            ]);
        }

        $stats = $this->estadisticas('recibidos');

        $this->assertSame(5, $stats['total']);
        $this->assertSame(1, $stats['finalizado']);
        // Solo el RECIBIDO está vencido; el TRAMITADO con la misma fecha ya se cerró
        $this->assertSame(1, $stats['vencido']);
        // Vigentes: el null y el EN_PROCESO (fuera quedan vencido, tramitado y anulado)
        $this->assertSame(2, $stats['vigentes']);
    }
}
