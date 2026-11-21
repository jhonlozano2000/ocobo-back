<?php

namespace Tests\Unit;

use App\Http\Requests\Ia\CrearDocumentoDesdeBorradorIaRequest;
use App\Services\Ia\IaDocumentoIntegracionService;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoExportService;
use App\Services\OfiArchivo\PlantillaGeneradaService;
use App\Services\OfiArchivo\VariablesPlantillaService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias de integración de borradores de IA con plantillas y formato TipTap/Docx (F2-04).
 */
class IaDocumentoIntegracionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_convertir_texto_plano_a_tiptap_estructura(): void
    {
        $plantillaMock = Mockery::mock(PlantillaGeneradaService::class);
        $variablesMock = Mockery::mock(VariablesPlantillaService::class);
        $exportMock = Mockery::mock(DocumentoExportService::class);

        $service = new IaDocumentoIntegracionService($plantillaMock, $variablesMock, $exportMock);

        $texto = "El Espinal, Tolima, 21 de noviembre de 2026\n\nRespetado ciudadano:\n\nEn atención a su solicitud...";
        $tiptap = $service->convertirTextoATipTap($texto);

        $this->assertIsArray($tiptap);
        $this->assertSame('doc', $tiptap['type']);
        $this->assertCount(3, $tiptap['content']);

        $primerParrafo = $tiptap['content'][0];
        $this->assertSame('paragraph', $primerParrafo['type']);
        $this->assertSame('El Espinal, Tolima, 21 de noviembre de 2026', $primerParrafo['content'][0]['text']);
    }

    public function test_convertir_texto_con_marcadores_anti_alucinacion(): void
    {
        $plantillaMock = Mockery::mock(PlantillaGeneradaService::class);
        $variablesMock = Mockery::mock(VariablesPlantillaService::class);
        $exportMock = Mockery::mock(DocumentoExportService::class);

        $service = new IaDocumentoIntegracionService($plantillaMock, $variablesMock, $exportMock);

        $texto = 'En respuesta al radicado [VERIFICAR DATO: número radicado], informamos que [DATO NO DISPONIBLE].';
        $tiptap = $service->convertirTextoATipTap($texto);

        $this->assertCount(1, $tiptap['content']);
        $textoExtraido = $tiptap['content'][0]['content'][0]['text'];
        $this->assertStringContainsString('[VERIFICAR DATO: número radicado]', $textoExtraido);
        $this->assertStringContainsString('[DATO NO DISPONIBLE]', $textoExtraido);
    }

    public function test_fusionar_contenido_con_plantilla_vacia(): void
    {
        $plantillaMock = Mockery::mock(PlantillaGeneradaService::class);
        $variablesMock = Mockery::mock(VariablesPlantillaService::class);
        $exportMock = Mockery::mock(DocumentoExportService::class);

        $service = new IaDocumentoIntegracionService($plantillaMock, $variablesMock, $exportMock);

        $borrador = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Párrafo borrador']]],
            ],
        ];

        $resultado = $service->fusionarContenidoConPlantilla([], $borrador);
        $this->assertSame($borrador, $resultado);
    }

    public function test_crear_documento_request_validaciones(): void
    {
        $request = new CrearDocumentoDesdeBorradorIaRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('titulo', $rules);
        $this->assertArrayHasKey('borrador', $rules);
        $this->assertContains('required', $rules['titulo']);
        $this->assertContains('required', $rules['borrador']);
        $this->assertContains('min:3', $rules['titulo']);
        $this->assertContains('min:10', $rules['borrador']);

        $messages = $request->messages();
        $this->assertArrayHasKey('titulo.required', $messages);
        $this->assertArrayHasKey('borrador.required', $messages);
    }
}
