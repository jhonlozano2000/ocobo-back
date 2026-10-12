<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Services\VentanillaUnica\PqrsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M04-07: el cambio de estado aceptaba cualquier salto (Vencida → Pendiente, Respondida → Pendiente)
 * aunque el mapa de transiciones lo prohibiera, lo que permitía maquillar los indicadores.
 */
class TransicionEstadoPqrsTest extends TestCase
{
    use DatabaseTransactions;

    private function pqrs(string $estado): VentanillaPqrs
    {
        $user = User::factory()->create(['estado' => 1]);
        Schema::disableForeignKeyConstraints();
        $tipoId = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Queja']);
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0407-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $user->id,
        ]);
        $id = DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $radicado, 'tipo_pqrs_id' => $tipoId, 'fecha_vencimiento' => '2026-12-01',
            'prioridad' => 'Normal', 'fallo_judicial' => 'No', 'tiene_prorroga' => false, 'estado_tramite' => $estado,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return VentanillaPqrs::findOrFail($id);
    }

    public function test_rechaza_un_salto_no_permitido(): void
    {
        $pqrs = $this->pqrs('Vencida');

        try {
            app(PqrsService::class)->cambiarEstado($pqrs->id, 'Pendiente');
            $this->fail('Se esperaba la excepción');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Vencida', $e->getMessage());
        }

        $this->assertSame('Vencida', $pqrs->refresh()->estado_tramite);
    }

    public function test_aplica_una_transicion_permitida(): void
    {
        $pqrs = $this->pqrs('En Tramite');

        $actualizada = app(PqrsService::class)->cambiarEstado($pqrs->id, 'Respondida');

        $this->assertSame('Respondida', $actualizada->estado_tramite);
        $this->assertNotNull($actualizada->fecha_respuesta);
    }
}
