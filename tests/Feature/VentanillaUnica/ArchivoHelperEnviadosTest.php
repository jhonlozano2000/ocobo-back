<?php

namespace Tests\Feature\VentanillaUnica;

use App\Helpers\ArchivoHelper;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * M02-11: Métodos centralizados en ArchivoHelper en lugar de Storage:: directo.
 */
class ArchivoHelperEnviadosTest extends TestCase
{
    private const DISK = 'radicados_enviados';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(self::DISK);
    }

    public function test_archivo_helper_operaciones_basicas(): void
    {
        $archivo = 'test-documento.pdf';
        $contenido = '%PDF-1.4 prueba de contenido';

        $this->assertFalse(ArchivoHelper::existe($archivo, self::DISK));

        ArchivoHelper::guardarContenido($archivo, $contenido, self::DISK);

        $this->assertTrue(ArchivoHelper::existe($archivo, self::DISK));
        $this->assertSame($contenido, ArchivoHelper::obtenerContenido($archivo, self::DISK));
        $this->assertSame(strlen($contenido), ArchivoHelper::tamanio($archivo, self::DISK));
        $this->assertNotNull(ArchivoHelper::tipoMime($archivo, self::DISK));

        $response = ArchivoHelper::descargar($archivo, self::DISK, 'descarga.pdf');
        $this->assertInstanceOf(StreamedResponse::class, $response);
    }
}
