<?php

namespace Tests\Feature\Seguridad;

use App\Http\Requests\MiBandeja\StoreGrupoAdjuntoRequest;
use App\Http\Requests\Ventanilla\Enviados\UploadArchivosAdjuntosEnviadoRequest;
use App\Http\Requests\Ventanilla\Internos\UploadArchivosAdjuntosInternoRequest;
use App\Http\Requests\Ventanilla\Pqrs\UploadPqrsAdjuntosRequest;
use App\Http\Requests\Ventanilla\Recibidos\UploadArchivosAdjuntosRecibidoRequest;
use App\Models\Configuracion\ConfigVarias;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * M01-04 / M02-04 / M03-10: las cargas de adjuntos validan extensión permitida
 * y contenido real (antes solo 'file' y 'max').
 */
class CargaAdjuntosSegurosTest extends TestCase
{
    private array $temporales = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporales as $f) {
            @unlink($f);
        }

        parent::tearDown();
    }

    private function subido(string $contenido, string $nombre): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'carga');
        file_put_contents($path, $contenido);
        $this->temporales[] = $path;

        return new UploadedFile($path, $nombre, null, null, true);
    }

    private function pdf(string $nombre = 'oficio.pdf'): UploadedFile
    {
        return $this->subido("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n", $nombre);
    }

    public static function requests(): array
    {
        return [
            'recibidos' => [UploadArchivosAdjuntosRecibidoRequest::class],
            'enviados' => [UploadArchivosAdjuntosEnviadoRequest::class],
            'internos' => [UploadArchivosAdjuntosInternoRequest::class],
            'pqrs' => [UploadPqrsAdjuntosRequest::class],
        ];
    }

    /**
     * @dataProvider requests
     */
    public function test_acepta_pdf_legitimo(string $request): void
    {
        $this->assertTrue(Validator::make(['archivos' => [$this->pdf()]], (new $request)->rules())->passes());
    }

    /**
     * @dataProvider requests
     */
    public function test_rechaza_webshell_y_extension_falsa(string $request): void
    {
        $rules = (new $request)->rules();

        $webshell = $this->subido('<?php system($_GET["c"]); ?>', 'shell.php');
        $this->assertTrue(Validator::make(['archivos' => [$webshell]], $rules)->fails());

        $disfrazado = $this->subido('<?php system($_GET["c"]); ?>', 'oficio.pdf');
        $this->assertTrue(Validator::make(['archivos' => [$disfrazado]], $rules)->fails());

        $exe = $this->subido("MZ\x90\x00".str_repeat("\x00", 200), 'factura.exe');
        $this->assertTrue(Validator::make(['archivos' => [$exe]], $rules)->fails());
    }

    public function test_adjunto_de_grupo_valida_contenido_y_tamano_en_kb(): void
    {
        $rules = (new StoreGrupoAdjuntoRequest)->rules();

        $this->assertTrue(Validator::make(['archivo' => $this->pdf()], $rules)->passes());
        $this->assertTrue(Validator::make(['archivo' => $this->subido('<?php echo 1; ?>', 'oficio.pdf')], $rules)->fails());

        // max se expresa en KB: antes se multiplicaba por 1024 (≈10 GB permitidos).
        $this->assertContains('max:'.ConfigVarias::getValor('max_tamano_archivo', 10240), $rules['archivo']);
    }
}
