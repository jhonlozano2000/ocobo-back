<?php

namespace App\Services\Ia;

use App\Models\Ia\IaBorradorAuditoria;
use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoExportService;
use App\Services\OfiArchivo\PlantillaGeneradaService;
use App\Services\OfiArchivo\VariablesPlantillaService;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de integración entre los borradores de IA y el motor documental de OCOBO.
 *
 * Convierte el texto proyectado por la IA a la estructura TipTap/Yjs del editor colaborativo,
 * resuelve las variables institucionales (combinación de correspondencia) y compila
 * documentos .docx estándar (PhpWord) listos para Word y LibreOffice.
 */
class IaDocumentoIntegracionService
{
    public function __construct(
        protected PlantillaGeneradaService $plantillaService,
        protected VariablesPlantillaService $variablesService,
        protected DocumentoExportService $exportService
    ) {}

    /**
     * Convierte texto plano con párrafos a la estructura de nodos JSON de TipTap.
     *
     * @param  string  $texto  Texto generado por el modelo LLM
     * @return array Estructura TipTap ['type' => 'doc', 'content' => [...]]
     */
    public function convertirTextoATipTap(string $texto): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', trim($texto));
        $parrafos = [];
        $acumulador = [];

        foreach ($lineas as $linea) {
            $limpia = trim($linea);
            if ($limpia === '') {
                if (! empty($acumulador)) {
                    $parrafos[] = $this->crearNodoParrafo(implode(' ', $acumulador));
                    $acumulador = [];
                }
            } else {
                $acumulador[] = $limpia;
            }
        }

        if (! empty($acumulador)) {
            $parrafos[] = $this->crearNodoParrafo(implode(' ', $acumulador));
        }

        if (empty($parrafos)) {
            $parrafos[] = ['type' => 'paragraph', 'content' => []];
        }

