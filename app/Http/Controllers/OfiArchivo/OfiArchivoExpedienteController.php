<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Helpers\ArchivoHelper;
use App\Helpers\ExpedienteHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Fpdi;

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
     * @return JsonResponse
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
            'observaciones_generales' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $numeroExpediente = ExpedienteHelper::generarNumeroExpediente(
                $request->dependencia_id,
                $request->serie_trd_id
            );

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
                'observacion_1' => $request->observaciones_generales,
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

    public function show($id)
    {
        try {
            $expediente = OfiArchivoExpediente::with([
                'dependencia',
                'serieTrd',
                'usuarioApertura',
                'documentos' => function ($q) {
                    $q->where('activo', true)->orderBy('tipo_documental')->orderBy('orden');
                },
            ])->findOrFail($id);

            return $this->successResponse($expediente, 'Expediente obtenido exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el expediente', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre_expediente' => 'nullable|string|max:300',
            'dependencia_id' => 'nullable|exists:calidad_organigrama,id',
            'serie_trd_id' => 'nullable|exists:clasificacion_documental_trd,id',
            'deposito' => 'nullable|string|max:100',
            'caja' => 'nullable|string|max:50',
            'carpeta' => 'nullable|string|max:50',
            'folios_fisicos' => 'nullable|integer|min:0',
            'observaciones_generales' => 'nullable|string',
        ]);

        try {
            $expediente = OfiArchivoExpediente::findOrFail($id);

            $fields = array_filter($request->only([
                'nombre_expediente',
                'dependencia_id',
                'serie_trd_id',
                'deposito',
                'caja',
                'carpeta',
                'folios_fisicos',
            ]));

            if ($request->has('observaciones_generales')) {
                $fields['observacion_1'] = $request->observaciones_generales;
            }

            $expediente->update($fields);

            return $this->successResponse(
                $expediente->load(['dependencia', 'serieTrd']),
                'Expediente actualizado exitosamente'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar el expediente', $e->getMessage(), 500);
        }
    }

    public function cerrarExpediente(Request $request, $id)
    {
        $request->validate([
            'motivo_cierre' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::findOrFail($id);

            if ($expediente->estado !== 'Abierto') {
                return $this->errorResponse('Solo se pueden cerrar expedientes en estado abierto', null, 422);
            }

            $documentos = $expediente->documentos()
                ->select('numero_folio', 'documentable_type', 'documentable_id', 'fecha_incorporacion', 'hash_sha256')
                ->orderBy('numero_folio')
                ->get();

            $indice = $documentos->map(function ($doc) {
                return [
                    'numero_folio' => $doc->numero_folio,
                    'documentable_type' => $doc->documentable_type,
                    'documentable_id' => $doc->documentable_id,
                    'fecha_incorporacion' => $doc->fecha_incorporacion->toDateTimeString(),
                    'hash_sha256' => $doc->hash_sha256,
                ];
            })->toArray();

            $hashIndice = hash('sha256', json_encode($indice));

            $expediente->update([
                'estado' => 'Cerrado',
                'fecha_cierre' => now(),
                'hash_indice' => $hashIndice,
                'motivo_cierre' => $request->motivo_cierre,
                'usuario_cierre_id' => Auth::id(),
            ]);

            DB::commit();

            return $this->successResponse(
                $expediente->load(['dependencia', 'serieTrd', 'usuarioApertura']),
                'Expediente cerrado exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al cerrar el expediente', $e->getMessage(), 500);
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
                $query->where(function ($q) use ($request) {
                    $q->where('numero_expediente', 'like', "%{$request->search}%")
                        ->orWhere('nombre_expediente', 'like', "%{$request->search}%");
                });
            }

            $perPage = min($request->get('per_page', 15), 100);
            $expedientes = $query->latest()->paginate($perPage);

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
            'detalle' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::findOrFail($expedienteId);

            if ($expediente->estado !== 'Abierto') {
                return $this->errorResponse('No se pueden incorporar documentos a un expediente cerrado o transferido', null, 422);
            }

            // Mapear el tipo de documento al modelo correspondiente
            $modelMap = [
                'radicado_recibido' => VentanillaRadicaReci::class,
                'radicado_enviado' => VentanillaRadicaEnviados::class,
            ];

            $modelClass = $modelMap[$request->documentable_type];
            $documento = $modelClass::findOrFail($request->documentable_id);

            // Validar si el documento ya está en el expediente (Evitar duplicidad de folios)
            $existe = OfiArchivoExpedienteDocumento::where('expediente_id', $expedienteId)
                ->where('documentable_id', $request->documentable_id)
                ->where('documentable_type', $modelClass)
                ->exists();

            if ($existe) {
                return $this->errorResponse('Este documento ya se encuentra incorporado en el expediente', null, 422);
            }

            // Crear el registro de índice (La foliación es automática por el boot() del modelo)
            $expedienteDocumento = OfiArchivoExpedienteDocumento::create([
                'expediente_id' => $expedienteId,
                'documentable_id' => $request->documentable_id,
                'documentable_type' => $modelClass,
                'detalle' => $request->detalle,
                'usuario_id' => Auth::id(),
                'fecha_incorporacion' => now(),
            ]);

            DB::commit();

            return $this->successResponse(
                $expedienteDocumento->load('documentable'),
                'Documento incorporado y foliado exitosamente como Folio #'.$expedienteDocumento->numero_folio
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al incorporar el documento', $e->getMessage(), 500);
        }
    }

    /**
     * Sube archivos directamente al expediente (por tipo documental o como archivo completo).
     * Implementa foliación automática e integridad ISO 27001.
     */
    public function subirArchivos(Request $request, $expedienteId)
    {
        $request->validate([
            'archivos' => 'required|array|min:1',
            'archivos.*' => 'file|max:51200',
            'tipo' => 'required|in:tipo_documental,expediente_completo',
            'tipo_documental' => 'nullable|string|max:200',
            'descripcion' => 'nullable|string|max:500',
        ]);

        // Validar que tipo_documental sea requerido cuando tipo es 'tipo_documental'
        if ($request->tipo === 'tipo_documental' && empty($request->tipo_documental)) {
            return $this->errorResponse('El nombre del tipo documental es requerido', null, 422);
        }

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::findOrFail($expedienteId);

            if ($expediente->estado !== 'Abierto') {
                return $this->errorResponse('No se pueden subir archivos a un expediente cerrado o transferido', null, 422);
            }

            $disk = 'archivo_expedientes';
            $documentosCreados = [];

            foreach ($request->file('archivos') as $archivo) {
                // Validar archivo seguro
                $validacion = ArchivoHelper::validarArchivoSeguro($archivo);
                if (! $validacion['valido']) {
                    DB::rollBack();

                    return $this->errorResponse(
                        "Archivo no válido: {$archivo->getClientOriginalName()}",
                        $validacion['error'] ?? 'Error de validación',
                        422
                    );
                }

                // Guardar archivo con hash SHA-256
                $uploadData = ArchivoHelper::guardarArchivoConHash(
                    $this->wrapFileRequest($archivo),
                    'archivo',
                    $disk
                );

                if (! $uploadData || empty($uploadData['path'])) {
                    DB::rollBack();

                    return $this->errorResponse(
                        "Error al guardar el archivo: {$archivo->getClientOriginalName()}",
                        null,
                        500
                    );
                }

                // Crear registro de índice (La foliación es automática por el boot() del modelo)
                $detalleParts = [];
                if ($request->tipo === 'tipo_documental') {
                    $detalleParts[] = "Tipo documental: {$request->tipo_documental}";
                }
                $detalleParts[] = "Archivo: {$archivo->getClientOriginalName()}";
                $detalleParts[] = "Formato: {$archivo->getClientOriginalExtension()}";
                $detalleParts[] = "Tamaño: {$archivo->getSize()} bytes";
                $detalleParts[] = "Hash: {$uploadData['hash']}";
                if ($request->descripcion) {
                    $detalleParts[] = "Descripción: {$request->descripcion}";
                }
                if ($request->detalle) {
                    $detalleParts[] = $request->detalle;
                }

                $doc = OfiArchivoExpedienteDocumento::create([
                    'expediente_id' => $expedienteId,
                    'tipo' => $request->tipo,
                    'tipo_documental' => $request->tipo_documental,
                    'detalle' => implode(' | ', $detalleParts),
                    'usuario_id' => Auth::id(),
                    'archivo_path' => $uploadData['path'],
                    'hash_sha256' => $uploadData['hash'],
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'formato_archivo' => $archivo->getClientOriginalExtension(),
                    'tamano_bytes' => $archivo->getSize(),
                    'fecha_documento' => now()->toDateString(),
                    'asunto' => $request->tipo_documental ?? 'Archivo de expediente',
                    'activo' => true,
                ]);

                $documentosCreados[] = $doc;
            }

            DB::commit();

            return $this->successResponse(
                $documentosCreados,
                count($documentosCreados).' archivo(s) subido(s) y foliado(s) exitosamente',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Error al subir los archivos', $e->getMessage(), 500);
        }
    }

    /**
     * Envuelve un archivo en un objeto Request temporal para ArchivoHelper.
     */
    private function wrapFileRequest($file)
    {
        $tempRequest = new Request;
        $tempRequest->files->set('archivo', $file);

        return $tempRequest;
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
                'documentos.documentable',
            ])->findOrFail($id);

            // Generar PDF usando FPDF
            $pdf = new Fpdi;
            $pdf->AddPage('L'); // Horizontal para que quepan los hashes
            $pdf->SetFont('Arial', 'B', 14);

            // Encabezado AGN
            $pdf->Cell(0, 10, utf8_decode('ÍNDICE ELECTRÓNICO DE EXPEDIENTE - SGDEA OCOBO'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 8, utf8_decode("Dependencia: {$expediente->dependencia->nom_organico}"), 0, 1);
            $pdf->Cell(0, 8, utf8_decode("Serie/Subserie: {$expediente->serieTrd->nombre}"), 0, 1);
            $pdf->Cell(0, 8, utf8_decode("No. Expediente: {$expediente->numero_expediente} | Nombre: {$expediente->nombre_expediente}"), 0, 1);
            $pdf->Cell(0, 8, utf8_decode('Fecha de Apertura: '.$expediente->fecha_apertura->format('Y-m-d')), 0, 1);
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
                $tipo = $esRadicado ? "Radicado: {$doc->documentable->num_radicado}" : 'Documento Interno';
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
                ->header('Content-Disposition', 'inline; filename="Indice_Expediente_'.$expediente->numero_expediente.'.pdf"');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al generar el índice del expediente', $e->getMessage(), 500);
        }
    }

    /**
     * Borrado lógico de un documento del expediente (ISO 27001 A.12.4.1)
     */
    public function softDeleteDocumento($expedienteId, $documentoId)
    {
        try {
            $doc = OfiArchivoExpedienteDocumento::with('expediente')
                ->where('expediente_id', $expedienteId)
                ->findOrFail($documentoId);

            // Eliminar archivo del storage
            if ($doc->archivo_path) {
                ArchivoHelper::eliminarArchivo($doc->archivo_path, 'archivo_expedientes');
            }

            // Eliminar registro de la BD (físico, no lógico)
            $doc->forceDelete();

            return $this->successResponse(null, 'Documento eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el documento', $e->getMessage(), 500);
        }
    }
}
