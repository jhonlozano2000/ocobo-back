<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B16 (diseño de expedientes §3): la transferencia primaria y la secundaria terminaban las dos en
 * 'transferido'. No se distinguía un expediente del archivo central de uno del histórico, y uno ya
 * transferido al central figuraba como pendiente de transferir en las alertas de retención.
 */
class FasesExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $archivista;

    private User $jefe;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->archivista, $this->jefe] = User::factory()->count(2)->create(['estado' => 1])->all();
        $permisos = ['Transferencias -> Crear', 'Transferencias -> Aprobar', 'Expedientes -> Reabrir', 'Expedientes -> Ver', 'Expedientes -> Ver Todos'];
        foreach ($permisos as $accion) {
            $permiso = Permission::findOrCreate('Gestion de Archivo -> '.$accion, 'web');
            $this->archivista->givePermissionTo($permiso);
            $this->jefe->givePermissionTo($permiso);
        }
    }

    /** Expediente cerrado hace $aniosCerrado años, con una serie de 2 años en gestión y 3 en central. */
    private function expediente(float $aniosCerrado, array $extra = []): int
    {
        Schema::disableForeignKeyConstraints();
        $serie = DB::table('clasificacion_documental_trd')->insertGetId([
            'tipo' => 'Serie', 'cod' => 'F'.random_int(1000, 9999), 'nom' => 'Serie fases', 'user_register' => $this->archivista->id,
            'a_g' => '2', 'a_c' => '3', 'ct' => 1, 'e' => 0, 'm_d' => 0, 's' => 0,
        ]);
        $id = DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'B16-'.uniqid(), 'nombre_expediente' => 'Fases', 'anio' => 2015,
            'dependencia_id' => 1, 'serie_trd_id' => $serie, 'estado' => 'cerrado',
            'fecha_apertura' => now()->subYears(12), 'usuario_apertura_id' => $this->archivista->id,
            'fecha_cierre' => now()->subDays((int) round($aniosCerrado * 365)),
        ] + $extra);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function transferirYAprobar(int $expediente, string $tipo)
    {
        $id = $this->actingAs($this->archivista)->postJson('/api/archivo/transferencias', [
            'expediente_id' => $expediente, 'tipo' => $tipo, 'origen' => 'Origen', 'destino' => 'Destino',
            'responsable_origen_id' => $this->archivista->id, 'fecha_transferencia' => now()->toDateString(),
        ])->assertCreated()->json('data.id');

        return $this->actingAs($this->jefe)->postJson("/api/archivo/transferencias/{$id}/aprobar", ['estado' => 'aprobada'])->assertOk();
    }

    private function faseYEstado(int $expediente): array
    {
        return (array) DB::table('ofi_archivo_expedientes')->where('id', $expediente)->first(['fase', 'estado']);
    }

    public function test_la_primaria_lo_deja_en_central_y_la_secundaria_en_historico(): void
    {
        $exp = $this->expediente(6);
        $this->assertSame(['fase' => 'gestion', 'estado' => 'cerrado'], $this->faseYEstado($exp));

        // La secundaria exige que ya esté en el central
        $this->actingAs($this->archivista)->postJson('/api/archivo/transferencias', [
            'expediente_id' => $exp, 'tipo' => 'secundaria', 'origen' => 'Gestión', 'destino' => 'Histórico',
            'responsable_origen_id' => $this->archivista->id, 'fecha_transferencia' => now()->toDateString(),
        ])->assertStatus(422);

        $this->transferirYAprobar($exp, 'primaria');
        $this->assertSame(['fase' => 'central', 'estado' => 'cerrado'], $this->faseYEstado($exp));
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $exp, 'accion' => 'transferencia']);

        // En el central sigue cerrado, pero no se reabre ni vuelve a transferirse al central
        $this->actingAs($this->archivista)->postJson("/api/archivo/expedientes/{$exp}/reabrir", ['motivo_reapertura' => 'Falta un documento'])
            ->assertStatus(422);
        $this->actingAs($this->archivista)->postJson('/api/archivo/transferencias', [
            'expediente_id' => $exp, 'tipo' => 'primaria', 'origen' => 'Gestión', 'destino' => 'Central',
            'responsable_origen_id' => $this->archivista->id, 'fecha_transferencia' => now()->toDateString(),
        ])->assertStatus(422);

        $this->transferirYAprobar($exp, 'secundaria');
        $this->assertSame(['fase' => 'historico', 'estado' => 'transferido'], $this->faseYEstado($exp));

        $this->actingAs($this->archivista)->getJson('/api/archivo/expedientes?fase=historico&per_page=100')->assertOk()
            ->assertJsonFragment(['id' => $exp]);
        $this->actingAs($this->archivista)->getJson('/api/archivo/expedientes?fase=central&per_page=100')->assertOk()
            ->assertJsonMissing(['id' => $exp]);
    }

    public function test_la_bd_impide_reabrir_fuera_de_gestion_y_retroceder_de_fase(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Los triggers solo existen en MySQL.');
        }
        $exp = $this->expediente(6, ['fase' => 'central']);

        foreach ([['estado' => 'abierto'], ['fase' => 'gestion']] as $cambio) {
            try {
                DB::table('ofi_archivo_expedientes')->where('id', $exp)->update($cambio);
                $this->fail('El trigger debía impedir '.json_encode($cambio));
            } catch (QueryException $e) {
                $this->assertStringContainsString('45000', $e->getMessage());
            }
        }
        $this->assertSame(['fase' => 'central', 'estado' => 'cerrado'], $this->faseYEstado($exp));
    }

    public function test_la_alerta_de_retencion_en_gestion_omite_los_transferidos(): void
    {
        $enGestion = $this->expediente(2 - 7 / 365);
        $enCentral = $this->expediente(2 - 7 / 365, ['fase' => 'central']);

        Artisan::call('alertas:retencion-trd', ['--dias' => '7']);

        $avisados = DB::table('notifications')->where('user_id', $this->archivista->id)->where('type', 'retencion_trd')
            ->pluck('data')->map(fn ($d) => json_decode($d, true)['expediente_id'] ?? null)->all();
        $this->assertContains($enGestion, $avisados);
        $this->assertNotContains($enCentral, $avisados);
    }
}
