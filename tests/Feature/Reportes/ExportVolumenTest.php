<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Services\ReportesExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M04-11: los exports de PQRS / Recibidas / Enviadas hacían `->get()` sin tope ni cola;
 * un export sin filtros cargaba toda la tabla en memoria y bloqueaba el worker.
 */
class ExportVolumenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rechaza_exportaciones_por_encima_del_tope_del_formato(): void
    {
        DB::statement('CREATE TEMPORARY TABLE export_volumen_tmp (id INT)');
        DB::table('export_volumen_tmp')->insert(array_fill(0, ReportesExportService::MAX_FILAS['pdf'] + 1, ['id' => 1]));

        $servicio = new ReportesExportService;
        $servicio->validarVolumen(DB::table('export_volumen_tmp'), 'excel'); // 1001 < 5000: pasa

        $this->expectException(ValidationException::class);
        $servicio->validarVolumen(DB::table('export_volumen_tmp'), 'pdf');
    }

    public function test_el_export_de_pqrs_aplica_el_tope(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('Radicar -> PQRSF -> Listar', 'web'));

        $this->mock(ReportesExportService::class, function ($mock) {
            $mock->shouldReceive('validarVolumen')->once()
                ->withArgs(fn ($query, $formato) => $query->getModel() instanceof VentanillaPqrs && $formato === 'csv')
                ->andThrow(ValidationException::withMessages(['format' => 'demasiados']));
        });

        $this->actingAs($user)->getJson('/api/ventanilla/pqrs/export?format=csv')
            ->assertUnprocessable()->assertJsonValidationErrors('format');
    }
}
