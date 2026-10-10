<?php

namespace Tests\Feature\MiBandeja;

use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M07-01: un usuario ajeno al grupo no puede modificar, retirar ni marcar firmantes,
 * y la firma solo se registra mediante el flujo de firma (no por update).
 */
class GrupoFirmantesAutorizacionTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> ';

    protected User $creador;

    protected User $firmanteUser;

    protected User $ajeno;

    protected MiBandejaTemp $grupo;

    protected MiBandejaTempGrupoFirmante $firmante;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Ver', 'Gestionar Miembros'] as $p) {
            Permission::findOrCreate(self::PERM.$p, 'web');
        }

        $this->creador = User::factory()->create(['estado' => 1]);
        $this->firmanteUser = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);

        foreach ([$this->creador, $this->firmanteUser, $this->ajeno] as $u) {
            $u->givePermissionTo([self::PERM.'Ver', self::PERM.'Gestionar Miembros']);
        }

        // El radicado asociado no es relevante para estas pruebas
        Schema::disableForeignKeyConstraints();
        $this->grupo = MiBandejaTemp::create([
            'nombre' => 'Grupo M07',
            'asunto' => 'Prueba M07-01',
            'radicado_id' => 999999,
            'radicado_tipo' => 'recibido',
            'estado' => 'en_curso',
            'estado_grupo' => 'activo',
            'usua_crea_id' => $this->creador->id,
        ]);
        Schema::enableForeignKeyConstraints();

        $this->firmante = MiBandejaTempGrupoFirmante::create([
            'grupo_id' => $this->grupo->id,
            'user_id' => $this->firmanteUser->id,
            'orden_firma' => 1,
            'estado_tarea' => 'pendiente',
        ]);
    }

    private function url(string $sufijo = ''): string
    {
        return "/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/firmantes/{$this->firmante->id}{$sufijo}";
    }

    public function test_usuario_ajeno_no_puede_operar_firmantes_del_grupo(): void
    {
        $this->actingAs($this->ajeno)->putJson($this->url(), ['orden_firma' => 2])->assertForbidden();
        $this->actingAs($this->ajeno)->postJson($this->url('/marcar-terminado'))->assertForbidden();
        $this->actingAs($this->ajeno)->deleteJson($this->url())->assertForbidden();

        $this->assertNull($this->firmante->fresh()->fechor_terminado);
    }

    public function test_update_no_permite_marcar_la_firma(): void
    {
        $this->actingAs($this->creador)
            ->putJson($this->url(), ['orden_firma' => 2, 'fechor_firmado' => now()->toDateTimeString(), 'fechor_terminado' => now()->toDateTimeString()])
            ->assertOk();

        $firmante = $this->firmante->fresh();
        $this->assertSame(2, $firmante->orden_firma);
        $this->assertNull($firmante->fechor_firmado);
        $this->assertNull($firmante->fechor_terminado);
    }

    public function test_solo_el_titular_marca_su_tarea_terminada(): void
    {
        $this->actingAs($this->creador)->postJson($this->url('/marcar-terminado'))->assertForbidden();
        $this->actingAs($this->firmanteUser)->postJson($this->url('/marcar-terminado'))->assertOk();

        $this->assertNotNull($this->firmante->fresh()->fechor_terminado);
    }

    public function test_revisores_exigen_pertenencia_y_titularidad(): void
    {
        $revisor = MiBandejaTempGrupoRevisor::create([
            'grupo_id' => $this->grupo->id,
            'user_id' => $this->firmanteUser->id,
            'estado_tarea' => 'pendiente',
        ]);
        $url = "/api/mi-bandeja/grupos-colaborativos/{$this->grupo->id}/revisores/{$revisor->id}";

        $this->actingAs($this->ajeno)->putJson($url, ['fechor_terminado' => now()->toDateTimeString()])->assertForbidden();
        $this->actingAs($this->ajeno)->deleteJson($url)->assertForbidden();
        $this->actingAs($this->creador)->putJson($url, ['fechor_terminado' => now()->toDateTimeString()])->assertOk();
        $this->assertNull($revisor->fresh()->fechor_terminado);

        $this->actingAs($this->creador)->postJson($url.'/marcar-terminado')->assertForbidden();
        $this->actingAs($this->firmanteUser)->postJson($url.'/marcar-terminado')->assertOk();
    }

    public function test_no_se_retira_ni_modifica_un_firmante_que_ya_firmo(): void
    {
        MiBandejaTempGrupoFirmante::create(['grupo_id' => $this->grupo->id, 'user_id' => $this->creador->id, 'orden_firma' => 2]);
        $this->firmante->update(['fechor_firmado' => now()]);

        $this->actingAs($this->creador)->deleteJson($this->url())->assertStatus(422);
        $this->actingAs($this->creador)->putJson($this->url(), ['orden_firma' => 3])->assertStatus(422);
    }
}
