<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTRDController;
use App\Services\ClasificacionDocumental\TRDService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * M10-07: método debug() que devolvía el session_id.
 * M10-08: el temporal de importación usaba el nombre original del archivo.
 */
class ImportacionTRDTemporalTest extends TestCase
{
    public function test_no_existe_el_metodo_debug(): void
    {
        $this->assertFalse(method_exists(ClasificacionDocumentalTRDController::class, 'debug'));
    }

    public function test_el_temporal_tiene_nombre_unico_y_se_borra(): void
    {
        Log::spy();
        $archivo = UploadedFile::fake()->createWithContent('trd_dependencia.xlsx', 'no es un excel');

        try {
            app(TRDService::class)->importFromExcel([], $archivo);
        } catch (\Throwable) {
            // El contenido no es un Excel válido; solo interesa el temporal
        }

        $ruta = null;
        Log::shouldHaveReceived('info')->withArgs(function ($mensaje, $contexto = []) use (&$ruta) {
            if ($mensaje === 'TRD Import file saved') {
                $ruta = $contexto['tempPath'];
            }

            return true;
        });

        $this->assertNotNull($ruta);
        $this->assertStringNotContainsString('trd_dependencia', basename($ruta));
        $this->assertFileDoesNotExist($ruta);
    }
}
