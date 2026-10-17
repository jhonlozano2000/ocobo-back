<?php

namespace Tests\Feature\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * RBAC-003: /api/archivo/documentos/ver solo validaba disco y extensión; cualquier usuario
 * autenticado leía digitales de radicados ajenos o firmas de otros conociendo el path.
 */
class VerDocumentoAutorizacionTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/archivo/documentos/ver';

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    private User $creador;

    private User $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        (new \ReflectionProperty(ArchivoHelper::class, 'storageCache'))->setValue(null, []);
        Storage::fake('radicados_internos');
        Storage::fake('firmas');

        foreach (['responsa', 'destina', 'proyectores', 'firmantes', 'archivos'] as $sufijo) {
            if (! Schema::hasTable("ventanilla_radica_internos_{$sufijo}")) {
                DB::statement("CREATE TEMPORARY TABLE ventanilla_radica_internos_{$sufijo} LIKE ventanilla_radica_interno_{$sufijo}");
            }
        }

        $mostrar = Permission::findOrCreate('Radicar -> Cores. Interna -> Mostrar', 'web');
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo($mostrar);
        $this->ajeno->givePermissionTo($mostrar);
    }

    private function internoConDigital(string $path): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('ventanilla_radica_internos')->insert([
            'num_radicado' => 'RBAC3-'.uniqid(), 'clasifica_documen_id' => 1,
            'usuario_crea' => $this->creador->id, 'archivo_digital' => $path,
        ]);
        Schema::enableForeignKeyConstraints();
        Storage::disk('radicados_internos')->put($path, self::PDF);
    }

    private function ver(User $user, string $disk, string $path)
    {
        return $this->actingAs($user)->get(self::URL.'?'.http_build_query(['disk' => $disk, 'path' => $path]));
    }

    public function test_digital_de_radicado_solo_lo_ve_quien_tiene_acceso_al_radicado(): void
    {
        $path = 'rbac3-'.uniqid().'.pdf';
        $this->internoConDigital($path);

        $this->ver($this->creador, 'radicados_internos', $path)->assertOk();
        $this->ver($this->ajeno, 'radicados_internos', $path)->assertNotFound();
    }

    public function test_archivo_suelto_en_el_disco_no_se_sirve(): void
    {
        $path = 'huerfano-'.uniqid().'.pdf';
        Storage::disk('radicados_internos')->put($path, self::PDF);

        $this->ver($this->creador, 'radicados_internos', $path)->assertNotFound();
    }

    public function test_la_firma_solo_la_ve_su_titular(): void
    {
        $path = 'firma-'.uniqid().'.pdf';
        $this->creador->forceFill(['firma' => $path])->save();
        Storage::disk('firmas')->put($path, self::PDF);

        $this->ver($this->creador, 'firmas', $path)->assertOk();
        $this->ver($this->ajeno, 'firmas', $path)->assertNotFound();
    }

    public function test_obtener_url_apunta_al_endpoint_real(): void
    {
        $path = 'url-'.uniqid().'.pdf';
        $this->internoConDigital($path);

        $url = ArchivoHelper::obtenerUrl($path, 'radicados_internos');

        $this->assertStringContainsString(self::URL.'?', $url);
        $this->actingAs($this->creador)->get($url)->assertOk();
    }
}
