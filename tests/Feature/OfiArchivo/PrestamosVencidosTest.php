<?php

namespace Tests\Feature\OfiArchivo;

use App\Console\Commands\MarcarPrestamosVencidosCommand;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B15 (diseño de expedientes §3.10): los préstamos no devueltos a tiempo nunca pasaban a
 * "vencido"; ningún proceso los marcaba ni avisaba a nadie.
 */
class PrestamosVencidosTest extends TestCase
{
    use DatabaseTransactions;

    private User $archivista;

    private User $solicitante;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->archivista, $this->solicitante] = User::factory()->count(2)->create(['estado' => 1])->all();
    }

    private function prestamo(string $devolucion, string $estado = 'prestado'): OfiArchivoPrestamo
    {
        Schema::disableForeignKeyConstraints();
        $expediente = DB::table('ofi_archivo_expedientes')->insertGetId([
            'numero_expediente' => 'B15-'.uniqid(), 'nombre_expediente' => 'Contrato', 'anio' => 2025,
            'dependencia_id' => 1, 'serie_trd_id' => 1, 'estado' => 'cerrado',
            'fecha_apertura' => now()->subYear(), 'fecha_cierre' => now()->subMonth(), 'usuario_apertura_id' => $this->archivista->id,
        ]);
        Schema::enableForeignKeyConstraints();

        return OfiArchivoPrestamo::create([
            'expediente_id' => $expediente, 'solicitante_id' => $this->solicitante->id, 'usuario_registro_id' => $this->archivista->id,
            'fecha_prestamo' => now()->subDays(10), 'fecha_devolucion_esperada' => $devolucion, 'estado' => $estado,
        ]);
    }

    public function test_marca_los_vencidos_avisa_y_deja_el_evento(): void
    {
        $vencido = $this->prestamo(now()->subDay()->toDateTimeString());
        $enPlazo = $this->prestamo(now()->addDays(3)->toDateTimeString());
        $devuelto = $this->prestamo(now()->subDays(5)->toDateTimeString(), 'devuelto');

        // Antes de que corra el job, el préstamo vencido ya cuenta como vencido y no como activo
        $this->assertSame([$vencido->id], OfiArchivoPrestamo::vencidos()->whereKey([$vencido->id, $enPlazo->id, $devuelto->id])->pluck('id')->all());
        $this->assertSame([$enPlazo->id], OfiArchivoPrestamo::activos()->whereKey([$vencido->id, $enPlazo->id, $devuelto->id])->pluck('id')->all());
        $this->artisan('archivo:marcar-prestamos-vencidos')->assertSuccessful();

        $this->assertSame('vencido', $vencido->fresh()->estado);
        $this->assertSame('prestado', $enPlazo->fresh()->estado);
        $this->assertSame('devuelto', $devuelto->fresh()->estado);

        // El conteo de vencidos no cambia por marcarlos, y los activos son solo los que están en plazo
        $ids = [$vencido->id, $enPlazo->id, $devuelto->id];
        $this->assertSame([$vencido->id], OfiArchivoPrestamo::vencidos()->whereKey($ids)->pluck('id')->all());
        $this->assertSame([$enPlazo->id], OfiArchivoPrestamo::activos()->whereKey($ids)->pluck('id')->all());

        foreach ([$this->solicitante, $this->archivista] as $user) {
            $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => MarcarPrestamosVencidosCommand::TYPE]);
        }
        $this->assertSame(2, DB::table('notifications')->where('type', MarcarPrestamosVencidosCommand::TYPE)
            ->whereIn('user_id', [$this->solicitante->id, $this->archivista->id])->count());
        $this->assertDatabaseHas('ofi_archivo_expediente_eventos', ['expediente_id' => $vencido->expediente_id, 'accion' => 'prestamo_vencido']);

        // Una segunda ejecución no repite el aviso
        $this->artisan('archivo:marcar-prestamos-vencidos')->assertSuccessful();
        $this->assertSame(2, DB::table('notifications')->where('type', MarcarPrestamosVencidosCommand::TYPE)
            ->whereIn('user_id', [$this->solicitante->id, $this->archivista->id])->count());
    }

    public function test_dry_run_no_cambia_nada(): void
    {
        $vencido = $this->prestamo(now()->subDay()->toDateTimeString());

        $this->artisan('archivo:marcar-prestamos-vencidos', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('prestado', $vencido->fresh()->estado);
        $this->assertDatabaseMissing('notifications', ['user_id' => $this->solicitante->id, 'type' => MarcarPrestamosVencidosCommand::TYPE]);
    }

    public function test_el_vencido_se_puede_devolver_y_sale_de_los_vencidos(): void
    {
        $permiso = Permission::findOrCreate('Gestion de Archivo -> Prestamos -> Devolver', 'web');
        $this->archivista->givePermissionTo($permiso);
        $vencido = $this->prestamo(now()->subDay()->toDateTimeString());
        $this->artisan('archivo:marcar-prestamos-vencidos')->assertSuccessful();

        $this->actingAs($this->archivista)->postJson("/api/archivo/prestamos/{$vencido->id}/devolver")->assertOk();

        $this->assertSame('devuelto', $vencido->fresh()->estado);
        $this->assertSame(0, OfiArchivoPrestamo::vencidos()->whereKey($vencido->id)->count());
    }
}
