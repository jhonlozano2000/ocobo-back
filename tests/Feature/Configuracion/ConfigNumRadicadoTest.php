<?php

namespace Tests\Feature\Configuracion;

use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M15-03: el endpoint de numeración guardaba en 'formato_num_radicado', clave que
 * ningún generador lee; el cambio no tenía efecto sobre los radicados nuevos.
 */
class ConfigNumRadicadoTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): User
    {
        $user = User::factory()->create(['estado' => 1]);
        foreach (['Listar', 'Editar'] as $accion) {
            $permiso = 'Config - Otras configuraciones -> '.$accion;
            Permission::findOrCreate($permiso, 'web');
            $user->givePermissionTo($permiso);
        }

        return $user;
    }

    public function test_el_formato_guardado_es_el_que_usa_la_numeracion(): void
    {
        $user = $this->usuario();

        $this->actingAs($user)->putJson('/api/config/config-num-radicado', ['formato' => 'RAD-YYYY-######'])
            ->assertOk()
            ->assertJsonPath('data.formato', 'RAD-YYYY-######');

        // Clave que leen RadicadoConsecutivoService y la radicación por correo
        $this->assertSame('RAD-YYYY-######', ConfigVarias::getValor('formato_num_radicado_reci'));

        $this->actingAs($user)->getJson('/api/config/config-num-radicado')
            ->assertOk()
            ->assertJsonPath('data.formato', 'RAD-YYYY-######');
    }
}
