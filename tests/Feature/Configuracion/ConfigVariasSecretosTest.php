<?php

namespace Tests\Feature\Configuracion;

use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M15-01 / M15-02 / M05-02 / M05-03: config-varias y listas no exponen
 * credenciales ni catálogos completos a usuarios sin permiso.
 */
class ConfigVariasSecretosTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIX = '/api/config';

    private const PERM_LISTAR = 'Config - Otras configuraciones -> Listar';

    private const PERM_EDITAR = 'Config - Otras configuraciones -> Editar';

    protected User $funcionario;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([self::PERM_LISTAR, self::PERM_EDITAR, 'Config - Listas -> Listar', 'Config - Listas -> Mostrar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $this->funcionario = User::factory()->create(['estado' => 1]);
        ConfigVarias::setValor('correo_password', 'secreto-de-prueba');
    }

    public function test_password_se_guarda_cifrada(): void
    {
        $crudo = DB::table('config_varias')->where('clave', 'correo_password')->value('valor');

        $this->assertNotSame('secreto-de-prueba', $crudo);
        $this->assertSame('secreto-de-prueba', decrypt($crudo));
    }

    public function test_listado_completo_exige_permiso(): void
    {
        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/config-varias')->assertForbidden();
    }

    public function test_listado_con_permiso_no_devuelve_el_valor_secreto(): void
    {
        $this->funcionario->givePermissionTo(self::PERM_LISTAR);

        $response = $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/config-varias?per_page=1000')->assertOk();

        $this->assertStringNotContainsString('secreto-de-prueba', $response->getContent());
        $crudo = DB::table('config_varias')->where('clave', 'correo_password')->value('valor');
        $this->assertStringNotContainsString($crudo, $response->getContent());
    }

    public function test_publicas_sin_permiso_excluye_credenciales_de_correo(): void
    {
        $response = $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/config-varias/publicas')->assertOk();

        $claves = collect($response->json('data'))->pluck('clave');
        $this->assertFalse($claves->contains(fn ($c) => str_starts_with($c, 'correo_')));
    }

    public function test_actualizar_con_password_vacia_conserva_la_actual(): void
    {
        $this->funcionario->givePermissionTo(self::PERM_EDITAR);

        $this->actingAs($this->funcionario)
            ->postJson(self::PREFIX.'/config-varias/batch', ['configs' => [['clave' => 'correo_password', 'valor' => '']]])
            ->assertOk();

        $this->assertSame('secreto-de-prueba', decrypt(ConfigVarias::getValor('correo_password')));
    }

    public function test_catalogo_de_lista_disponible_sin_permiso_y_listas_protegidas(): void
    {
        $lista = ConfigLista::create(['cod' => 'TST_M15', 'nombre' => 'Lista prueba', 'estado' => true]);
        $lista->detalles()->create(['codigo' => 'A', 'nombre' => 'Opción A', 'estado' => true]);
        $lista->detalles()->create(['codigo' => 'B', 'nombre' => 'Opción B', 'estado' => false]);

        $response = $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/listas/catalogo/TST_M15')->assertOk();
        $this->assertSame(['Opción A'], collect($response->json('data'))->pluck('nombre')->all());

        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/listas')->assertForbidden();
        $this->actingAs($this->funcionario)->getJson(self::PREFIX.'/listas/'.$lista->id)->assertForbidden();
    }
}
