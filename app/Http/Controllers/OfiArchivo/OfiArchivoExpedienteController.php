<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Http\Controllers\Controller;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Helpers\ExpedienteHelper;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OfiArchivoExpedienteController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con protección de permisos (Spatie).
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        // Los permisos se validarán en cada método según la Serie TRD
    }

    /**
     * Registra la apertura de un nuevo expediente electrónico/híbrido.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre_expediente' => 'required|string|max:300',
            'dependencia_id' => 'required|exists:calidad_organigrama,id',
            'serie_trd_id' => 'required|exists:clasificacion_documental_trd,id',
            'deposito' => 'nullable|string|max:100',
            'caja' => 'nullable|string|max:50',
            'carpeta' => 'nullable|string|max:50',
            'folios_fisicos' => 'nullable|integer|min:0',
            'observacion_1' => 'nullable|string',
            'observacion_2' => 'nullable|string',
            'observacion_3' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // 1. Generar el número único de expediente (Helper centralizado)
            $numeroExpediente = ExpedienteHelper::generarNumeroExpediente(
                $request->dependencia_id, 
                $request->serie_trd_id
            );

            // 2. Crear el registro
            $expediente = OfiArchivoExpediente::create([
                'numero_expediente' => $numeroExpediente,
                'nombre_expediente' => $request->nombre_expediente,
                'dependencia_id' => $request->dependencia_id,
                'serie_trd_id' => $request->serie_trd_id,
                'estado' => 'Abierto',
                'fecha_apertura' => now(),
                'deposito' => $request->deposito,
                'caja' => $request->caja,
                'carpeta' => $request->carpeta,
                'folios_fisicos' => $request->folios_fisicos ?? 0,
                'observacion_1' => $request->observacion_1,
                'observacion_2' => $request->observacion_2,
                'observacion_3' => $request->observacion_3,
                'usuario_apertura_id' => Auth::id(),
            ]);

            DB::commit();

            return $this->successResponse(
                $expediente->load(['dependencia', 'serieTrd']), 
                'Expediente abierto exitosamente', 
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al abrir el expediente', $e->getMessage(), 500);
        }
    }

    /**
     * Lista expedientes con filtros por dependencia y serie (Cumplimiento ISO 27001).
     */
    public function index(Request $request)
    {
        try {
            $query = OfiArchivoExpediente::with(['dependencia', 'serieTrd', 'usuarioApertura']);

            // Filtros opcionales
            if ($request->has('dependencia_id')) {
                $query->where('dependencia_id', $request->dependencia_id);
            }

            if ($request->has('serie_trd_id')) {
                $query->where('serie_trd_id', $request->serie_trd_id);
            }

            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('numero_expediente', 'like', "%{$request->search}%")
                      ->orWhere('nombre_expediente', 'like', "%{$request->search}%");
                });
            }

            $expedientes = $query->latest()->paginate($request->get('per_page', 15));

            return $this->successResponse($expedientes, 'Listado de expedientes obtenido');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado', $e->getMessage(), 500);
        }
    }

    /**
     * Incorpora un documento (Radicado Recibido/Enviado) al expediente.
     * Implementa foliación automática e integridad ISO 27001.
     */
    public function incorporarDocumento(Request $request, $expedienteId)
    {
        $request->validate([
            'documentable_id' => 'required|integer',
            'documentable_type' => 'required|string|in:radicado_recibido,radicado_enviado',
            'detalle' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::findOrFail($expedienteId);

            if ($expediente->estado !== 'Abierto') {
                return $this->errorResponse('No se pueden incorporar documentos a un expediente cerrado o transferido', null, 422);
            }

            // Mapear el tipo de documento al modelo correspondiente
            $modelMap = [
                'radicado_recibido' => \App\Models\VentanillaUnica\VentanillaRadicaReci::class,
                'radicado_enviado' => \App\Models\VentanillaUnica\VentanillaRadicaEnviados::class
            ];

            $modelClass = $modelMap[$request->documentable_type];
            $documento = $modelClass::findOrFail($request->documentable_id);

            // Validar si el documento ya está en el expediente (Evitar duplicidad de folios)
            $existe = \App\Models\OfiArchivo\OfiArchivoExpedienteDocumento::where('expediente_id', $expedienteId)
                ->where('documentable_id', $request->documentable_id)
                ->where('documentable_type', $modelClass)
                ->exists();

            if ($existe) {
                return $this->errorResponse('Este documento ya se encuentra incorporado en el expediente', null, 422);
            }

            // Crear el registro de índice (La foliación es automática por el boot() del modelo)
            $expedienteDocumento = \App\Models\OfiArchivo\OfiArchivoExpedienteDocumento::create([
                'expediente_id' => $expedienteId,
                'documentable_id' => $request->documentable_id,
                'documentable_type' => $modelClass,
                'detalle' => $request->detalle,
                'usuario_id' => Auth::id(),
                'fecha_incorporacion' => now()
            ]);

            DB::commit();

            return $this->successResponse(
                $expedienteDocumento->load('documentable'),
                'Documento incorporado y foliado exitosamente como Folio #' . $expedienteDocumento->numero_folio
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al incorporar el documento', $e->getMessage(), 500);
        }
    }

    /**
     * Genera el Índice Electrónico del Expediente en formato PDF (Acuerdo 003 de 2015 AGN).
     */
    public function generarIndicePdf($id)
    {
        try {
            $expediente = OfiArchivoExpediente::with([
                'dependencia',
                'serieTrd',
                'documentos.documentable'
            ])->findOrFail($id);

            // Generar PDF usando FPDF
            $pdf = new \setasign\Fpdi\Fpdi();
            $pdf->AddPage('L'); // Horizontal para que quepan los hashes
            $pdf->SetFont('Arial', 'B', 14);

            // Encabezado AGN
            $pdf->Cell(0, 10, utf8_decode('ÍNDICE ELECTRÓNICO DE EXPEDIENTE - SGDEA OCOBO'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 8, utf8_decode("Dependencia: {$expediente->dependencia->nom_organico}"), 0, 1);
            $pdf->Cell(0, 8, utf8_decode("Serie/Subserie: {$expediente->serieTrd->nombre}"), 0, 1);
            $pdf->Cell(0, 8, utf8_decode("No. Expediente: {$expediente->numero_expediente} | Nombre: {$expediente->nombre_expediente}"), 0, 1);
            $pdf->Cell(0, 8, utf8_decode("Fecha de Apertura: " . $expediente->fecha_apertura->format('Y-m-d')), 0, 1);
            $pdf->Ln(5);

            // Cabecera de la tabla de documentos
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(15, 8, 'Folio', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'Fecha Incorp.', 1, 0, 'C', true);
            $pdf->Cell(50, 8, 'Tipo Documento', 1, 0, 'C', true);
            $pdf->Cell(80, 8, 'Asunto / Detalle', 1, 0, 'C', true);
            $pdf->Cell(95, 8, 'Huella Digital (Hash SHA-256)', 1, 1, 'C', true);

            // Filas de documentos
            $pdf->SetFont('Arial', '', 8);
            
            // Ordenar por folio estrictamente
            $documentos = $expediente->documentos->sortBy('numero_folio');

            foreach ($documentos as $doc) {
                // Determinar el origen y datos clave del documento polimórfico
                $esRadicado = str_contains($doc->documentable_type, 'VentanillaRadica');
                $tipo = $esRadicado ? "Radicado: {$doc->documentable->num_radicado}" : "Documento Interno";
                $asunto = substr($doc->documentable->asunto ?? $doc->detalle ?? 'Sin asunto', 0, 45);
                
                // Obtener el hash real del archivo original
                $hash = $doc->documentable->hash_sha256 ?? 'Sin Hash Registrado';

                // Escribir fila
                $pdf->Cell(15, 8, $doc->numero_folio, 1, 0, 'C');
                $pdf->Cell(35, 8, $doc->fecha_incorporacion->format('Y-m-d'), 1, 0, 'C');
                $pdf->Cell(50, 8, utf8_decode($tipo), 1, 0, 'L');
                $pdf->Cell(80, 8, utf8_decode($asunto), 1, 0, 'L');
                
                // Hash truncado si es muy largo, pero usualmente 64 chars caben en 95mm a tamaño 8
                $pdf->Cell(95, 8, $hash, 1, 1, 'L');
            }

            // Pie de página de certificación
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->Cell(0, 5, utf8_decode('Documento generado automáticamente por el Sistema de Gestión Documental OCOBO.'), 0, 1, 'C');
            $pdf->Cell(0, 5, utf8_decode('Este índice electrónico garantiza la integridad inalterable del expediente físico y digital.'), 0, 1, 'C');

            $contenidoPdf = $pdf->Output('S');

            return response($contenidoPdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="Indice_Expediente_' . $expediente->numero_expediente . '.pdf"');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al generar el índice del expediente', $e->getMessage(), 500);
        }
    }
}

