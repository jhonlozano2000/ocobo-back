<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M08-12: Préstamos de archivo: expedientes cerrados, transferidos o abiertos.
 * Prevenir doble préstamo activo y permitir devolver préstamos vencidos.
 */
class PrestamoExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $archivista;

    private User $solicitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->archivista = User::factory()->create(['estado' => 1]);
        $this->solicitante = User::factory()->create(['estado' => 1]);

        foreach (['Prestamos -> Crear', 'Prestamos -> Ver', 'Prestamos -> Devolver'] as $accion) {
            $permiso = Permission::findOrCreate('Gestion de Archivo -> '.$accion, 'web');
            $this->archivista->givePermissionTo($permiso);
        }
    }

    private function crearExpediente(string $estado = 'cerrado'): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'EXP-'.uniqid(),
            'nombre_expediente' => 'Expediente Prueba Prestamo',
            'anio' => 2024,
            'dependencia_id' => 1,
            'serie_trd_id' => 1,
            'estado' => $estado,
            'fecha_apertura' => now()->subYears(2),
            'usuario_apertura_id' => $this->archivista->id,
            'fecha_cierre' => $estado === 'abierto' ? null : now()->subYear(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    public function test_permite_prestar_expediente_cerrado_o_transferido(): void
    {
        $cerradoId = $this->crearExpediente('cerrado');
        $transferidoId = $this->crearExpediente('transferido');

        $payloadCerrado = [
            'expediente_id' => $cerradoId,
            'solicitante_id' => $this->solicitante->id,
            'fecha_devolucion_esperada' => now()->addDays(5)->toDateString(),
        ];

        $res1 = $this->actingAs($this->archivista)->postJson('/api/archivo/prestamos', $payloadCerrado);
        $res1->assertStatus(201);
        $this->assertDatabaseHas('ofi_archivo_prestamos', [
            'expediente_id' => $cerradoId,
            'estado' => 'prestado',
        ]);

        $payloadTransferido = [
            'expediente_id' => $transferidoId,
            'solicitante_id' => $this->solicitante->id,
            'fecha_devolucion_esperada' => now()->addDays(5)->toDateString(),
        ];

        $res2 = $this->actingAs($this->archivista)->postJson('/api/archivo/prestamos', $payloadTransferido);
        $res2->assertStatus(201);
        $this->assertDatabaseHas('ofi_archivo_prestamos', [
            'expediente_id' => $transferidoId,
            'estado' => 'prestado',
        ]);
    }

    public function test_rechaza_prestar_expediente_eliminado(): void
    {
        $eliminadoId = $this->crearExpediente('eliminado');

        $res = $this->actingAs($this->archivista)->postJson('/api/archivo/prestamos', [
            'expediente_id' => $eliminadoId,
            'solicitante_id' => $this->solicitante->id,
            'fecha_devolucion_esperada' => now()->addDays(5)->toDateString(),
        ]);

        $res->assertStatus(422);
    }

    public function test_rechaza_doble_prestamo_activo(): void
    {
        $expId = $this->crearExpediente('cerrado');

        $payload = [
            'expediente_id' => $expId,
            'solicitante_id' => $this->solicitante->id,
            'fecha_devolucion_esperada' => now()->addDays(5)->toDateString(),
        ];

        $this->actingAs($this->archivista)->postJson('/api/archivo/prestamos', $payload)->assertStatus(201);

        // Segundo intento debe ser rechazado
        $res2 = $this->actingAs($this->archivista)->postJson('/api/archivo/prestamos', $payload);
        $res2->assertStatus(422);
        $this->assertStringContainsString('ya se encuentra en préstamo activo', $res2->json('message'));
    }

    public function test_permite_devolver_prestamo_vencido(): void
    {
        $expId = $this->crearExpediente('cerrado');

        $prestamo = OfiArchivoPrestamo::create([
            'expediente_id' => $expId,
            'solicitante_id' => $this->solicitante->id,
            'fecha_prestamo' => now()->subDays(10),
            'fecha_devolucion_esperada' => now()->subDays(2),
            'estado' => 'vencido',
            'usuario_registro_id' => $this->archivista->id,
        ]);

        $res = $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$prestamo->id}/devolver");
        $res->assertStatus(200);

        $this->assertDatabaseHas('ofi_archivo_prestamos', [
            'id' => $prestamo->id,
            'estado' => 'devuelto',
        ]);
    }
}
