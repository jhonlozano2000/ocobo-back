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
            'created_at' => now(), 'updated_at' => now(),
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

    public function test_responsable_con_permiso_de_mi_bandeja_puede_ver_detalle_sin_permiso_de_ventanilla(): void
    {
        $creador = User::factory()->create(['estado' => 1]);
        $responsable = User::factory()->create(['estado' => 1]);
        $ajeno = User::factory()->create(['estado' => 1]);
        $radicado = $this->radicadoDe($creador);

        Schema::disableForeignKeyConstraints();
        $cargoId = DB::table('users_cargos')->insertGetId([
            'user_id' => $responsable->id,
            'cargo_id' => 999999,
            'fecha_inicio' => now()->toDateString(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ventanilla_radica_reci_responsa')->insert([
            'radica_reci_id' => $radicado,
            'users_cargos_id' => $cargoId,
            'custodio' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $url = "/api/ventanilla/radica-recibida/{$radicado}";
        $this->actingAs($responsable)->getJson($url)->assertForbidden();

        $permiso = Permission::findOrCreate('Mi Bandeja - Recibidos -> Ver', 'web');
        $responsable->givePermissionTo($permiso);
        $ajeno->givePermissionTo($permiso);

        $this->actingAs($responsable)->getJson($url)->assertOk()->assertJsonPath('data.id', $radicado);
        $this->actingAs($responsable)->getJson("{$url}/linea-tiempo")->assertOk();
        $this->actingAs($responsable)->getJson("{$url}/enviados")->assertOk();
        $this->actingAs($ajeno)->getJson($url)->assertNotFound();
    }
}
