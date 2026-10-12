<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoDepuracionDigital;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\TablaDepuracionDigital;
use Tests\TestCase;

/**
 * M08-02: la depuración cubre radicados internos y expedientes, deja bitácora de cada archivo
 * movido a cuarentena y la cuarentena solo se purga de forma explícita y registrada.
 */
class DepuracionDigitalTest extends TestCase
{
    use DatabaseTransactions, TablaDepuracionDigital;

    protected function setUp(): void
    {
        parent::setUp();

        // Tablas de sesión (sin commit implícito) mientras la BD de pruebas no tenga las migraciones
        $this->asegurarTablaDepuracionDigital();
        if (! Schema::hasTable('ventanilla_radica_internos_archivos')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radica_internos_archivos LIKE ventanilla_radica_interno_archivos');
        }
    }

    public function test_radicados_internos_huerfanos_van_a_cuarentena_con_bitacora(): void
    {
        $storage = Storage::fake('radicados_internos');
        $user = User::factory()->create();
        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId(['num_radicado' => 'M0802-I-1', 'archivo_digital' => 'principal.pdf', 'usuario_crea' => $user->id, 'clasifica_documen_id' => 1]);
        DB::table('ventanilla_radica_internos_archivos')->insert(['radicado_id' => $id, 'archivo' => 'anexo.pdf', 'subido_por' => $user->id]);
        Schema::enableForeignKeyConstraints();
        foreach (['principal.pdf', 'anexo.pdf', 'M0802-I-1/correo.eml', 'huerfano.pdf'] as $ruta) {
            $storage->put($ruta, 'contenido '.$ruta);
        }

        $this->artisan('archivo:depurar-digital', ['--disco' => 'radicados_internos', '--dias' => 0])->assertSuccessful();

        $storage->assertExists(['principal.pdf', 'anexo.pdf', 'M0802-I-1/correo.eml']);
        $storage->assertMissing('huerfano.pdf');
        $destino = 'cuarentena/'.now()->format('Y-m-d').'/huerfano.pdf';
        $storage->assertExists($destino);

        $registro = OfiArchivoDepuracionDigital::where('disco', 'radicados_internos')->sole();
        $this->assertSame(['cuarentena', 'huerfano.pdf', $destino, hash('sha256', 'contenido huerfano.pdf')],
            [$registro->accion, $registro->ruta_original, $registro->ruta_cuarentena, $registro->hash_sha256]);
    }

    public function test_expedientes_conservan_folios_retirados(): void
    {
        $storage = Storage::fake('archivo_expedientes');
        $user = User::factory()->create();
        Schema::disableForeignKeyConstraints();
        DB::table('ofi_archivo_expedientes_documentos')->insert([
            'expediente_id' => 999999, 'numero_folio' => 1, 'orden' => 0, 'tipo_documental' => 'Documento', 'fecha_documento' => '2026-10-12',
            'asunto' => 'Retirado', 'usuario_id' => $user->id, 'archivo_path' => 'exp/retirado.pdf', 'activo' => false,
        ]);
        Schema::enableForeignKeyConstraints();
        $storage->put('exp/retirado.pdf', 'folio');
        $storage->put('exp/rollback.pdf', 'sin registro');

        $this->artisan('archivo:depurar-digital', ['--disco' => 'archivo_expedientes', '--dias' => 0])->assertSuccessful();

        $storage->assertExists('exp/retirado.pdf');
        $storage->assertMissing('exp/rollback.pdf');
    }

    public function test_purga_solo_cuarentena_vencida_y_la_registra(): void
    {
        $storage = Storage::fake('radicados_internos');
        $vieja = 'cuarentena/'.now()->subDays(100)->format('Y-m-d');
        $reciente = 'cuarentena/'.now()->subDays(5)->format('Y-m-d');
        $storage->put("{$vieja}/a/viejo.pdf", 'viejo');
        $storage->put("{$reciente}/nuevo.pdf", 'nuevo');

        $this->artisan('archivo:depurar-digital', ['--disco' => 'radicados_internos', '--purgar-dias' => 10])->assertFailed();
        $storage->assertExists("{$vieja}/a/viejo.pdf");

        $this->artisan('archivo:depurar-digital', ['--disco' => 'radicados_internos', '--purgar-dias' => 90])->assertSuccessful();

        $storage->assertMissing("{$vieja}/a/viejo.pdf");
        $storage->assertExists("{$reciente}/nuevo.pdf");
        $registro = OfiArchivoDepuracionDigital::where('accion', 'purgado')->sole();
        $this->assertSame(['a/viejo.pdf', hash('sha256', 'viejo')], [$registro->ruta_original, $registro->hash_sha256]);
    }
}
