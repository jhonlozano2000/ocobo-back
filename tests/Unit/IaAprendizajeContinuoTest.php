<?php

namespace Tests\Unit;

use App\Services\Ia\IaAprendizajeContinuoService;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias para el servicio de Aprendizaje Continuo Human-in-the-Loop (HITL) F4-01.
 */
class IaAprendizajeContinuoTest extends TestCase
{
    protected IaAprendizajeContinuoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IaAprendizajeContinuoService;
    }

    public function test_constantes_de_umbrales_hitl(): void
    {
        $this->assertEquals(0.4000, IaAprendizajeContinuoService::UMBRAL_CANDIDATO_SFT);
        $this->assertEquals(0.7000, IaAprendizajeContinuoService::UMBRAL_EDICION_MODERADA);
    }

    public function test_extraer_texto_plano_de_tiptap_estructura(): void
    {
        $contenido = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 1],
                    'content' => [
                        ['type' => 'text', 'text' => 'MEMORANDO OFICIAL'],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'En atención a su solicitud, informamos el avance del trámite.'],
                    ],
                ],
                [
                    'type' => 'bulletList',
                    'content' => [
                        [
                            'type' => 'listItem',
                            'content' => [
                                [
                                    'type' => 'paragraph',
                                    'content' => [['type' => 'text', 'text' => 'Punto uno cumplido']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $texto = $this->service->extraerTextoPlanoDeTipTap($contenido);

        $this->assertStringContainsString('MEMORANDO OFICIAL', $texto);
        $this->assertStringContainsString('En atención a su solicitud, informamos el avance del trámite.', $texto);
        $this->assertStringContainsString('Punto uno cumplido', $texto);
    }

    public function test_normalizar_texto_elimina_espacios_y_tags(): void
    {
        $crudo = "  <p>Texto   con   múltiples     espacios</p>\r\n\r\n\r\n\r\nNueva   línea.  ";
        $norm = $this->service->normalizarTexto($crudo);

        $this->assertEquals("Texto con múltiples espacios\n\nNueva línea.", $norm);
    }

    public function test_calcular_edit_ratio_textos_identicos_retorna_cero(): void
    {
        $texto = 'Oficio institucional dirigido a la entidad correspondiente.';
        $res = $this->service->calcularEditRatio($texto, $texto);

        $this->assertEquals(0.0000, $res['edit_ratio']);
        $this->assertEquals(1.0000, $res['similar_ratio']);
        $this->assertEquals('capturado_candidato_sft', $res['estado_aprendizaje']);
    }

    public function test_calcular_edit_ratio_edicion_menor_clasifica_candidato_sft(): void
    {
        $borrador = 'En atención al radicado 2026-001 se remite copia de la historia clínica en 10 folios.';
        // Modificación menor: el funcionario ajustó el número de folios y agregó una frase breve
        $final = 'En atención al radicado 2026-001 se remite copia de la historia clínica en 15 folios digitales.';

        $res = $this->service->calcularEditRatio($borrador, $final);

        $this->assertLessThanOrEqual(0.4000, $res['edit_ratio']);
        $this->assertEquals('capturado_candidato_sft', $res['estado_aprendizaje']);
        $this->assertGreaterThan(0.6000, $res['similar_ratio']);
    }

    public function test_calcular_edit_ratio_edicion_moderada_clasifica_supervision(): void
    {
        $borrador = "Estimado usuario:\nEn respuesta a su solicitud de información sobre facturación radicada en días pasados, le informamos que su trámite se encuentra en auditoría médica.";
        $final = "Estimado usuario:\nEn respuesta a su solicitud formal sobre glosas de facturación radicada el día de ayer, le comunicamos que el trámite fue remitido a la gerencia financiera para revisión especial y soporte.";

        $res = $this->service->calcularEditRatio($borrador, $final);

        $this->assertGreaterThan(0.2000, $res['edit_ratio']);
        $this->assertLessThanOrEqual(0.7000, $res['edit_ratio']);
        $this->assertContains($res['estado_aprendizaje'], ['capturado_candidato_sft', 'capturado_edicion_moderada']);
    }

    public function test_calcular_edit_ratio_reescritura_clasifica_descartado_alta_edicion(): void
    {
        $borrador = 'Solicitud de información sobre historias clínicas de paciente fallecido.';
        $final = 'Por medio de la presente nos permitimos rechazar totalmente la factura N° 99823 por glosa no subsanada.';

        $res = $this->service->calcularEditRatio($borrador, $final);

        $this->assertGreaterThan(0.7000, $res['edit_ratio']);
        $this->assertEquals('descartado_alta_edicion', $res['estado_aprendizaje']);
    }

    public function test_calcular_edit_ratio_textos_vacios(): void
    {
        $res = $this->service->calcularEditRatio('', '');

        $this->assertEquals(0.0000, $res['edit_ratio']);
        $this->assertEquals('descartado_vacio', $res['estado_aprendizaje']);
    }
}
