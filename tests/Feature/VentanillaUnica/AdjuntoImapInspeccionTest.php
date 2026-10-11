<?php

namespace Tests\Feature\VentanillaUnica;

use App\Services\VentanillaUnica\ImapEmailService;
use DirectoryTree\ImapEngine\Attachment;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use ZipArchive;

/**
 * M05-06: los adjuntos de correo se inspeccionan (contenido real, ejecutables,
 * zip-bomb y tamaño) antes de quedar registrados en el radicado.
 */
class AdjuntoImapInspeccionTest extends TestCase
{
    private string $directorio;

    private ImapEmailService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->directorio = 'test-m05-06-'.uniqid().'/RAD-0001';
        $this->servicio = app(ImapEmailService::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/'.dirname($this->directorio)));

        parent::tearDown();
    }

    private function guardar(string $nombre, string $contenido): ?string
    {
        $adjunto = new Attachment($nombre, null, 'application/octet-stream', 'attachment', Utils::streamFor($contenido));

        return $this->servicio->saveAttachment($adjunto, $this->directorio);
    }

    private function archivosGuardados(): array
    {
        $dir = storage_path('app/'.$this->directorio);

        return is_dir($dir) ? array_values(array_diff(scandir($dir), ['.', '..'])) : [];
    }

    public function test_guarda_adjuntos_legitimos(): void
    {
        $ruta = $this->guardar('oficio.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");

        $this->assertNotNull($ruta);
        $this->assertFileExists(storage_path('app/'.$ruta));
    }

    public function test_rechaza_ejecutables_y_contenido_disfrazado(): void
    {
        Log::spy();

        $this->assertNull($this->guardar('factura.exe', "MZ\x90\x00".str_repeat("\x00", 200)));
        $this->assertNull($this->guardar('factura.pdf', "MZ\x90\x00".str_repeat("\x00", 200)));
        $this->assertNull($this->guardar('pagina.html', '<html><script>alert(1)</script></html>'));
        $this->assertNull($this->guardar('shell.dat', '<?php system($_GET["c"]); ?>'));

        $this->assertSame([], $this->archivosGuardados());
        Log::shouldHaveReceived('warning')->times(4);
    }

    public function test_rechaza_zip_bomb(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('ceros.bin', str_repeat("\0", 5 * 1024 * 1024));
        $zip->close();
        $contenido = file_get_contents($tmp);
        @unlink($tmp);

        $this->assertNull($this->guardar('anexos.zip', $contenido));
        $this->assertSame([], $this->archivosGuardados());
    }

    public function test_rechaza_adjuntos_que_superan_el_tamano_maximo(): void
    {
        $this->assertGreaterThanOrEqual(ImapEmailService::MIN_MAX_ADJUNTO, ImapEmailService::maxBytesAdjunto());

        $this->assertNull($this->guardar('grande.txt', str_repeat('a', ImapEmailService::maxBytesAdjunto() + 1)));
        $this->assertSame([], $this->archivosGuardados());
    }
}
