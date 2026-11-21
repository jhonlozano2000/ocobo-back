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
        $ids = User::factory()->count(ReportesExportService::MAX_FILAS['pdf'] + 1)->create()->modelKeys();
        $query = DB::table('users')->whereIn('id', $ids);

        $servicio = new ReportesExportService;
        $servicio->validarVolumen(clone $query, 'excel'); // 1001 < 5000: pasa

        $this->expectException(ValidationException::class);
        $servicio->validarVolumen(clone $query, 'pdf');
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
