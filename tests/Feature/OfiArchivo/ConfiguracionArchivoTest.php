<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use App\Services\OfiArchivo\ConfiguracionArchivo;
use App\Services\OfiArchivo\HojaControlService;
use App\Services\OfiArchivo\PendientesArchivoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Los ajustes del módulo de expedientes viven en config_varias para que cada entidad los
 * administre en Otras configuraciones; el entorno queda solo como respaldo.
 */
class ConfiguracionArchivoTest extends TestCase
{
    use DatabaseTransactions;

    private const BATCH = '/api/config/config-varias/batch';

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::table('config_varias')->where('clave', ConfiguracionArchivo::FOLIOS_POR_TOMO)->exists()) {
            $this->markTestSkipped('Requiere la migración 2026_10_19_210000.');
        }
    }

    private function editor(): User
    {
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('Config - Otras configuraciones -> Editar', 'web'));

        return $user;
    }

    public function test_los_ajustes_salen_de_config_varias_y_sin_valor_del_entorno(): void
    {
        config(['archivo.folios_por_tomo' => 77, 'archivo.meses_inactividad' => 9, 'firma.tsa_indice_expediente' => true]);
        ConfigVarias::setValor(ConfiguracionArchivo::FOLIOS_POR_TOMO, 120);
        ConfigVarias::setValor(ConfiguracionArchivo::MESES_INACTIVIDAD, 3);
        ConfigVarias::setValor(ConfiguracionArchivo::INDICE_TSA, 'false');

        $this->assertSame(120, HojaControlService::foliosPorTomo());
        $this->assertSame(3, PendientesArchivoService::mesesInactividad());
        $this->assertFalse(ConfiguracionArchivo::indiceConTsa());

        DB::table('config_varias')->where('clave', 'like', 'archivo\_%')->update(['valor' => '']);

        $this->assertSame(77, HojaControlService::foliosPorTomo());
        $this->assertSame(9, PendientesArchivoService::mesesInactividad());
        $this->assertTrue(ConfiguracionArchivo::indiceConTsa());

        DB::table('config_varias')->where('clave', ConfiguracionArchivo::FOLIOS_POR_TOMO)->delete();
        $this->assertSame(77, ConfiguracionArchivo::foliosPorTomo());
    }

    public function test_la_entidad_los_cambia_desde_otras_configuraciones(): void
    {
        $this->actingAs($this->editor())->postJson(self::BATCH, ['configs' => [
            ['clave' => ConfiguracionArchivo::FOLIOS_POR_TOMO, 'valor' => '250'],
            ['clave' => ConfiguracionArchivo::MESES_INACTIVIDAD, 'valor' => '12'],
            ['clave' => ConfiguracionArchivo::INDICE_TSA, 'valor' => 'true'],
        ]])->assertOk();

        $this->assertSame(250, ConfiguracionArchivo::foliosPorTomo());
        $this->assertSame(12, ConfiguracionArchivo::mesesInactividad());
        $this->assertTrue(ConfiguracionArchivo::indiceConTsa());
    }

    public function test_un_valor_invalido_rechaza_el_lote_completo(): void
    {
        ConfigVarias::setValor(ConfiguracionArchivo::MESES_INACTIVIDAD, 6);
        ConfigVarias::setValor(ConfiguracionArchivo::FOLIOS_POR_TOMO, 200);
        $editor = $this->editor();

        foreach (['0', '1001', 'abc', '', '12.5'] as $folios) {
            $this->actingAs($editor)->postJson(self::BATCH, ['configs' => [
                ['clave' => ConfiguracionArchivo::MESES_INACTIVIDAD, 'valor' => '12'],
                ['clave' => ConfiguracionArchivo::FOLIOS_POR_TOMO, 'valor' => $folios],
            ]])->assertUnprocessable()->assertJsonValidationErrors(['configs.1.valor']);
        }

        $this->actingAs($editor)->postJson(self::BATCH, ['configs' => [
            ['clave' => ConfiguracionArchivo::INDICE_TSA, 'valor' => 'quizas'],
        ]])->assertUnprocessable();

        $this->assertSame(6, ConfiguracionArchivo::mesesInactividad(), 'El lote rechazado no guarda nada');
        $this->assertSame(200, ConfiguracionArchivo::foliosPorTomo());
    }

    public function test_la_edicion_individual_tambien_valida(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->putJson('/api/config/config-varias/'.ConfiguracionArchivo::MESES_INACTIVIDAD, ['valor' => '121'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.valor.0', 'El valor de meses de inactividad no puede ser mayor que 120.');
        $this->actingAs($editor)->putJson('/api/config/config-varias/'.ConfiguracionArchivo::MESES_INACTIVIDAD, ['valor' => '24'])
            ->assertOk();

        $this->assertSame(24, ConfiguracionArchivo::mesesInactividad());
    }
}
