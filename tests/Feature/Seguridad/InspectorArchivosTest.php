<?php

namespace Tests\Feature\Seguridad;

use App\Rules\ArchivoSeguro;
use App\Services\Seguridad\InspectorArchivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use ZipArchive;

/**
 * M01-04 / M02-04 / M03-10 / M05-06: inspección del contenido real de los archivos.
 */
class InspectorArchivosTest extends TestCase
{
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $f) {
            @unlink($f);
        }

        parent::tearDown();
    }

    private function archivo(string $contenido): string
    {
        $path = tempnam(sys_get_temp_dir(), 'insp');
        file_put_contents($path, $contenido);

        return $this->temporales[] = $path;
    }

    private function zip(array $entradas): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entradas as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }
        $zip->close();

        return $this->temporales[] = $path;
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    public function test_acepta_archivos_legitimos(): void
    {
        $this->assertNull(InspectorArchivos::inspeccionar($this->archivo($this->pdf()), 'oficio.pdf'));
        $this->assertNull(InspectorArchivos::inspeccionar($this->archivo("Radicado de prueba\n"), 'nota.txt'));
        $this->assertNull(InspectorArchivos::inspeccionar($this->zip(['a.txt' => 'hola', 'b.txt' => 'mundo']), 'anexos.zip'));
    }

    public function test_rechaza_extensiones_y_contenido_ejecutable(): void
    {
        $this->assertNotNull(InspectorArchivos::inspeccionar($this->archivo($this->pdf()), 'shell.php'));
        $this->assertNotNull(InspectorArchivos::inspeccionar($this->archivo("MZ\x90\x00".str_repeat("\x00", 200)), 'factura.exe'));
        $this->assertNotNull(InspectorArchivos::inspeccionar($this->archivo('<?php system($_GET["c"]); ?>'), 'archivo.dat'));
        $this->assertNotNull(InspectorArchivos::inspeccionar($this->archivo('<html><script>alert(1)</script></html>'), 'x.dat'));
    }

    public function test_rechaza_contenido_que_no_corresponde_con_la_extension(): void
    {
        $error = InspectorArchivos::inspeccionar($this->archivo('<?php echo 1; ?>'), 'oficio.pdf');
        $this->assertNotNull($error);

        $this->assertNotNull(InspectorArchivos::inspeccionar($this->archivo('texto plano'), 'foto.jpg'));
    }

    public function test_respeta_la_lista_de_extensiones_permitidas(): void
    {
        $path = $this->archivo("a,b\n1,2\n");

        $this->assertNull(InspectorArchivos::inspeccionar($path, 'datos.csv', ['csv', 'pdf']));
        $this->assertNotNull(InspectorArchivos::inspeccionar($path, 'datos.csv', ['pdf']));
    }

    public function test_rechaza_zip_bomb(): void
    {
        $bomba = $this->zip(['ceros.bin' => str_repeat("\0", 5 * 1024 * 1024)]);

        $this->assertNotNull(InspectorArchivos::inspeccionar($bomba, 'anexos.zip'));
    }

    public function test_extension_segura_no_confia_en_el_cliente(): void
    {
        $this->assertSame('pdf', InspectorArchivos::extensionSegura($this->archivo($this->pdf()), 'Oficio.PDF'));
        $this->assertSame('pdf', InspectorArchivos::extensionSegura($this->archivo($this->pdf()), 'oficio.php'));
        $this->assertNotSame('php', InspectorArchivos::extensionSegura($this->archivo('<?php echo 1; ?>'), 'x.php'));
    }

    public function test_regla_archivo_seguro(): void
    {
        $ok = new UploadedFile($this->archivo($this->pdf()), 'oficio.pdf', null, null, true);
        $malo = new UploadedFile($this->archivo('<?php echo 1; ?>'), 'oficio.pdf', null, null, true);

        $this->assertTrue(Validator::make(['f' => $ok], ['f' => [new ArchivoSeguro(['pdf'])]])->passes());
        $this->assertTrue(Validator::make(['f' => $malo], ['f' => [new ArchivoSeguro(['pdf'])]])->fails());
    }
}
