<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mi bandeja → Internos expone `puede_firmar` por fila con las reglas del flujo de firma:
 * PDF cargado, firmante designado (firmante o responsable) y firma aún pendiente.
 */
class MiBandejaInternosPuedeFirmarTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/internos/mis-radicados';

    protected User $user;

    protected int $userCargoId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ventanilla_radica_internos_responsa')) {
            $this->markTestSkipped('BD de pruebas sin la tabla ventanilla_radica_internos_responsa.');
        }

        $permiso = 'Mi Bandeja - Internos -> Ver';
        Permission::findOrCreate($permiso, 'web');
        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo($permiso);

        Schema::disableForeignKeyConstraints();
        $this->userCargoId = DB::table('users_cargos')->insertGetId([
            'user_id' => $this->user->id,
            'cargo_id' => 999999,
            'fecha_inicio' => now()->toDateString(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function interno(string $num, ?string $archivo, ?bool $firmado): int
    {
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => $num,
            'clasifica_documen_id' => 999999,
            'asunto' => 'Prueba puede_firmar',
            'usuario_crea' => $this->user->id,
            'archivo_digital' => $archivo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ventanilla_radica_internos_responsa')->insert([
            'radica_interno_id' => $id,
            'users_cargos_id' => $this->userCargoId,
            'custodio' => 1,
            'created_at' => now(),
        ]);
        if ($firmado !== null) {
            DB::table('ventanilla_radica_internos_firmantes')->insert([
                'radica_interno_id' => $id,
                'users_id' => $this->user->id,
                'firmado' => $firmado,
                'created_at' => now(),
            ]);
        }

        return $id;
    }

    public function test_puede_firmar_refleja_pdf_designacion_y_firma_pendiente(): void
    {
        $esperado = [
            $this->interno('PF-TEST-PENDIENTE', 'pf/pendiente.pdf', false) => true,
            $this->interno('PF-TEST-FIRMADO', 'pf/firmado.pdf', true) => false,
            $this->interno('PF-TEST-SINPDF', null, false) => false,
            $this->interno('PF-TEST-RESPONSABLE', 'pf/responsable.pdf', null) => true,
        ];

        $filas = collect($this->actingAs($this->user)->getJson(self::URL)->assertOk()->json('data'))
            ->keyBy('id');

        foreach ($esperado as $id => $puede) {
            $this->assertSame($puede, $filas[$id]['puede_firmar'], "radicado {$filas[$id]['num_radicado']}");
        }
    }
}
