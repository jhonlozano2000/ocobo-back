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
use App\Http\Requests\OfiArchivo\TipificarDocumentoExpedienteRequest;
use App\Http\Requests\OfiArchivo\UpdateExpedienteRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoExpedienteDocumento;
use App\Models\OfiArchivo\OfiArchivoExpedienteIndiceVersion;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\OfiArchivo\ExpedienteEventosService;
use App\Services\OfiArchivo\IndiceExpedienteService;
use App\Services\OfiArchivo\TiposDocumentalesExpediente;
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
    public function __construct(
        private IndiceExpedienteService $indiceService,
        private TiposDocumentalesExpediente $tiposDocumentales,
    ) {
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
        $clasificacion = $this->resolverClasificacion(
            (int) $request->serie_trd_id,
            $request->filled('subserie_trd_id') ? (int) $request->subserie_trd_id : null,
            (int) $request->dependencia_id
        );
        if (is_string($clasificacion)) {
            return $this->warningResponse($clasificacion);
        }
        [$serie, $subserie] = $clasificacion;

        try {
            DB::beginTransaction();

            $numeroExpediente = ExpedienteHelper::generarNumeroExpediente(
                $request->dependencia_id,
                $serie->id,
                $subserie?->id
            );

            $expediente = OfiArchivoExpediente::create([
                'numero_expediente' => $numeroExpediente,
                'nombre_expediente' => $request->nombre_expediente,
                'anio' => (int) CalendarioHelper::hoy()->year,
                'dependencia_id' => $request->dependencia_id,
                'serie_trd_id' => $serie->id,
                'subserie_trd_id' => $subserie?->id,
                'soporte' => $request->soporte,
                'nivel_acceso' => $request->nivel_acceso,
                'fundamento_acceso' => $request->nivel_acceso === 'publico' ? null : $request->fundamento_acceso,
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
                $expediente->load(['dependencia', 'serieTrd', 'subserieTrd']),
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
                'subserieTrd',
                'usuarioApertura',
                'documentos' => function ($q) {
                    $q->where('activo', true)->orderBy('tipo_documental')->orderBy('orden');
                },
            ])->findOrFail($id);

            // B06/D6: qué le falta para estar conforme (nivel de acceso, subserie)
            $expediente->setAttribute('pendientes', $expediente->pendientesDeCompletar());

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
            $reclasifica = $request->filled('serie_trd_id') || $request->filled('subserie_trd_id') || $request->filled('dependencia_id');
            $clasificacion = null;
            if ($reclasifica) {
                // Sin serie nueva se revalida la clasificación actual (la subserie, si la tiene)
                $nodo = $request->serie_trd_id ?: ($request->filled('subserie_trd_id') ? $expediente->serie_trd_id : ($expediente->subserie_trd_id ?: $expediente->serie_trd_id));
                $clasificacion = $this->resolverClasificacion(
                    (int) $nodo,
                    $request->filled('subserie_trd_id') ? (int) $request->subserie_trd_id : null,
                    (int) ($request->dependencia_id ?: $expediente->dependencia_id)
                );
                if (is_string($clasificacion)) {
                    return $this->warningResponse($clasificacion);
                }
            }

            // M08-16: array_filter descartaba 0 y vacíos; los datos físicos se pueden poner en 0 o vaciar,
            // y los obligatorios solo cambian si llegan con valor
            $fields = array_filter(
                $request->only(['nombre_expediente', 'dependencia_id', 'soporte', 'nivel_acceso']),
                fn ($valor) => filled($valor)
            ) + $request->only(['ubicacion_fisica', 'caja', 'carpeta', 'folios_fisicos', 'observaciones_generales']);

            if (array_key_exists('folios_fisicos', $fields)) {
                $fields['folios_fisicos'] = (int) ($fields['folios_fisicos'] ?? 0);
            }
            if ($clasificacion) {
                $fields['serie_trd_id'] = $clasificacion[0]->id;
                $fields['subserie_trd_id'] = $clasificacion[1]?->id;
            }
            if (isset($fields['nivel_acceso'])) {
                $fields['fundamento_acceso'] = $fields['nivel_acceso'] === 'publico' ? null : $request->fundamento_acceso;
            } elseif ($request->filled('fundamento_acceso')) {
                if (! in_array($expediente->nivel_acceso, ['clasificado', 'reservado'], true)) {
                    return $this->warningResponse('Solo los expedientes clasificados o reservados llevan fundamento de acceso.');
                }
                $fields['fundamento_acceso'] = $request->fundamento_acceso;
            }

            $expediente->fill($fields);
            // B06/D6: un expediente "por completar" sale de ese estado al declarar lo que le faltaba
            if ($expediente->por_completar || $reclasifica || isset($fields['nivel_acceso'])) {
                $expediente->por_completar = $expediente->pendientesDeCompletar() !== [];
            }
            $expediente->save();

            return $this->successResponse(
                $expediente->load(['dependencia', 'serieTrd', 'subserieTrd']),
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

            // B10: el índice queda congelado y firmado en la misma transacción del cierre
            if (IndiceExpedienteService::disponible()) {
                $this->indiceService->versionar($expediente->fresh(['dependencia', 'serieTrd']), Auth::user());
            }

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
            $query = OfiArchivoExpediente::visiblesPara(Auth::user())->with(['dependencia', 'serieTrd', 'subserieTrd', 'usuarioApertura']);

            // Filtros opcionales
            if ($request->filled('dependencia_id')) {
                $query->where('dependencia_id', $request->dependencia_id);
            }

            if ($request->filled('serie_trd_id')) {
                $query->where(fn ($q) => $q->where('serie_trd_id', $request->serie_trd_id)
                    ->orWhere('subserie_trd_id', $request->serie_trd_id));
            }

            // B06/D6: expedientes a los que les falta nivel de acceso o subserie
            if ($request->has('por_completar')) {
                $query->where('por_completar', $request->boolean('por_completar'));
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

            // B07: el tipo es opcional al incorporar un radicado; sin él el folio queda por tipificar
            $tipo = null;
            if ($request->filled('tipo_documental_id')) {
                $tipo = $this->tiposDocumentales->resolver($expediente, (int) $request->tipo_documental_id);
                if (is_string($tipo)) {
                    DB::rollBack();

                    return $this->warningResponse($tipo);
                }
            }

            // Crear el registro de índice (La foliación es automática por el boot() del modelo)
            $expedienteDocumento = OfiArchivoExpedienteDocumento::create([
                'expediente_id' => $expedienteId,
                'documentable_id' => $request->documentable_id,
                'documentable_type' => $modelClass,
                // M08-09: la huella del radicado queda fijada en el folio al incorporarlo
                'hash_sha256' => $documento->hash_sha256 ?? null,
                'tipo_documental' => $tipo?->nom ?? ($request->detalle ?: 'Documento incorporado'),
                'asunto' => $request->detalle ?: 'Documento incorporado',
                'detalle' => $request->detalle,
                'usuario_id' => Auth::id(),
                'fecha_incorporacion' => now(),
                'orden' => 0,
                'fecha_documento' => now()->toDateString(),
            ] + $this->tipoDocumentalId($tipo));

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

            // B07: cada archivo por tipo documental se tipifica con el catálogo de la TRD del expediente
            $tipo = null;
            if ($request->tipo === 'tipo_documental') {
                $tipo = $this->tiposDocumentales->resolver(
                    $expediente,
                    $request->filled('tipo_documental_id') ? (int) $request->tipo_documental_id : null,
                    $request->tipo_documental
                );
                if (is_string($tipo)) {
                    DB::rollBack();

                    return $this->warningResponse($tipo);
                }
            }
            $nombreTipo = $tipo?->nom ?? 'Expediente completo';

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
                    $detalleParts[] = "Tipo documental: {$nombreTipo}";
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
                    'tipo_documental' => $nombreTipo,
                    'detalle' => implode(' | ', $detalleParts),
                    'usuario_id' => Auth::id(),
                    'archivo_path' => $uploadData['path'],
                    'hash_sha256' => $uploadData['hash'],
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'formato_archivo' => $archivo->getClientOriginalExtension(),
                    'tamano_bytes' => $archivo->getSize(),
                    'fecha_documento' => now()->toDateString(),
                    'asunto' => $tipo?->nom ?? 'Archivo de expediente',
                    'activo' => true,
                    'orden' => 0,
                ] + $this->tipoDocumentalId($tipo));

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
     * B07: tipos documentales con los que se pueden tipificar los folios del expediente.
     */
    public function tiposDocumentales($id)
    {
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($id);

        return $this->successResponse($this->tiposDocumentales->catalogo($expediente), 'Tipos documentales del expediente');
    }

    /**
     * B07: tipifica un folio que llegó sin tipo (radicado incorporado, archivo de un flujo) o
     * corrige el tipo mientras el expediente está abierto. Queda en el historial.
     */
    public function tipificarDocumento(TipificarDocumentoExpedienteRequest $request, $expedienteId, $documentoId)
    {
        if (! TiposDocumentalesExpediente::disponible()) {
            return $this->warningResponse('Falta ejecutar la migración de tipos documentales (2026_10_19_120000).');
        }

        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($expedienteId);
        $doc = OfiArchivoExpedienteDocumento::where('expediente_id', $expediente->id)->findOrFail($documentoId);

        if ($expediente->estado !== 'abierto') {
            return $this->warningResponse('Solo se tipifican documentos de un expediente abierto');
        }
        if (! $doc->activo) {
            return $this->warningResponse('El documento fue retirado del expediente');
        }
        if ($doc->tipo !== 'tipo_documental') {
            return $this->warningResponse('El archivo del expediente completo no lleva tipo documental');
        }

        $tipo = $this->tiposDocumentales->resolver($expediente, (int) $request->tipo_documental_id);
        if (is_string($tipo)) {
            return $this->warningResponse($tipo);
        }

        if ((int) $doc->tipo_documental_id === $tipo->id) {
            return $this->successResponse($doc, 'El documento ya tenía ese tipo documental');
        }

        DB::transaction(function () use ($doc, $tipo) {
            $antes = $doc->tipo_documental;
            $doc->update(['tipo_documental' => $tipo->nom, 'tipo_documental_id' => $tipo->id]);
            if (IndiceExpedienteService::disponible()) {
                $this->indiceService->actualizarTipo($doc);
            }

            app(ExpedienteEventosService::class)->registrar($doc->expediente_id, 'tipificacion', [
                'documento_id' => $doc->id,
                'numero_folio' => $doc->numero_folio,
                'antes' => $antes,
                'despues' => $tipo->nom,
            ]);
        });

        return $this->successResponse($doc->fresh(), "Folio {$doc->numero_folio} tipificado como {$tipo->nom}");
    }

    /** La columna puede no existir aún (migración 2026_10_19_120000 pendiente). */
    private function tipoDocumentalId(?ClasificacionDocumentalTRD $tipo): array
    {
        return TiposDocumentalesExpediente::disponible() ? ['tipo_documental_id' => $tipo?->id] : [];
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
     * B06: resuelve la serie y la subserie del expediente. El árbol de la TRD envía el nodo elegido
     * en serie_trd_id (Serie o SubSerie); si es una serie con subseries, la subserie es obligatoria.
     *
     * @return array{0: ClasificacionDocumentalTRD, 1: ?ClasificacionDocumentalTRD}|string
     */
    private function resolverClasificacion(int $nodoId, ?int $subserieId, int $dependenciaId): array|string
    {
        if ($error = $this->serieInvalida($nodoId, $dependenciaId)) {
            return $error;
        }
        $nodo = ClasificacionDocumentalTRD::find($nodoId);

        if ($nodo->tipo === 'SubSerie') {
            $serie = ClasificacionDocumentalTRD::find($nodo->getAttributes()['parent'] ?? null);
            if (! $serie || $serie->tipo !== 'Serie') {
                return 'La subserie seleccionada no cuelga de una serie de la TRD.';
            }
            if ($subserieId && $subserieId !== $nodo->id) {
                return 'La subserie seleccionada no corresponde a la clasificación enviada.';
            }

            return [$serie, $nodo];
        }

        if ($subserieId) {
            $subserie = ClasificacionDocumentalTRD::find($subserieId);
            if (! $subserie || $subserie->tipo !== 'SubSerie' || (int) ($subserie->getAttributes()['parent'] ?? 0) !== $nodo->id) {
                return 'La subserie seleccionada no pertenece a la serie del expediente.';
            }

            return [$nodo, $subserie];
        }

        if (ClasificacionDocumentalTRD::where('parent', $nodo->id)->where('tipo', 'SubSerie')->exists()) {
            return "La serie {$nodo->cod} tiene subseries: el expediente debe clasificarse en una de ellas.";
        }

        return [$nodo, null];
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
     * B10: estado de la cadena del índice y versiones firmadas en cada cierre, cada una verificada.
     */
    public function versionesIndice($id)
    {
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($id);

        if (! IndiceExpedienteService::disponible()) {
            return $this->successResponse(['cadena' => $this->indiceService->verificarCadena($expediente), 'versiones' => []], 'El índice encadenado aún no está habilitado');
        }

        $versiones = OfiArchivoExpedienteIndiceVersion::where('expediente_id', $expediente->id)
            ->orderByDesc('version')->get()
            ->map(fn (OfiArchivoExpedienteIndiceVersion $v) => $v->toArray() + [
                'con_sello_tiempo' => $v->sello_tiempo !== null,
                'verificacion' => $this->indiceService->verificarVersion($v),
            ]);

        return $this->successResponse([
            'cadena' => $this->indiceService->verificarCadena($expediente),
            'versiones' => $versiones,
        ], 'Índice electrónico del expediente');
    }

    /** B10: PDF del índice tal como se firmó en esa versión. */
    public function descargarVersionIndice($id, $version)
    {
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($id);
        abort_unless(IndiceExpedienteService::disponible(), 404);

        $v = OfiArchivoExpedienteIndiceVersion::where('expediente_id', $expediente->id)->where('version', $version)->firstOrFail();

        return response($v->pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Indice_Expediente_'.$expediente->numero_expediente.'_v'.$v->version.'.pdf"')
            ->header('X-Codigo-Verificacion', $v->codigo_verificacion);
    }

    /**
     * B12: historial del expediente con el resultado de verificar su cadena de huellas.
     */
    public function historial($id, ExpedienteEventosService $eventos)
    {
        $expediente = OfiArchivoExpediente::visiblesPara(Auth::user())->findOrFail($id);

        if (! ExpedienteEventosService::disponible()) {
            return $this->successResponse(['eventos' => [], 'integro' => null, 'rupturas' => []], 'El historial aún no está habilitado');
        }

        $rupturas = $eventos->verificar($expediente->id);

        return $this->successResponse([
            'eventos' => $expediente->eventos()->with('usuario:id,nombres,apellidos')->orderBy('secuencia')->get(),
            'integro' => $rupturas === [],
            'rupturas' => $rupturas,
        ], 'Historial del expediente');
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
