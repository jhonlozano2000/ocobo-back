<?php

namespace Tests\Feature\MiBandeja;

use App\Models\MiDisco\MiDiscoCarpeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * RBAC-003: mi-disco validaba carpeta_padre_id / carpeta_id solo con `exists`, así que se podían
 * crear carpetas o subir archivos dentro de la carpeta de otro usuario y leer su ruta en el breadcrumb.
 */
class MiDiscoCarpetaAjenaTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/mi-bandeja/mi-disco';

    private User $duenio;

    private User $ajeno;

    private MiDiscoCarpeta $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('mi_disco_carpetas')) {
            $this->markTestSkipped('La BD de pruebas no tiene las tablas de mi-disco');
        }

        Storage::fake('local');
        $this->duenio = User::factory()->create(['estado' => 1]);
        $this->ajeno = User::factory()->create(['estado' => 1]);
        $this->carpeta = MiDiscoCarpeta::create(['nombre' => 'Privada '.uniqid(), 'user_id' => $this->duenio->id]);
    }

    public function test_no_se_crean_carpetas_dentro_de_una_ajena(): void
    {
        $this->actingAs($this->ajeno)
            ->postJson(self::URL.'/carpetas', ['nombre' => 'Intrusa', 'carpeta_padre_id' => $this->carpeta->id])
            ->assertUnprocessable();

        $this->actingAs($this->duenio)
            ->postJson(self::URL.'/carpetas', ['nombre' => 'Propia', 'carpeta_padre_id' => $this->carpeta->id])
            ->assertCreated();
    }

    public function test_no_se_suben_archivos_a_una_carpeta_ajena(): void
    {
        $this->actingAs($this->ajeno)
            ->post(self::URL.'/archivos', [
                'archivos' => [UploadedFile::fake()->create('x.pdf', 1, 'application/pdf')],
                'carpeta_id' => $this->carpeta->id,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_el_breadcrumb_no_revela_carpetas_ajenas(): void
    {
        $this->actingAs($this->ajeno)
            ->getJson(self::URL.'?carpeta_id='.$this->carpeta->id)
            ->assertOk()
            ->assertJsonPath('data.breadcrumb', []);
    }
}
