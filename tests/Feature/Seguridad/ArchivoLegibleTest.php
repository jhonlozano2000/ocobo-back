<?php

namespace Tests\Feature\Seguridad;

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTVDController;
use App\Http\Requests\ClasificacionDocumental\ClasificacionDocumentalRequest;
use App\Http\Requests\ClasificacionDocumental\ImportarTRDRequest;
use App\Http\Requests\MiBandeja\CheckInRequest;
use App\Http\Requests\MiBandeja\StoreGrupoAdjuntoRequest;
use App\Http\Requests\MiBandeja\TempReci\ImportDocumentoRequest;
use App\Http\Requests\OfiArchivo\StorePlantillaDocumentoRequest;
use App\Http\Requests\OfiArchivo\UpdatePlantillaDocumentoRequest;
use App\Http\Requests\Ventanilla\Enviados\UploadArchivoEnviadoRequest;
use App\Http\Requests\Ventanilla\Enviados\UploadArchivosAdjuntosEnviadoRequest;
use App\Http\Requests\Ventanilla\Internos\UploadArchivoInternoRequest;
use App\Http\Requests\Ventanilla\Internos\UploadArchivosAdjuntosInternoRequest;
use App\Http\Requests\Ventanilla\Pqrs\UploadPqrsAdjuntosRequest;
use App\Http\Requests\Ventanilla\Pqrs\UploadPqrsDigitalRequest;
use App\Http\Requests\Ventanilla\Recibidos\UploadArchivoRecibidoRequest;
use App\Http\Requests\Ventanilla\Recibidos\UploadArchivosAdjuntosRecibidoRequest;
use App\Http\Requests\Workflows\StoreTareaArchivoRequest;
use App\Http\Requests\Workflows\StoreWorkFlowArchivoRequest;
use App\Rules\ArchivoLegible;
use App\Services\ClasificacionDocumental\TVDService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Un archivo subido que no se puede leer (bloqueado o borrado por el antivirus) debe terminar en un
 * error de validación, no en un 500: las reglas max y mimes lo leen y lanzan excepción.
 */
class ArchivoLegibleTest extends TestCase
{
    private function ilegible(string $nombre): UploadedFile
    {
        $temporal = tempnam(sys_get_temp_dir(), 'ilegible');
        file_put_contents($temporal, '%PDF-1.4 contenido');
        $archivo = new UploadedFile($temporal, $nombre, null, null, true);
        unlink($temporal);

        return $archivo;
    }

    public function test_la_regla_rechaza_solo_lo_que_no_se_puede_leer(): void
    {
        $temporal = tempnam(sys_get_temp_dir(), 'legible');
        file_put_contents($temporal, '%PDF-1.4 contenido');
        $legible = new UploadedFile($temporal, 'oficio.pdf', null, null, true);

        $this->assertFalse(Validator::make(['f' => $legible], ['f' => [new ArchivoLegible]])->fails());
        $this->assertFalse(Validator::make(['f' => 'texto'], ['f' => [new ArchivoLegible]])->fails());

        $validador = Validator::make(['f' => $this->ilegible('oficio.pdf')], ['f' => [new ArchivoLegible]]);
        $this->assertSame(ArchivoLegible::MENSAJE, $validador->errors()->first('f'));

        unlink($temporal);
    }

    /**
     * @return array<string, array{0: class-string, 1: string, 2: string}>
     */
    public static function solicitudes(): array
    {
        return [
            'TRD (clasificación)' => [ClasificacionDocumentalRequest::class, 'file', 'trd.xlsx'],
            'TRD (importar)' => [ImportarTRDRequest::class, 'archivo', 'trd.xlsx'],
            'check-in' => [CheckInRequest::class, 'archivo', 'oficio.docx'],
            'importar documento' => [ImportDocumentoRequest::class, 'archivo', 'oficio.docx'],
            'adjunto de grupo' => [StoreGrupoAdjuntoRequest::class, 'archivo', 'anexo.pdf'],
            'plantilla (crear)' => [StorePlantillaDocumentoRequest::class, 'archivo', 'plantilla.docx'],
            'plantilla (editar)' => [UpdatePlantillaDocumentoRequest::class, 'archivo', 'plantilla.docx'],
            'enviado digital' => [UploadArchivoEnviadoRequest::class, 'archivo_digital', 'oficio.pdf'],
            'interno digital' => [UploadArchivoInternoRequest::class, 'archivo_digital', 'oficio.pdf'],
            'recibido digital' => [UploadArchivoRecibidoRequest::class, 'archivo_digital', 'oficio.pdf'],
            'PQRS digital' => [UploadPqrsDigitalRequest::class, 'archivo_digital', 'oficio.pdf'],
            'adjuntos enviado' => [UploadArchivosAdjuntosEnviadoRequest::class, 'archivos.0', 'anexo.pdf'],
            'adjuntos interno' => [UploadArchivosAdjuntosInternoRequest::class, 'archivos.0', 'anexo.pdf'],
            'adjuntos recibido' => [UploadArchivosAdjuntosRecibidoRequest::class, 'archivos.0', 'anexo.pdf'],
            'adjuntos PQRS' => [UploadPqrsAdjuntosRequest::class, 'archivos.0', 'anexo.pdf'],
            'tarea de workflow' => [StoreTareaArchivoRequest::class, 'archivo', 'anexo.pdf'],
            'archivo de workflow' => [StoreWorkFlowArchivoRequest::class, 'archivo', 'anexo.pdf'],
        ];
    }

    #[DataProvider('solicitudes')]
    public function test_cada_carga_rechaza_el_archivo_ilegible_con_validacion(string $clase, string $campo, string $nombre): void
    {
        $archivo = $this->ilegible($nombre);
        $datos = str_starts_with($campo, 'archivos.') ? ['archivos' => [$archivo]] : [$campo => $archivo];

        $reglas = (new $clase)->rules();
        $soloArchivo = array_intersect_key($reglas, array_flip([$campo, str_replace('.0', '.*', $campo)]));
        $this->assertNotEmpty($soloArchivo, "{$clase} no valida {$campo}");

        $validador = Validator::make($datos, $soloArchivo);

        $this->assertTrue($validador->fails());
        $this->assertSame(ArchivoLegible::MENSAJE, $validador->errors()->first($campo));
    }

    public function test_las_validaciones_en_controladores_tambien(): void
    {
        $reglas = (fn () => self::reglasImportacion())->call(new ClasificacionDocumentalTVDController(app(TVDService::class)));
        $validador = Validator::make(['archivo' => $this->ilegible('tvd.xlsx')], ['archivo' => $reglas['archivo']]);
        $this->assertSame(ArchivoLegible::MENSAJE, $validador->errors()->first('archivo'));

        $fuente = file_get_contents(app_path('Http/Controllers/MiBandeja/Temp/MiBandejaTempController.php'));
        $this->assertStringContainsString("'archivo' => ['bail', 'required', 'file', new ArchivoLegible, 'max:51200',", $fuente);
    }
}