        return [
            'type' => 'doc',
            'content' => $parrafos,
        ];
    }

    /**
     * Construye un nodo de párrafo TipTap con marcas de advertencia si contiene [VERIFICAR DATO].
     */
    protected function crearNodoParrafo(string $texto): array
    {
        // Detectar si contiene marcadores [VERIFICAR DATO: ...] o [DATO NO DISPONIBLE]
        if (preg_match('/\[VERIFICAR DATO:[^\]]+\]|\[DATO NO DISPONIBLE\]/i', $texto)) {
            return [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $texto,
                    ],
                ],
            ];
        }

        return [
            'type' => 'paragraph',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $texto,
                ],
            ],
        ];
    }

    /**
     * Fusiona el contenido de una plantilla institucional con el cuerpo proyectado por la IA.
     */
    public function fusionarContenidoConPlantilla(array $contenidoPlantilla, array $nodosBorrador): array
    {
        $bloquesPlantilla = $contenidoPlantilla['content'] ?? [];

        if (empty($bloquesPlantilla)) {
            return $nodosBorrador;
        }

        $bloquesFinales = [];
        $cuerpoInsertado = false;

        foreach ($bloquesPlantilla as $bloque) {
            // Si el bloque es un párrafo con un campo vacío o marcador de cuerpo, reemplazarlo
            $esMarcadorCuerpo = false;
            if (($bloque['type'] ?? '') === 'paragraph' && ! empty($bloque['content'])) {
                foreach ($bloque['content'] as $inline) {
                    if (($inline['type'] ?? '') === 'campo' && in_array(strtolower($inline['attrs']['etiqueta'] ?? ''), ['cuerpo', 'contenido', 'texto'])) {
                        $esMarcadorCuerpo = true;
                        break;
                    }
                }
            }

            if ($esMarcadorCuerpo && ! $cuerpoInsertado) {
                foreach ($nodosBorrador['content'] as $nodoIa) {
                    $bloquesFinales[] = $nodoIa;
                }
                $cuerpoInsertado = true;
            } else {
                $bloquesFinales[] = $bloque;
            }
        }

        // Si la plantilla no tenía un marcador explícito, anexar el cuerpo antes de las firmas
        if (! $cuerpoInsertado) {
            // Buscar si hay firmas al final para insertar el cuerpo antes
            $posicionFirma = count($bloquesFinales);
            for ($i = count($bloquesFinales) - 1; $i >= 0; $i--) {
                $b = $bloquesFinales[$i];
                if (isset($b['content'][0]['type']) && $b['content'][0]['type'] === 'campoGrupo' && ($b['content'][0]['attrs']['rol'] ?? '') === 'firmantes') {
                    $posicionFirma = max(0, $i - 1);
                }
            }

            array_splice($bloquesFinales, $posicionFirma, 0, $nodosBorrador['content']);
        }

        return [
            'type' => 'doc',
            'content' => $bloquesFinales,
        ];
    }

    /**
     * Crea un documento colaborativo en Mi Bandeja a partir del borrador de la IA y la plantilla elegida.
     *
     * @param  array  $datos  Datos validados (titulo, borrador, radica_reci_id, plantilla_id, notas)
     * @param  User  $user  Usuario creador
     */
    public function crearDocumentoColaborativo(array $datos, User $user): Documento
    {
        return DB::transaction(function () use ($datos, $user) {
            $radicado = ! empty($datos['radica_reci_id'])
                ? VentanillaRadicaReci::find($datos['radica_reci_id'])
                : null;

            $plantillaInfo = null;
            if (! empty($datos['plantilla_id'])) {
                $plantillaModelo = OfiArchivoPlantillaGenerada::findOrFail($datos['plantilla_id']);
                $plantillaInfo = $this->plantillaService->paraDocumento($plantillaModelo, $user, $radicado);
            }

            $nodosBorrador = $this->convertirTextoATipTap($datos['borrador']);

            if ($plantillaInfo && ! empty($plantillaInfo['contenido'])) {
                $contenidoFinal = $this->fusionarContenidoConPlantilla($plantillaInfo['contenido'], $nodosBorrador);
                $configPagina = $plantillaInfo['pagina'] ?? [];
            } else {
                $contenidoFinal = $nodosBorrador;
                $configPagina = [
                    'tamano_papel' => 'carta',
                    'orientacion' => 'vertical',
                    'margenes' => ['superior' => 25.4, 'inferior' => 25.4, 'izquierdo' => 25.4, 'derecho' => 25.4],
                ];
            }

            $documento = Documento::create(array_merge([
                'titulo' => $datos['titulo'],
                'notas' => $datos['notas'] ?? 'Borrador proyectado con Asistente de IA OCOBO',
                'es_publico' => false,
                'estado' => 'borrador',
                'user_id' => $user->id,
                'radica_reci_id' => $radicado?->id,
            ], $configPagina));

            Contenido::create([
                'documento_id' => $documento->id,
                'contenido_yjs' => $contenidoFinal,
                'hash_contenido' => hash('sha256', json_encode($contenidoFinal)),
            ]);

            // Si hay un ID de auditoría, asociarlo al documento
            if (! empty($datos['auditoria_id'])) {
                IaBorradorAuditoria::where('id', $datos['auditoria_id'])
                    ->where('user_id', $user->id)
                    ->update([
                        'id_radicado' => $radicado?->num_radicado ?? $datos['id_radicado'] ?? null,
                        'radicado_id' => $radicado?->id,
                    ]);
            }

            return $documento->load(['contenido', 'creador']);
        });
    }

    /**
     * Genera un archivo .docx para descarga directa a partir del borrador y las plantillas institucionales.
     *
     * @return array{ruta: string, nombre: string}
     */
    public function exportarDocxDesdeBorrador(array $datos, User $user): array
    {
        $documento = $this->crearDocumentoColaborativo($datos, $user);
        $rutaDocx = $this->exportService->exportarDocx($documento);
        $nombreDescarga = str_replace(['/', '\\'], '-', $documento->titulo).'.docx';

        return [
            'ruta' => $rutaDocx,
            'nombre' => $nombreDescarga,
            'documento_id' => $documento->id,
        ];
    }
}
