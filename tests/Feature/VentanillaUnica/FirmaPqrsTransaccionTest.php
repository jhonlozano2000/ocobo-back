<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Services\VentanillaUnica\PqrsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M04-04: la firma de la PQRS y su evento de auditoría se guardan juntos o no se guarda ninguno.
 */
class FirmaPqrsTransaccionTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['estado' => 1]);
    }

    private function pqrs(): VentanillaPqrs
    {
        Schema::disableForeignKeyConstraints();
        $tipoId = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Petición']);
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0404-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
        ]);
        $id = DB::table('ventanilla_pqrs')->insertGetId([
            'ventanilla_radica_reci_id' => $radicado, 'tipo_pqrs_id' => $tipoId, 'fecha_vencimiento' => '2026-12-31',
            'estado_firma' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return VentanillaPqrs::findOrFail($id);
    }

    private function eventos(int $pqrsId): int
    {
        return FirmaEvento::where('documentable_type', VentanillaPqrs::class)->where('documentable_id', $pqrsId)->count();
    }

    public function test_si_falla_el_evento_de_auditoria_la_pqrs_no_queda_firmada(): void
    {
        $pqrs = $this->pqrs();
        FirmaEvento::creating(fn () => throw new \RuntimeException('fallo simulado'));

        try {
            app(PqrsService::class)->guardarFirma($pqrs, ['firma_digital' => 'data:image/png;base64,AAA'], $this->user);
            $this->fail('Se esperaba la excepción');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo simulado', $e->getMessage());
        }

        $this->assertSame('pendiente', DB::table('ventanilla_pqrs')->where('id', $pqrs->id)->value('estado_firma'));
        $this->assertNull(DB::table('ventanilla_pqrs')->where('id', $pqrs->id)->value('firma_digital'));
    }

    public function test_firma_una_sola_vez(): void
    {
        $pqrs = $this->pqrs();
        $servicio = app(PqrsService::class);

        $firmada = $servicio->guardarFirma($pqrs, ['firma_digital' => 'data:image/png;base64,AAA'], $this->user);
        $this->assertSame('firmada', $firmada->estado_firma);
        $this->assertSame(1, $this->eventos($pqrs->id));
        $this->assertSame(64, strlen(FirmaEvento::where('documentable_id', $pqrs->id)->where('documentable_type', VentanillaPqrs::class)->value('hash_original')));

        // Instancia vieja (estado 'pendiente' en memoria), como en dos solicitudes simultáneas
        $this->expectException(\DomainException::class);
        try {
            $servicio->guardarFirma($pqrs, ['firma_digital' => 'data:image/png;base64,BBB'], $this->user);
        } finally {
            $this->assertSame(1, $this->eventos($pqrs->id));
        }
    }
}
