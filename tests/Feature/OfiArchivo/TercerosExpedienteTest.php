<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * B24 (diseño de expedientes §2.1): el expediente guarda sus terceros con un rol; el vínculo
 * solo cambia con el expediente abierto y cada cambio queda en el historial.
 */
class TercerosExpedienteTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private OfiArchivoExpediente $expediente;

    private int $tercero;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ofi_archivo_expediente_terceros') || ! Schema::hasTable('ofi_archivo_expediente_eventos')) {
            $this->markTestSkipped('Requiere las migraciones 2026_10_19_100000 y 170000.');
        }

        $this->user = User::factory()->create(['estado' => 1]);
        foreach (['Ver', 'Editar'] as $accion) {
            $this->user->givePermissionTo(Permission::findOrCreate('Gestion de Archivo -> Expedientes -> '.$accion, 'web'));
        }

        $this->tercero = DB::table('gestion_terceros')->insertGetId([
            'num_docu_nit' => 'TST-B24-'.uniqid(), 'nom_razo_soci' => 'Contratista B24', 'tipo' => 'Natural',
        ]);

        $this->actingAs($this->user);
        Schema::disableForeignKeyConstraints();
        $this->expediente = OfiArchivoExpediente::create([
            'numero_expediente' => '2026-B24-'.uniqid(), 'nombre_expediente' => 'Contrato B24', 'anio' => 2026,
            'dependencia_id' => 1, 'serie_trd_id' => 1, 'estado' => 'abierto', 'nivel_acceso' => 'publico',
            'fecha_apertura' => '2026-01-10', 'usuario_apertura_id' => $this->user->id,
        ]);
        Schema::enableForeignKeyConstraints();
    }

    private function url(string $sufijo = ''): string
    {
        return "/api/archivo/expedientes/{$this->expediente->id}/terceros{$sufijo}";
    }

    public function test_vincula_y_desvincula_terceros_con_rol_y_deja_rastro(): void
    {
        $this->postJson($this->url(), ['tercero_id' => $this->tercero, 'rol' => 'jefe'])
            ->assertUnprocessable()->assertJsonValidationErrors('rol');

        $this->postJson($this->url(), ['tercero_id' => $this->tercero, 'rol' => 'contratista'])
            ->assertOk()->assertJsonPath('data.0.rol', 'contratista')->assertJsonPath('data.0.tercero.nom_razo_soci', 'Contratista B24');
        // Repetir el mismo vínculo no lo duplica ni registra otro evento; otro rol sí es otro vínculo
        $this->postJson($this->url(), ['tercero_id' => $this->tercero, 'rol' => 'contratista'])->assertOk()->assertJsonCount(1, 'data');
        $vinculos = $this->postJson($this->url(), ['tercero_id' => $this->tercero, 'rol' => 'titular'])->assertJsonCount(2, 'data')->json('data');

        $this->deleteJson($this->url('/'.$vinculos[0]['id']))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.rol', 'titular');

        $eventos = OfiArchivoExpedienteEvento::where('expediente_id', $this->expediente->id)
            ->whereIn('accion', ['vinculo_tercero', 'desvinculo_tercero'])->orderBy('secuencia')->get();
        $this->assertSame(['vinculo_tercero', 'vinculo_tercero', 'desvinculo_tercero'], $eventos->pluck('accion')->all());
        $this->assertSame('contratista', $eventos[2]->detalle['rol']);
        $this->assertStringEndsWith('Contratista B24', $eventos[2]->detalle['tercero']);
    }

    public function test_el_vinculo_no_cambia_con_el_expediente_cerrado_ni_fuera_del_alcance(): void
    {
        $vinculo = $this->postJson($this->url(), ['tercero_id' => $this->tercero, 'rol' => 'titular'])->json('data.0.id');
        DB::table('ofi_archivo_expedientes')->where('id', $this->expediente->id)->update(['estado' => 'cerrado']);

        $this->postJson($this->url(), ['tercero_id' => $this->tercero, 'rol' => 'solicitante'])
            ->assertUnprocessable()->assertJsonPath('message', 'Solo se pueden cambiar los terceros de un expediente abierto.');
        $this->deleteJson($this->url('/'.$vinculo))->assertUnprocessable();
        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');

        // Sin "Editar" no se modifica; un expediente ajeno (fuera de su dependencia) no existe para él
        $otro = User::factory()->create(['estado' => 1]);
        $otro->givePermissionTo('Gestion de Archivo -> Expedientes -> Ver');
        $this->actingAs($otro)->postJson($this->url(), ['tercero_id' => $this->tercero, 'rol' => 'solicitante'])->assertForbidden();
        $this->actingAs($otro)->getJson($this->url())->assertNotFound();
    }
}
