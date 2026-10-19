<?php

namespace App\Http\Controllers\OfiArchivo;

use App\Helpers\ArchivoHelper;
use App\Helpers\CalendarioHelper;
use App\Helpers\ExpedienteHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\OfiArchivo\CerrarExpedienteRequest;
use App\Http\Requests\OfiArchivo\IncorporarDocumentoExpedienteRequest;
use App\Http\Requests\OfiArchivo\ListarExpedientesRequest;
use App\Http\Requests\OfiArchivo\StoreExpedienteRequest;
use App\Http\Requests\OfiArchivo\SubirArchivosExpedienteRequest;
use App\Http\Requests\OfiArchivo\UpdateExpedienteRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\OfiArchivo\IndiceExpedienteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OfiArchivoExpedienteController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con protección de permisos (Spatie).
     */
    public function __construct(private IndiceExpedienteService $indiceService)
    {
        $this->middleware('auth:sanctum');
        // Los permisos se validarán en cada método según la Serie TRD
    }

    /**
     * Registra la apertura de un nuevo expediente electrónico/híbrido.
     *
     * @return JsonResponse
     */
    public function store(StoreExpedienteRequest $request)
    {

        if (! OfiArchivoExpediente::puedeUsarDependencia(Auth::user(), $request->dependencia_id)) {
            return $this->forbiddenResponse('No puede abrir expedientes de otra dependencia');
        }
        if ($error = $this->serieInvalida((int) $request->serie_trd_id, (int) $request->dependencia_id)) {
            return $this->warningResponse($error);
        }

        try {
            DB::beginTransaction();

            $numeroExpediente = ExpedienteHelper::generarNumeroExpediente(
                $request->dependencia_id,
                $request->serie_trd_id
            );

            $expediente = OfiArchivoExpediente::create([
                'numero_expediente' => $numeroExpediente,
                'nombre_expediente' => $request->nombre_expediente,
                'anio' => (int) CalendarioHelper::hoy()->year,
                'dependencia_id' => $request->dependencia_id,
                'serie_trd_id' => $request->serie_trd_id,
                'estado' => 'abierto',
                'fecha_apertura' => CalendarioHelper::hoy()->toDateString(),
                'ubicacion_fisica' => $request->ubicacion_fisica,
                'caja' => $request->caja,
                'carpeta' => $request->carpeta,
                'folios_fisicos' => $request->folios_fisicos ?? 0,
                'observaciones_generales' => $request->observaciones_generales,
                'usuario_apertura_id' => Auth::id(),
            ]);

            DB::commit();

            return $this->createdResponse(
                $expediente->load(['dependencia', 'serieTrd']),
                'Expediente abierto exitosamente'
            );

        } catch (\Exception $e) {
            // B25: también al relanzar un 404, para no dejar la transacción abierta
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al abrir el expediente', $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->with([
                'dependencia',
                'serieTrd',
                'usuarioApertura',
                'documentos' => function ($q) {
                    $q->where('activo', true)->orderBy('tipo_documental')->orderBy('orden');
                },
            ])->findOrFail($id);

            return $this->successResponse($expediente, 'Expediente obtenido exitosamente');

        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el expediente', $e->getMessage());
        }
    }

    public function update(UpdateExpedienteRequest $request, $id)
    {

        try {
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($id);

            // M08-05: un expediente cerrado o transferido conserva su clasificación y su hash_indice
            if ($expediente->estado !== 'abierto') {
                return $this->warningResponse('Solo se pueden editar expedientes en estado abierto');
            }
            if ($request->filled('dependencia_id')
                && ! OfiArchivoExpediente::puedeUsarDependencia(Auth::user(), $request->dependencia_id)) {
                return $this->forbiddenResponse('No puede asignar el expediente a otra dependencia');
            }
            if ($request->filled('serie_trd_id') || $request->filled('dependencia_id')) {
                $error = $this->serieInvalida(
                    (int) ($request->serie_trd_id ?: $expediente->serie_trd_id),
                    (int) ($request->dependencia_id ?: $expediente->dependencia_id)
                );
                if ($error) {
                    return $this->warningResponse($error);
                }
            }

            // M08-16: array_filter descartaba 0 y vacíos; los datos físicos se pueden poner en 0 o vaciar,
            // y los obligatorios solo cambian si llegan con valor
            $fields = array_filter(
                $request->only(['nombre_expediente', 'dependencia_id', 'serie_trd_id']),
                fn ($valor) => filled($valor)
            ) + $request->only(['ubicacion_fisica', 'caja', 'carpeta', 'folios_fisicos', 'observaciones_generales']);

            if (array_key_exists('folios_fisicos', $fields)) {
                $fields['folios_fisicos'] = (int) ($fields['folios_fisicos'] ?? 0);
            }

            $expediente->update($fields);

            return $this->successResponse(
                $expediente->load(['dependencia', 'serieTrd']),
                'Expediente actualizado exitosamente'
            );

        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al actualizar el expediente', $e->getMessage());
        }
    }

    public function cerrarExpediente(CerrarExpedienteRequest $request, $id)
    {

        try {
            DB::beginTransaction();

            // B25: bloqueo para que dos cierres simultáneos no calculen el hash dos veces
            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->lockForUpdate()->findOrFail($id);

            if ($expediente->estado !== 'abierto') {
                DB::rollBack();

                return $this->warningResponse('Solo se pueden cerrar expedientes en estado abierto');
            }

            // M08-09: mismas filas que el índice PDF/XML, para que el hash sea verificable
            $hashIndice = $this->indiceService->hash($this->indiceService->filas($expediente));

            $expediente->update([
                'estado' => 'cerrado',
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
            // B25: también al relanzar un 404, para no dejar la transacción abierta
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al cerrar el expediente', $e->getMessage());
        }
    }

    /**
     * Lista expedientes con filtros por dependencia y serie (Cumplimiento ISO 27001).
     */
    public function index(ListarExpedientesRequest $request)
    {

        try {
            $query = OfiArchivoExpediente::visiblesPara(Auth::user())->with(['dependencia', 'serieTrd', 'usuarioApertura']);

            // Filtros opcionales
            if ($request->filled('dependencia_id')) {
                $query->where('dependencia_id', $request->dependencia_id);
            }

            if ($request->filled('serie_trd_id')) {
                $query->where('serie_trd_id', $request->serie_trd_id);
            }

            // B03: el filtro de estado de la lista se ignoraba
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('numero_expediente', 'like', "%{$request->search}%")
                        ->orWhere('nombre_expediente', 'like', "%{$request->search}%");
                });
            }

            $perPage = min($request->get('per_page', 15), 100);
            $expedientes = $query->latest()->paginate($perPage);

            return $this->successResponse($expedientes, 'Listado de expedientes obtenido');

        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al obtener el listado', $e->getMessage());
        }
    }

    /**
     * Incorpora un documento (Radicado Recibido/Enviado) al expediente.
     * Implementa foliación automática e integridad ISO 27001.
     */
    public function incorporarDocumento(IncorporarDocumentoExpedienteRequest $request, $expedienteId)
    {

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($expedienteId);

            if ($expediente->estado !== 'abierto') {
                DB::rollBack();

                return $this->warningResponse('No se pueden incorporar documentos a un expediente cerrado o transferido');
            }

            // Mapear el tipo de documento al modelo correspondiente
            $modelMap = [
                'radicado_recibido' => VentanillaRadicaReci::class,
                'radicado_enviado' => VentanillaRadicaEnviados::class,
            ];

            $modelClass = $modelMap[$request->documentable_type];
            $documento = $this->radicadoAccesible($modelClass, (int) $request->documentable_id);
            if (! $documento) {
                DB::rollBack();

                return $this->notFoundResponse('Radicado no encontrado');
            }

            // Validar si el documento ya está en el expediente (Evitar duplicidad de folios)
            $existe = OfiArchivoExpedienteDocumento::where('expediente_id', $expedienteId)
                ->where('documentable_id', $request->documentable_id)
                ->where('documentable_type', $modelClass)
                ->exists();

            if ($existe) {
                DB::rollBack();

                return $this->warningResponse('Este documento ya se encuentra incorporado en el expediente');
            }

            // Crear el registro de índice (La foliación es automática por el boot() del modelo)
            $expedienteDocumento = OfiArchivoExpedienteDocumento::create([
                'expediente_id' => $expedienteId,
                'documentable_id' => $request->documentable_id,
                'documentable_type' => $modelClass,
                // M08-09: la huella del radicado queda fijada en el folio al incorporarlo
                'hash_sha256' => $documento->hash_sha256 ?? null,
                'tipo_documental' => $request->detalle ?: 'Documento incorporado',
                'asunto' => $request->detalle ?: 'Documento incorporado',
                'detalle' => $request->detalle,
                'usuario_id' => Auth::id(),
                'fecha_incorporacion' => now(),
                'orden' => 0,
                'fecha_documento' => now()->toDateString(),
            ]);

            DB::commit();

            return $this->successResponse(
                $expedienteDocumento->load('documentable'),
                'Documento incorporado y foliado exitosamente como Folio #'.$expedienteDocumento->numero_folio
            );

        } catch (\Exception $e) {
            // B25: también al relanzar un 404, para no dejar la transacción abierta
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al incorporar el documento', $e->getMessage());
        }
    }

    /**
     * Sube archivos directamente al expediente (por tipo documental o como archivo completo).
     * Implementa foliación automática e integridad ISO 27001.
     */
    public function subirArchivos(SubirArchivosExpedienteRequest $request, $expedienteId)
    {

        $disk = 'archivo_expedientes';
        // M08-02: binarios ya escritos; si la transacción se revierte se borran para no dejar huérfanos
        $guardados = [];

        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($expedienteId);

            if ($expediente->estado !== 'abierto') {
                DB::rollBack();

                return $this->warningResponse('No se pueden subir archivos a un expediente cerrado o transferido');
            }

            $documentosCreados = [];

            foreach ($request->file('archivos') as $archivo) {
                // Validar archivo seguro
                $validacion = ArchivoHelper::validarArchivoSeguro($archivo);
                if (! $validacion['valido']) {
                    DB::rollBack();
                    Storage::disk($disk)->delete($guardados);

                    return $this->warningResponse(
                        "Archivo no válido: {$archivo->getClientOriginalName()}",
                        $validacion['error'] ?? 'Error de validación'
                    );
                }

                // Guardar archivo con hash SHA-256
                $uploadData = ArchivoHelper::guardarArchivoConHash(
                    $this->wrapFileRequest($archivo),
                    'archivo',
                    $disk
                );
                if (! empty($uploadData['path'])) {
                    $guardados[] = $uploadData['path'];
                }

                if (! $uploadData || empty($uploadData['path'])) {
                    DB::rollBack();
                    Storage::disk($disk)->delete($guardados);

                    return $this->serverErrorResponse(
                        "Error al guardar el archivo: {$archivo->getClientOriginalName()}"
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
                    'tipo_documental' => $request->tipo_documental ?? ($request->tipo === 'expediente_completo' ? 'Expediente completo' : 'Documento'),
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
                    'orden' => 0,
                ]);

                $documentosCreados[] = $doc;
            }

            DB::commit();

            return $this->createdResponse(
                $documentosCreados,
                count($documentosCreados).' archivo(s) subido(s) y foliado(s) exitosamente'
            );

        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            DB::rollBack();
            Storage::disk($disk)->delete($guardados);
            if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al subir los archivos', $e->getMessage());
        }
    }

    /**
     * M08-13/M09-07: el expediente se clasifica en una Serie o SubSerie de la TRD vigente (versión
     * aprobada) de su propia dependencia; no en un tipo documental, otra dependencia o una TRD histórica.
     */
    private function serieInvalida(int $serieId, int $dependenciaId): ?string
    {
        $serie = ClasificacionDocumentalTRD::with('versionTRD')->find($serieId);

        return match (true) {
            ! $serie => 'La serie TRD seleccionada no existe.',
            ! in_array($serie->tipo, ['Serie', 'SubSerie'], true) => 'El expediente debe clasificarse en una Serie o SubSerie, no en un tipo documental.',
            (int) $serie->dependencia_id !== $dependenciaId => 'La serie TRD seleccionada no pertenece a la dependencia del expediente.',
            $serie->versionTRD?->estado_version !== 'ACTIVO' => 'La serie TRD seleccionada no pertenece a la versión vigente (aprobada) de la TRD.',
            default => null,
        };
    }

    /**
     * M08-04: solo se incorporan radicados que el usuario puede consultar; el archivo central
     * (PERMISO_VER_TODOS) incorpora cualquiera.
     */
    private function radicadoAccesible(string $modelClass, int $id): ?Model
    {
        $user = Auth::user();
        if (OfiArchivoExpediente::puedeVerTodos($user)) {
            return $modelClass::find($id);
        }

        if ($modelClass === VentanillaRadicaReci::class) {
            return VentanillaRadicaReci::conPermisoJerarquico($user)->find($id);
        }

        return VentanillaRadicaEnviados::conPermisoJerarquico($user)->find($id);
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
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->with(['dependencia', 'serieTrd'])->findOrFail($id);

        return response($this->indiceService->pdf($expediente))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Indice_Expediente_'.$expediente->numero_expediente.'.pdf"');
    }

    /**
     * Índice electrónico en XML (Acuerdo 003 de 2015 AGN), con las mismas filas y hash que el PDF.
     */
    public function generarIndiceXml($id)
    {
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->with(['dependencia', 'serieTrd'])->findOrFail($id);

        return response($this->indiceService->xml($expediente))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="Indice_Expediente_'.$expediente->numero_expediente.'.xml"');
    }

    /**
     * Borrado lógico de un documento del expediente (ISO 27001 A.12.4.1)
     */
    public function softDeleteDocumento($expedienteId, $documentoId)
    {
        try {
            DB::beginTransaction();

            $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($expedienteId);

            // M08-05: los folios de un expediente cerrado o transferido no se retiran
            if ($expediente->estado !== 'abierto') {
                DB::rollBack();

                return $this->warningResponse('No se pueden retirar documentos de un expediente cerrado o transferido');
            }

            $doc = OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)
                ->findOrFail($documentoId);

            // Ya retirado: un segundo retiro descontaría otra vez total_folios_elec
            if (! $doc->activo) {
                DB::rollBack();

                return $this->warningResponse('El documento ya fue retirado del expediente');
            }

            // M08-06: borrado lógico real. El binario se conserva para que el folio inactivo
            // siga siendo verificable contra su hash_sha256 (antes se eliminaba del disco).
            $doc->delete();

            DB::commit();

            return $this->successResponse(null, 'Documento eliminado exitosamente');
        } catch (\Exception $e) {
            // B25: también al relanzar un 404, para no dejar la transacción abierta
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al eliminar el documento', $e->getMessage());
        }
    }
}
