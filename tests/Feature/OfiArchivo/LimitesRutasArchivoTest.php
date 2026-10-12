<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M08-17: las rutas de Gestión de Archivo no tenían throttle y la carga de archivos de un
 * expediente no limitaba la cantidad (50 MB × N).
 */
class LimitesRutasArchivoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_todas_las_rutas_de_archivo_tienen_throttle(): void
    {
        $sinLimite = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_contains($r->getActionName(), '\\OfiArchivo\\'))
            ->reject(fn ($r) => collect($r->gatherMiddleware())->contains(fn ($m) => str_starts_with($m, 'throttle:')))
            ->map(fn ($r) => $r->methods()[0].' '.$r->uri())
            ->values()
            ->all();

        $this->assertSame([], $sinLimite);
    }

    public function test_la_carga_de_archivos_tiene_tope_de_cantidad(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> Incorporar', 'web'));
        $archivos = array_map(fn ($i) => UploadedFile::fake()->create("doc{$i}.pdf", 1), range(1, 21));

        $this->actingAs($user)->postJson('/api/archivo/expedientes/1/archivos', ['archivos' => $archivos, 'tipo' => 'expediente_completo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivos');
    }
}
