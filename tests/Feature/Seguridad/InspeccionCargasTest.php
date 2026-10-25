<?php

namespace Tests\Feature\Seguridad;

use App\Http\Controllers\ClasificacionDocumental\ClasificacionDocumentalTVDController;
use App\Http\Requests\ClasificacionDocumental\ClasificacionDocumentalRequest;
use App\Http\Requests\ClasificacionDocumental\ImportarTRDRequest;
use App\Http\Requests\MiBandeja\CheckInRequest;
use App\Http\Requests\OfiArchivo\StorePlantillaDocumentoRequest;
use App\Http\Requests\OfiArchivo\UpdatePlantillaDocumentoRequest;
use App\Http\Requests\Ventanilla\Enviados\UploadArchivoEnviadoRequest;
use App\Http\Requests\Ventanilla\Internos\UploadArchivoInternoRequest;
use App\Http\Requests\Ventanilla\Pqrs\UploadPqrsDigitalRequest;
use App\Http\Requests\Ventanilla\Recibidos\UploadArchivoRecibidoRequest;
use App\Http\Requests\Workflows\StoreTareaArchivoRequest;
use App\Http\Requests\Workflows\StoreWorkFlowArchivoRequest;
use App\Services\ClasificacionDocumental\TVDService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las cargas que solo validaban la extensión (mimes) inspeccionan también el contenido real con
 * ArchivoSeguro: un script con extensión de documento se rechaza y un documento real pasa.
 */
class InspeccionCargasTest extends TestCase
{
    private array $temporales = [];

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), $this->temporales);

        parent::tearDown();
    }

    private function subido(string $contenido, string $nombre): UploadedFile
    {
        $this->temporales[] = $temporal = tempnam(sys_get_temp_dir(), 'carga');
        file_put_contents($temporal, $contenido);

        return new UploadedFile($temporal, $nombre, null, null, true);
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";
    }

    /**
     * @return array<string, array{0: class-string, 1: string, 2: string}>
     */
    public static function cargas(): array
    {
        return [
            'enviado digital' => [UploadArchivoEnviadoRequest::class, 'archivo_digital', 'pdf'],
            'interno digital' => [UploadArchivoInternoRequest::class, 'archivo_digital', 'pdf'],
            'recibido digital' => [UploadArchivoRecibidoRequest::class, 'archivo_digital', 'pdf'],
            'PQRS digital' => [UploadPqrsDigitalRequest::class, 'archivo_digital', 'pdf'],
            'plantilla (crear)' => [StorePlantillaDocumentoRequest::class, 'archivo', 'pdf'],
            'plantilla (editar)' => [UpdatePlantillaDocumentoRequest::class, 'archivo', 'pdf'],
            'check-in' => [CheckInRequest::class, 'archivo', 'pdf'],
            'tarea de workflow' => [StoreTareaArchivoRequest::class, 'archivo', 'pdf'],
            'archivo de workflow' => [StoreWorkFlowArchivoRequest::class, 'archivo', 'pdf'],
            'TRD (clasificación)' => [ClasificacionDocumentalRequest::class, 'file', 'xlsx'],
            'TRD (importar)' => [ImportarTRDRequest::class, 'archivo', 'xlsx'],
        ];
    }

    private function validar(array $reglas, string $campo, UploadedFile $archivo): \Illuminate\Validation\Validator
    {
        return Validator::make([$campo => $archivo], [$campo => $reglas]);
    }

    #[DataProvider('cargas')]
    public function test_un_script_con_extension_de_documento_se_rechaza(string $clase, string $campo, string $ext): void
    {
        $reglas = (new $clase)->rules()[$campo];

        $script = $this->validar($reglas, $campo, $this->subido('<?php echo "hola"; ?>', "oficio.{$ext}"));
        $this->assertSame('El contenido del archivo no está permitido.', $script->errors()->first($campo));

        if ($ext === 'pdf') {
            $this->assertFalse($this->validar($reglas, $campo, $this->subido($this->pdf(), 'oficio.pdf'))->fails());
        }
    }

    public function test_un_xlsx_que_no_es_hoja_de_calculo_se_rechaza(): void
    {
        $reglas = (new ImportarTRDRequest)->rules()['archivo'];

        $falso = $this->validar($reglas, 'archivo', $this->subido($this->pdf(), 'trd.xlsx'));
        $this->assertSame('El contenido del archivo no corresponde con su extensión .xlsx.', $falso->errors()->first('archivo'));
    }

    public function test_importacion_tvd_y_version_inicial_de_grupo(): void
    {
        $reglas = (fn () => self::reglasImportacion())->call(new ClasificacionDocumentalTVDController(app(TVDService::class)));
        $script = $this->validar($reglas['archivo'], 'archivo', $this->subido('<?php echo 1; ?>', 'tvd.csv'));
        $this->assertSame('El contenido del archivo no está permitido.', $script->errors()->first('archivo'));

        $fuente = file_get_contents(app_path('Http/Controllers/MiBandeja/Temp/MiBandejaTempController.php'));
        $this->assertStringContainsString("new ArchivoSeguro(CheckInRequest::EXTENSIONES), 'mimes:'.implode(',', CheckInRequest::EXTENSIONES)", $fuente);
    }
}
