<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M01-21: pase y compartir de recibidos (y sus historiales) no declaraban permiso en la ruta;
 * lo validaba un middleware en el constructor con hasPermissionTo, que da 500 si el permiso no existe.
 */
class PermisoPaseCompartirRecibidosTest extends TestCase
{
    use DatabaseTransactions;

    private function radicadoDe(User $user): int
    {
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_reci')->insertGetId([
            'num_radicado' => 'M0121-'.uniqid(), 'medio_recep_id' => 1, 'usuario_crea' => $user->id,
        ]);
        Schema::enableForeignKeyConstraints();

        return $id;
    }

    public function test_las_rutas_declaran_el_permiso(): void
    {
        foreach (['pase.registrar', 'compartir.registrar', 'pase.historial', 'compartir.historial'] as $nombre) {
            $middleware = Route::getRoutes()->getByName('radica-recibida.'.$nombre)->gatherMiddleware();

            $this->assertNotEmpty(array_filter($middleware, fn ($m) => str_starts_with($m, 'can_any:')), $nombre);
        }
    }

    public function test_el_historial_exige_permiso_aunque_tenga_acceso_al_radicado(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $radicado = $this->radicadoDe($user);

        $this->actingAs($user)->getJson("/api/ventanilla/radica-recibida/{$radicado}/pase-historial")->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('Mi Bandeja - Recibidos -> Ver', 'web'));

        $this->actingAs($user)->getJson("/api/ventanilla/radica-recibida/{$radicado}/pase-historial")->assertOk();
    }
}
