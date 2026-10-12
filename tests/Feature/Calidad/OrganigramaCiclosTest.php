<?php

namespace Tests\Feature\Calidad;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M17-02: el organigrama no admite ciclos y los recorridos de la jerarquía
 * terminan aunque la tabla ya tenga un ciclo (datos heredados).
 */
class OrganigramaCiclosTest extends TestCase
{
    use DatabaseTransactions;

    private CalidadOrganigrama $a;

    private CalidadOrganigrama $b;

    private CalidadOrganigrama $c;

    private CalidadOrganigrama $cargo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        $this->a = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => 'Dep A M17', 'cod_organico' => 'M17A']);
        $this->b = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => 'Dep B M17', 'cod_organico' => 'M17B', 'parent' => $this->a->id]);
        $this->c = CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'Ofi C M17', 'cod_organico' => 'M17C', 'parent' => $this->b->id]);
        $this->cargo = CalidadOrganigrama::create(['tipo' => 'Cargo', 'nom_organico' => 'Cargo M17', 'cod_organico' => 'M17K', 'parent' => $this->c->id]);
    }

    private function editor(): User
    {
        $permiso = 'Calidad - Organigrama -> Editar';
        Permission::findOrCreate($permiso, 'web');
        $user = User::factory()->create();
        $user->givePermissionTo($permiso);

        return $user;
    }

    private function mover(CalidadOrganigrama $nodo, int $parent)
    {
        return $this->actingAs($this->editor())->putJson('/api/calidad/organigrama/'.$nodo->id, [
            'tipo' => $nodo->tipo,
            'nom_organico' => $nodo->nom_organico,
            'cod_organico' => $nodo->cod_organico,
            'parent' => $parent,
        ]);
    }

    /** M09-03: la dependencia necesita código propio (numeración de expedientes) */
    public function test_dependencia_requiere_codigo_organico(): void
    {
        $this->actingAs($this->editor())->putJson('/api/calidad/organigrama/'.$this->b->id, [
            'tipo' => 'Dependencia', 'nom_organico' => $this->b->nom_organico, 'cod_organico' => null, 'parent' => $this->a->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('cod_organico');
    }

    public function test_rechaza_mover_un_nodo_bajo_si_mismo_o_un_descendiente(): void
    {
        $this->mover($this->a, $this->a->id)->assertStatus(422)->assertJsonValidationErrors('parent');
        $this->mover($this->a, $this->b->id)->assertStatus(422)->assertJsonValidationErrors('parent');

        $this->assertNull($this->a->fresh()->parent);
    }

    public function test_permite_mover_a_un_nodo_no_descendiente(): void
    {
        $d = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => 'Dep D M17', 'cod_organico' => 'M17D']);

        $this->mover($this->b, $d->id)->assertOk();
        $this->assertSame($d->id, (int) $this->b->fresh()->parent);
    }

    public function test_recorridos_terminan_con_un_ciclo_existente(): void
    {
        // Ciclo A -> B -> A creado directamente en la tabla (sin pasar por la validación)
        DB::table('calidad_organigrama')->where('id', $this->a->id)->update(['parent' => $this->b->id]);

        $cargo = $this->cargo->fresh();
        $this->assertCount(4, $cargo->getJerarquiaCompleta());
        $this->assertLessThanOrEqual(CalidadOrganigrama::MAX_PROFUNDIDAD, $cargo->getNivel());

        $codigos = $this->a->fresh()->getDescendientesCodigos();
        sort($codigos);
        $this->assertSame(['M17B', 'M17C', 'M17K'], $codigos);

        $this->assertTrue(CalidadOrganigrama::formaCiclo($this->c->id, $this->a->id));
    }
}
