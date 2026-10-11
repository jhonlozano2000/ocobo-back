<?php

namespace Tests\Feature\VentanillaUnica;

use App\Services\VentanillaUnica\ImapEmailService;
use DirectoryTree\ImapEngine\Attachment;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * M05-05: el nombre del adjunto viene del header MIME (lo controla el remitente)
 * y no puede salir del directorio del radicado ni sobrescribir archivos.
 */
class AdjuntoImapNombreSeguroTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorio = 'test-m05-05-'.uniqid().'/RAD-0001';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/'.dirname($this->directorio)));

        parent::tearDown();
    }

    private function adjunto(?string $nombre, string $contenido = 'x'): Attachment
    {
        return new Attachment($nombre, null, 'application/pdf', 'attachment', Utils::streamFor($contenido));
    }

    public function test_nombre_con_path_traversal_queda_dentro_del_directorio(): void
    {
        $servicio = app(ImapEmailService::class);

        foreach (['../../../evil.php', '..\\..\\evil2.php', '/etc/passwd', '.htaccess'] as $nombre) {
            $ruta = $servicio->saveAttachment($this->adjunto($nombre), $this->directorio);

            $this->assertSame($this->directorio, dirname($ruta), $nombre);
            $this->assertFileExists(storage_path('app/'.$ruta));
        }

        $this->assertFileDoesNotExist(storage_path('app/evil.php'));
        $this->assertFileDoesNotExist(storage_path('evil2.php'));
    }

    public function test_no_sobrescribe_archivos_existentes(): void
    {
        $servicio = app(ImapEmailService::class);

        $primera = $servicio->saveAttachment($this->adjunto('correo-RAD-0001.pdf', 'original'), $this->directorio);
        $segunda = $servicio->saveAttachment($this->adjunto('correo-RAD-0001.pdf', 'ataque'), $this->directorio);

        $this->assertNotSame($primera, $segunda);
        $this->assertSame('original', file_get_contents(storage_path('app/'.$primera)));
        $this->assertStringEndsWith('correo-RAD-0001_1.pdf', $segunda);
    }

    public function test_nombre_seguro(): void
    {
        $this->assertSame('evil.php', ImapEmailService::nombreSeguroAdjunto('../../evil.php'));
        $this->assertSame('passwd', ImapEmailService::nombreSeguroAdjunto('C:\\Windows\\..\\passwd'));
        $this->assertSame('Respuesta año 2026.pdf', ImapEmailService::nombreSeguroAdjunto('Respuesta año 2026.pdf'));
        $this->assertSame('htaccess', ImapEmailService::nombreSeguroAdjunto('.htaccess'));
        $this->assertStringStartsWith('adjunto_', ImapEmailService::nombreSeguroAdjunto('..', 'pdf'));
        $this->assertStringEndsWith('.pdf', ImapEmailService::nombreSeguroAdjunto(null, 'pdf'));
        $this->assertLessThanOrEqual(150, mb_strlen(ImapEmailService::nombreSeguroAdjunto(str_repeat('a', 300).'.pdf')));
    }
}
