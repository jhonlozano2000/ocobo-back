<?php

namespace Tests\Feature\MiBandeja;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * La TRD del grupo colaborativo es obligatoria: quien crea grupos (rol «Mi espacio», sin 'TRD -> Listar') debe
 * poder elegir la dependencia y ver su árbol de TRD.
 */
class ClasificacionGrupoTest extends TestCase
{
    use DatabaseTransactions;

    private const URL_GRUPOS = '/api/mi-bandeja/grupos-colaborativos';

    private CalidadOrganigrama $dependencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->dependencia = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => 'Gestión Grupo TRD', 'cod_organico' => 'GTRD1']);
    }

    private function creadorDeGrupos(): User
    {
        $cargo = CalidadOrganigrama::create([
            'tipo' => 'Cargo', 'nom_organico' => 'Profesional TRD', 'cod_organico' => 'GTRD2', 'parent' => $this->dependencia->id,
        ]);
        $user = User::factory()->create(['estado' => 1]);
        DB::table('users_cargos')->insert([
            'user_id' => $user->id, 'cargo_id' => $cargo->id, 'fecha_inicio' => now()->toDateString(), 'estado' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user->givePermissionTo(Permission::findOrCreate('Mi Bandeja - Grupos Colaborativos -> Crear', 'web'));

        return $user;
    }

    public function test_quien_crea_grupos_elige_la_dependencia_y_ve_su_trd(): void
    {
        $user = $this->creadorDeGrupos();

        $respuesta = $this->actingAs($user)->getJson(self::URL_GRUPOS.'/dependencias-trd')->assertOk();
        $this->assertSame($this->dependencia->id, $respuesta->json('data.mi_dependencia_id'));
        $this->assertContains($this->dependencia->id, array_column($respuesta->json('data.dependencias'), 'id'));

        $this->getJson("/api/clasifica-documental/trd/dependencia/{$this->dependencia->id}")->assertOk();
    }

    public function test_sin_permiso_de_crear_grupos_ni_de_trd_no_ve_el_arbol(): void
    {
        $user = User::factory()->create(['estado' => 1]);

        $this->actingAs($user)->getJson(self::URL_GRUPOS.'/dependencias-trd')->assertForbidden();
        $this->getJson("/api/clasifica-documental/trd/dependencia/{$this->dependencia->id}")->assertForbidden();
    }
}
