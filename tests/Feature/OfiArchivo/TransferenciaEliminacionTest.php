<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M08-10: la eliminación documental no verificaba TRD, retención, préstamos ni aprobador.
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
        foreach (['Transferencias -> Crear', 'Transferencias -> Aprobar', 'Eliminaciones -> Crear', 'Eliminaciones -> Aprobar'] as $accion) {
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

    private function transferir(int $expediente, string $tipo = 'primaria')
    {
        return $this->actingAs($this->archivista)->postJson('/api/archivo/transferencias', [
            'expediente_id' => $expediente, 'tipo' => $tipo, 'origen' => 'Gestión', 'destino' => 'Central',
            'responsable_origen_id' => $this->archivista->id, 'fecha_transferencia' => now()->toDateString(),
        ]);
    }

    public function test_eliminacion_exige_disposicion_retencion_y_aprobador_valido(): void
    {
        // Conservación total: no se elimina
        $this->eliminar($this->expediente(['ct' => 1], 10))->assertStatus(422);
        // Retención (2 + 3 años) no cumplida
        $this->eliminar($this->expediente(['e' => 1], 4))->assertStatus(422);

        $exp = $this->expediente(['e' => 1], 6);
        // El registrador no se aprueba a sí mismo
        $this->eliminar($exp, $this->archivista->id)->assertStatus(422);
        // Aprobador sin permiso
        $this->eliminar($exp, User::factory()->create(['estado' => 1])->id)->assertStatus(422);

        // Préstamo sin devolver
        DB::table('ofi_archivo_prestamos')->insert([
            'expediente_id' => $exp, 'solicitante_id' => $this->jefe->id, 'fecha_prestamo' => now(),
            'fecha_devolucion_esperada' => now()->addDay(), 'estado' => 'prestado', 'usuario_registro_id' => $this->jefe->id,
        ]);
        $this->eliminar($exp)->assertStatus(422);
        $this->assertSame(0, DB::table('ofi_archivo_eliminaciones')->where('expediente_id', $exp)->count());
        $this->assertSame('cerrado', DB::table('ofi_archivo_expedientes')->where('id', $exp)->value('estado'));
    }

    public function test_eliminacion_valida_marca_expediente_y_no_se_repite(): void
    {
        $exp = $this->expediente(['s' => 1], 6);

        $this->eliminar($exp)->assertCreated();
        $this->assertSame('eliminado', DB::table('ofi_archivo_expedientes')->where('id', $exp)->value('estado'));
        $this->eliminar($exp)->assertStatus(422);
    }

    public function test_transferencia_exige_precondiciones_y_segregacion(): void
    {
        // Abierto o con retención en gestión (2 años) sin cumplir
        $this->transferir($this->expediente(['e' => 1], 3, 'abierto'))->assertStatus(422);
        $this->transferir($this->expediente(['e' => 1], 1))->assertStatus(422);
        // Secundaria de un expediente aún en gestión
        $this->transferir($this->expediente(['ct' => 1], 10), 'secundaria')->assertStatus(422);

        $exp = $this->expediente(['ct' => 1], 3);
        $id = $this->transferir($exp)->assertCreated()->json('data.id');
        // Una sola pendiente por expediente
        $this->transferir($exp)->assertStatus(422);

        // Quien registró no aprueba; otro usuario sí
        $this->actingAs($this->archivista)->postJson("/api/archivo/transferencias/{$id}/aprobar", ['estado' => 'aprobada'])->assertStatus(422);
        $this->actingAs($this->jefe)->postJson("/api/archivo/transferencias/{$id}/aprobar", ['estado' => 'aprobada'])->assertOk();
        // B16: la primaria lo deja cerrado en el archivo central
        $this->assertSame(['fase' => 'central', 'estado' => 'cerrado'], (array) DB::table('ofi_archivo_expedientes')->where('id', $exp)->first(['fase', 'estado']));
    }
}
