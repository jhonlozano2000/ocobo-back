<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Services\VentanillaUnica\PqrsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * M04-05: la prórroga del art. 14 de la Ley 1755 no aplica a tutelas ni a fallos judiciales.
 */
class ProrrogaPqrsTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['estado' => 1]);
    }

    private function pqrs(array $attrs = []): VentanillaPqrs
    {
        Schema::disableForeignKeyConstraints();
        $tipoId = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'Queja']);
        $radicado = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0405-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $this->user->id,
        ]);
        $id = DB::table('ventanilla_pqrs')->insertGetId($attrs + [
            'ventanilla_radica_reci_id' => $radicado, 'tipo_pqrs_id' => $tipoId, 'fecha_vencimiento' => '2026-12-01',
            'prioridad' => 'Normal', 'fallo_judicial' => 'No', 'tiene_prorroga' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return VentanillaPqrs::findOrFail($id);
    }

    public static function terminosJudiciales(): array
    {
        return [
            'tutela' => [['prioridad' => 'Tutela']],
            'fallo judicial' => [['fallo_judicial' => 'Si']],
        ];
    }

    #[DataProvider('terminosJudiciales')]
    public function test_no_se_prorroga_un_termino_judicial(array $attrs): void
    {
        $pqrs = $this->pqrs($attrs);

        try {
            app(PqrsService::class)->aplicarProrroga($pqrs->id);
            $this->fail('Se esperaba la excepción');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('tutela', $e->getMessage());
        }

        $fila = DB::table('ventanilla_pqrs')->where('id', $pqrs->id)->first();
        $this->assertSame(0, (int) $fila->tiene_prorroga);
        $this->assertSame('2026-12-01', substr($fila->fecha_vencimiento, 0, 10));
    }

    public function test_prorroga_una_queja_una_sola_vez(): void
    {
        $pqrs = $this->pqrs();
        $servicio = app(PqrsService::class);

        $prorrogada = $servicio->aplicarProrroga($pqrs->id);
        $this->assertTrue($prorrogada->tiene_prorroga);
        $this->assertTrue($prorrogada->fecha_vencimiento->gt('2026-12-01'));

        $this->expectException(\DomainException::class);
        $servicio->aplicarProrroga($pqrs->id);
    }
}
