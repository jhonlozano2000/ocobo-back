<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M08-10: la eliminación documental no verificaba TRD, retención, préstamos ni aprobador (B18: ahora va por lote).
 * M08-11: las transferencias no tenían precondiciones ni segregación de funciones.
 */
class TransferenciaEliminacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $archivista;

    private User $jefe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->archivista = User::factory()->create(['estado' => 1]);
        $this->jefe = User::factory()->create(['estado' => 1]);
        foreach (['Transferencias -> Crear', 'Transferencias -> Aprobar', 'Eliminaciones -> Ver', 'Eliminaciones -> Crear', 'Eliminaciones -> Aprobar'] as $accion) {
            $permiso = Permission::findOrCreate('Gestion de Archivo -> '.$accion, 'web');
            $this->archivista->givePermissionTo($permiso);
            $this->jefe->givePermissionTo($permiso);
        }
    }

    /**
     * Expediente con su serie TRD; $aniosCerrado = años transcurridos desde el cierre.
     */
    private function expediente(array $trd, ?int $aniosCerrado, string $estado = 'cerrado'): int
    {
        Schema::disableForeignKeyConstraints();
        $serie = DB::table('clasificacion_documental_trd')->insertGetId(array_merge([
            'tipo' => 'Serie', 'cod' => 'T'.random_int(1000, 9999), 'nom' => 'Serie prueba', 'user_register' => $this->archivista->id,
            'a_g' => '2', 'a_c' => '3', 'ct' => 0, 'e' => 0, 'm_d' => 0, 's' => 0,
        ], $trd));
        $id = DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'EXP-'.uniqid(), 'nombre_expediente' => 'Prueba', 'anio' => 2010,
            'dependencia_id' => 1, 'serie_trd_id' => $serie, 'estado' => $estado,
            'fecha_apertura' => now()->subYears(20), 'usuario_apertura_id' => $this->archivista->id,
            'fecha_cierre' => $aniosCerrado === null ? null : now()->subYears($aniosCerrado)->subDay(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    private function eliminar(int $expediente, ?int $aprobador = null)
    {
        return $this->actingAs($this->archivista)->postJson('/api/archivo/eliminaciones', [
            'expediente_id' => $expediente, 'metodo' => 'borrado_seguro',
            'responsable_ids' => [$this->archivista->id, $this->jefe->id],
            'aprobado_por_id' => $aprobador ?? $this->jefe->id,
        ]);
    }

    /** B18: la eliminación va por lote de serie (EliminacionDocumentalTest); la suelta quedó cerrada. */
    public function test_la_eliminacion_individual_quedo_cerrada(): void
    {
        $exp = $this->expediente(['e' => 1], 6);

        $this->eliminar($exp)->assertStatus(422)
            ->assertJsonFragment(['message' => 'La eliminación individual ya no está disponible: proponga el lote de la serie en Gestión de Archivo → Eliminaciones']);
        $this->assertSame(0, DB::table('ofi_archivo_eliminaciones')->where('expediente_id', $exp)->count());
        $this->assertSame('cerrado', DB::table('ofi_archivo_expedientes')->where('id', $exp)->value('estado'));
        // El historial de las eliminaciones sueltas se sigue consultando
        $this->actingAs($this->archivista)->getJson('/api/archivo/eliminaciones')->assertOk();
    }

    /**
     * B17: las precondiciones de la transferencia se prueban en LotesTransferenciaTest. Las
     * pendientes sueltas, anteriores a los lotes, se siguen decidiendo con segregación de funciones.
     */
    public function test_la_pendiente_anterior_a_los_lotes_se_aprueba_con_segregacion(): void
    {
        $exp = $this->expediente(['ct' => 1], 3);
        $id = DB::table('ofi_archivo_transferencias')->insertGetId([
            'expediente_id' => $exp, 'tipo' => 'primaria', 'origen' => 'Gestión', 'destino' => 'Central',
            'responsable_origen_id' => $this->archivista->id, 'fecha_transferencia' => now(),
            'estado' => 'pendiente', 'usuario_registro_id' => $this->archivista->id,
        ]);

        // Quien registró no aprueba; otro usuario sí
        $this->actingAs($this->archivista)->postJson("/api/archivo/transferencias/{$id}/aprobar", ['estado' => 'aprobada'])->assertStatus(422);
        $this->actingAs($this->jefe)->postJson("/api/archivo/transferencias/{$id}/aprobar", ['estado' => 'aprobada'])->assertOk();
        // B16: la primaria lo deja cerrado en el archivo central
        $this->assertSame(['fase' => 'central', 'estado' => 'cerrado'], (array) DB::table('ofi_archivo_expedientes')->where('id', $exp)->first(['fase', 'estado']));
    }
}
