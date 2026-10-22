<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Events\MiBandeja\TempReci\ContenidoActualizado;
use App\Events\MiBandeja\TempReci\UsuarioConectado;
use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\TempReci\ConfiguracionPaginaRequest;
use App\Http\Requests\MiBandeja\TempReci\DocumentoRequest;
use App\Http\Resources\MiBandeja\TempReci\DocumentoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\Version;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\MiBandeja\TempDocumentosRecibidos\CursorService;
use App\Services\MiBandeja\TempDocumentosRecibidos\EstilosDocumento;
use App\Services\MiBandeja\TempDocumentosRecibidos\LogoEntidadService;
use App\Services\OfiArchivo\PlantillaGeneradaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Controlador de documentos colaborativos para Comunicaciones Recibidas.
 *
 * Maneja el CRUD de documentos, sincronización Yjs,
 * gestión de versiones y asignación de usuarios.
 */
class DocumentoController extends Controller
{
    use ApiResponseTrait;

    /** Tamaño máximo del contenido serializado (M07-03): el body de `sincronizar` no tenía tope propio. */
    public const MAX_CONTENIDO_BYTES = 5 * 1024 * 1024;

    public function __construct(
        protected CursorService $cursorService
    ) {}

    /**
     * Obtiene el listado de documentos colaborativos del usuario autenticado.
     *
     * Retorna todos los documentos donde el usuario tiene acceso (como creador o asignado).
     * Incluye contenido, creador y paginación.
     *
     * @param  Request  $request  Solicitud HTTP
     * @return JsonResponse Lista paginada de documentos
     *
     * @queryParam per_page integer Elementos por página (por defecto: 20). Example: 20
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "titulo": "Oficio de respuesta",
     *       "estado": "borrador",
     *       "creador": {"id": 1, "name": "Juan Perez"}
     *     }
     *   ],
     *   "pagination": {"total": 50, "per_page": 20, "current_page": 1, "last_page": 3}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $search = trim((string) $request->input('search'));

        $documentos = $this->misDocumentos($request)
            ->when($search !== '', fn ($q) => $q->where(fn ($b) => $b->where('titulo', 'like', "%{$search}%")
                ->orWhereHas('creador', fn ($u) => $u->whereRaw("CONCAT(nombres, ' ', apellidos) LIKE ?", ["%{$search}%"]))))
            ->with(['usuarios', 'contenido', 'creador'])
            ->orderBy('updated_at', 'desc')
            ->paginate($request->integer('per_page', 20))
            // Paginador (no ResourceCollection) para que la respuesta incluya `pagination`
            ->through(fn (Documento $documento) => new DocumentoResource($documento));

        return $this->successResponse($documentos, 'Documentos obtenidos exitosamente');
    }

    /**
     * Cuenta los documentos del usuario por estado para las tarjetas de Mis Documentos.
     *
     * El listado viene paginado, así que el front no puede contarlos por su cuenta.
     *
     * @response 200 {"data": {"total": 5, "borrador": 3, "en_revision": 1, "firmado": 1}}
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $porEstado = $this->misDocumentos($request)
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado');

        return $this->successResponse([
            'total' => (int) $porEstado->sum(),
            'borrador' => (int) ($porEstado['borrador'] ?? 0),
            'en_revision' => (int) ($porEstado['en_revision'] ?? 0),
            'firmado' => (int) ($porEstado['firmado'] ?? 0),
        ], 'Estadísticas obtenidas exitosamente');
    }

    /** Documentos que creó el usuario o en los que colabora (sin los de trabajo de las plantillas). */
    private function misDocumentos(Request $request)
    {
        $userId = $request->user()->id;

        return Documento::whereNull('plantilla_generada_id')
            ->where(fn ($q) => $q->where('user_id', $userId)
                ->orWhereHas('usuarios', fn ($u) => $u->where('user_id', $userId)));
    }

    /**
     * Crea un nuevo documento colaborativo.
     *
     * Inicializa un documento vinculado a un radicado de comunicaciones recibidas.
     * Automáticamente crea el registro de contenido vacío.
     *
     * @param  DocumentoRequest  $request  Solicitud validada
     * @return JsonResponse Documento creado con código 201
     *
     * @bodyParam radica_reci_id integer required ID del radicado. Example: 1
     * @bodyParam titulo string required Título del documento (3-255 caracteres). Example: "Oficio de respuesta"
     * @bodyParam notas string Notas opcionales. Example: "Revision pendiente"
     * @bodyParam es_publico boolean Visibilidad para otros usuarios. Default: false
     * @bodyParam plantilla_id integer Plantilla generada (institucional publicada o personal): da la página y el
     *   contenido inicial, con la fecha, la ciudad y los datos del usuario y de la entidad ya llenos. Example: 3
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "titulo": "Oficio de respuesta",
     *     "estado": "borrador"
     *   }
     * }
     * @response 422 {
     *   "message": "Error de validación",
     *   "errors": {"titulo": ["El título es obligatorio"]}
     * }
     */
    public function store(DocumentoRequest $request, PlantillaGeneradaService $plantillas): JsonResponse
    {
        $data = $request->validated();
        $plantilla = ! empty($data['plantilla_id'])
            ? $plantillas->paraDocumento(
                OfiArchivoPlantillaGenerada::findOrFail($data['plantilla_id']),
                $request->user(),
                ! empty($data['radica_reci_id']) ? VentanillaRadicaReci::find($data['radica_reci_id']) : null
            )
            : null;

        $documentoData = [
            'titulo' => $data['titulo'],
            'notas' => $data['notas'] ?? null,
            'es_publico' => $data['es_publico'] ?? false,
            'estado' => $data['estado'] ?? 'borrador',
            'user_id' => $request->user()->id,
        ];

        if (! empty($data['radica_reci_id'])) {
            $documentoData['radica_reci_id'] = $data['radica_reci_id'];
        }

        $documento = Documento::create($documentoData + ($plantilla['pagina'] ?? []));

        Contenido::create([
            'documento_id' => $documento->id,
            'contenido_yjs' => $plantilla['contenido'] ?? [],
            'hash_contenido' => '',
        ]);

        return $this->createdResponse(
            new DocumentoResource($documento->load(['contenido', 'creador'])),
            'Documento creado exitosamente'
        );
    }

    /**
     * Muestra un documento específico con toda su información.
     *
     * Retorna el documento, contenido, usuarios asignados y cursores activos.
     * Inicializa el cursor del usuario si no existe.
     *
     * @param  Request  $request  Solicitud HTTP
     * @param  Documento  $documento  Documento a mostrar
     * @return JsonResponse Documento con relaciones o error 403
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "titulo": "Oficio de respuesta",
     *     "contenido": {"contenido_yjs": [], "hash": "abc123"},
     *     "cursores": [{"user_id": 1, "color": "#E53935"}]
     *   }
     * }
     * @response 403 {
     *   "message": "No tienes acceso a este documento"
     * }
     */
    /**
     * Logo de la entidad para el encabezado de los documentos (elemento «logo» del encabezado o pie).
     * 404 si no hay logo configurado o no es una imagen válida.
     */
    public function logoEntidad(LogoEntidadService $logos): Response
    {
        $logo = $logos->archivo();
        abort_unless($logo, 404);

        return response($logo['bytes'], 200, [
            'Content-Type' => $logo['mime'],
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(Request $request, Documento $documento): JsonResponse
    {
        if (! $documento->tieneAcceso($request->user())) {
            return $this->forbiddenResponse('No tienes acceso a este documento');
        }

        if ($documento->cursores()->count() === 0) {
            $this->inicializarCursor($documento, $request->user());
        }

        try {
            UsuarioConectado::dispatch(
                $documento->id,
                $request->user()->id,
                $request->user()->nombres
            );
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            \Log::warning('Broadcast UsuarioConectado falló: '.$e->getMessage());
        }

        return $this->successResponse(
            new DocumentoResource($documento->load(['contenido', 'cursores', 'usuarios.usuario'])),
            'Documento obtenido exitosamente'
        );
    }

    /**
     * Actualiza los metadatos de un documento.
     *
     * Actualiza título, estado, notas o visibilidad.
     * No actualiza el contenido del editor (usar sincronizar).
     *
     * @param  DocumentoRequest  $request  Solicitud validada
     * @param  Documento  $documento  Documento a actualizar
     * @return JsonResponse Documento actualizado o error 403
     *
     * @bodyParam titulo string Nuevo título. Example: "Oficio corregido"
     * @bodyParam estado string Estado: borrador|en_revision|firmado. Example: "en_revision"
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "titulo": "Oficio corregido",
     *     "estado": "en_revision"
     *   }
     * }
     * @response 403 {
     *   "message": "No tienes permisos para editar este documento"
     * }
     */
    public function update(DocumentoRequest $request, Documento $documento): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return $this->forbiddenResponse('No tienes permisos para editar este documento');
        }

        $documento->update($request->validated());

        return $this->successResponse(
            new DocumentoResource($documento->fresh(['contenido', 'creador'])),
            'Documento actualizado exitosamente'
        );
    }

    /**
     * Elimina un documento y todo su contenido.
     *
     * Solo el creador puede eliminar el documento.
     * Elimina en cascada contenido, versiones, comentarios y cursores.
     *
     * @param  Request  $request  Solicitud HTTP
     * @param  Documento  $documento  Documento a eliminar
     * @return JsonResponse Mensaje de confirmación o error 403
     *
     * @response 200 {
     *   "message": "Documento eliminado correctamente"
     * }
     * @response 403 {
     *   "message": "Solo el creador puede eliminar el documento"
     * }
     */
    public function destroy(Request $request, Documento $documento): JsonResponse
    {
        if ($documento->user_id !== $request->user()->id) {
            return $this->forbiddenResponse('Solo el creador puede eliminar el documento');
        }

        $documento->delete();

        return $this->successResponse(null, 'Documento eliminado correctamente');
    }

    /**
     * Sincroniza el contenido Yjs del documento.
     *
     * Compara hash SHA256 del contenido enviado vs almacenado.
     * Si es diferente, actualiza y retorna el contenido del servidor.
     * Si es igual, retorna confirmación sin contenido.
     *
     * @param  Request  $request  Solicitud con contenido Yjs
     * @param  Documento  $documento  Documento a sincronizar
     * @return JsonResponse Estado de sincronización
     *
     * @bodyParam contenido array required Contenido Yjs JSON. Example: [{"insert": "texto"}]
     *
     * @response 200 {
     *   "sincronizado": true,
     *   "hash": "sha256..."
     * }
     * @response 200 {
     *   "sincronizado": true,
     *   "hash": "sha256...",
     *   "contenido": [{"insert": "texto servidor"}]
     * }
     * @response 403 {
     *   "message": "No tienes permisos para editar"
     * }
     */
    public function sincronizar(Request $request, Documento $documento): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return $this->forbiddenResponse('No tienes permisos para editar');
        }

        $request->validate([
            'contenido' => ['required', 'array'],
            'hash_base' => ['nullable', 'string', 'size:64'],
        ]);

        $contenido = $request->input('contenido');
        $hashBase = $request->input('hash_base');
        $json = json_encode($contenido);

        if (strlen($json) > self::MAX_CONTENIDO_BYTES) {
            throw ValidationException::withMessages([
                'contenido' => 'El documento excede el tamaño máximo permitido ('.(self::MAX_CONTENIDO_BYTES / 1024 / 1024).' MB).',
            ]);
        }

        $hashCliente = hash('sha256', $json);

        return DB::transaction(function () use ($documento, $contenido, $hashCliente, $hashBase, $request) {
            $contenidoDoc = Contenido::where('documento_id', $documento->id)->lockForUpdate()->first();

            if (! $contenidoDoc) {
                $contenidoDoc = Contenido::create([
                    'documento_id' => $documento->id,
                    'contenido_yjs' => $contenido,
                    'hash_contenido' => $hashCliente,
                    'actualizado_por' => $request->user()->id,
                ]);
            } elseif ($contenidoDoc->hash_contenido === $hashCliente) {
                return $this->successResponse([
                    'sincronizado' => true,
                    'hash' => $hashCliente,
                ]);
            } else {
                // M07-04: Mitigación Last-Write-Wins (detección de conflicto de concurrencia)
                if ($hashBase !== null && $contenidoDoc->hash_contenido && $contenidoDoc->hash_contenido !== $hashBase) {
                    return $this->conflictResponse(
                        'El documento fue modificado concurrentemente por otro usuario. Recargue para obtener la versión más reciente.',
                        [
                            'hash_servidor' => $contenidoDoc->hash_contenido,
                            'hash_base' => $hashBase,
                            'actualizado_por' => $contenidoDoc->actualizado_por,
                            'actualizado_en' => $contenidoDoc->updated_at?->toIso8601String(),
                        ]
                    );
                }

                $contenidoAnterior = $contenidoDoc->contenido_yjs;
                $contenidoDoc->actualizarContenido($contenido, $request->user());

                $this->autoVersionarSiEsNecesario($documento, $contenidoAnterior, $contenido, $request->user());
            }

            try {
                ContenidoActualizado::dispatch(
                    $documento->id,
                    $contenidoDoc->contenido_yjs,
                    $hashCliente,
                    $request->user()->id
                );
            } catch (\Exception $e) {
                if ($e instanceof ValidationException) {
                    throw $e;
                }
                if ($e instanceof HttpExceptionInterface) {
                    throw $e;
                }
                \Log::warning('Broadcast falló en sincronizar: '.$e->getMessage());
            }

            return $this->successResponse([
                'sincronizado' => true,
                'hash' => $hashCliente,
                'contenido' => $contenidoDoc->contenido_yjs,
            ]);
        });
    }

    private function autoVersionarSiEsNecesario(Documento $documento, array $contenidoAnterior, array $contenidoNuevo, $user): void
    {
        $ultimaVersion = $documento->versiones()->first();

        if (! $ultimaVersion) {
            Version::crearVersion($documento, $contenidoNuevo, $user, 'Auto-guardado: primera versión');

            return;
        }

        $minutosDesdeUltimaVersion = $ultimaVersion->created_at->diffInMinutes(now());
        if ($minutosDesdeUltimaVersion >= 5) {
            Version::crearVersion($documento, $contenidoNuevo, $user, 'Auto-guardado automático');

            return;
        }

        $cambioSignificativo = $this->esCambioSignificativo($contenidoAnterior, $contenidoNuevo);
        if ($cambioSignificativo) {
            Version::crearVersion($documento, $contenidoNuevo, $user, 'Auto-guardado: cambio significativo');
        }
    }

    private function esCambioSignificativo(array $anterior, array $nuevo): bool
    {
        $textoAnterior = $this->extraerTextoDeContenido($anterior);
        $textoNuevo = $this->extraerTextoDeContenido($nuevo);

        $diff = abs(strlen($textoNuevo) - strlen($textoAnterior));

        return $diff > 100;
    }

    private function extraerTextoDeContenido(array $contenido): string
    {
        $texto = '';

        foreach ($contenido as $bloque) {
            if (! isset($bloque['type'])) {
                continue;
            }

            if ($bloque['type'] === 'doc') {
                $texto .= $this->extraerTextoDeContenido($bloque['content'] ?? []);
            } elseif ($bloque['type'] === 'paragraph' && isset($bloque['content'])) {
                foreach ($bloque['content'] as $inline) {
                    if (isset($inline['text'])) {
                        $texto .= $inline['text'];
                    }
                }
                $texto .= "\n";
            }
        }

        return $texto;
    }

    /**
     * Obtiene el contenido Yjs actual del documento.
     *
     * Retorna el contenido y hash para inicializar el editor local.
     *
     * @param  Request  $request  Solicitud HTTP
     * @param  Documento  $documento  Documento
     * @return JsonResponse Contenido Yjs y hash
     *
     * @response 200 {
     *   "contenido": [],
     *   "hash": "sha256..."
     * }
     * @response 403 {
     *   "message": "No tienes acceso"
     * }
     */
    public function obtenerContenido(Request $request, Documento $documento): JsonResponse
    {
        if (! $documento->tieneAcceso($request->user())) {
            return $this->forbiddenResponse('No tienes acceso');
        }

        $contenido = $documento->contenido;

        if (! $contenido) {
            return $this->successResponse([
                'contenido' => [],
                'hash' => null,
            ]);
        }

        return $this->successResponse([
            'contenido' => $contenido->contenido_yjs ?? [],
            'hash' => $contenido->hash_contenido,
        ]);
    }

    /**
     * Crea una instantánea del contenido actual.
     *
     * Guarda una versión del contenido para restaurar después.
     *
     * @param  Request  $request  Solicitud con descripción
     * @param  Documento  $documento  Documento
     * @return JsonResponse Versión creada
     *
     * @bodyParam descripcion string Descripción de la versión. Example: "Antes de modificar"
     *
     * @response 201 {
     *   "message": "Versión creada correctamente",
     *   "version": {"id": 1, "numero_version": 1}
     * }
     * @response 403 {
     *   "message": "No tienes permisos"
     * }
     */
    public function crearVersion(Request $request, Documento $documento): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return $this->forbiddenResponse('No tienes permisos');
        }

        $version = Version::crearVersion(
            $documento,
            $documento->contenido->contenido_yjs ?? [],
            $request->user(),
            $request->input('descripcion')
        );

        return $this->createdResponse([
            'version' => [
                'id' => $version->id,
                'numero_version' => $version->numero_version,
                'descripcion' => $version->descripcion,
                'created_at' => $version->created_at->toISOString(),
            ],
        ], 'Versión creada correctamente');
    }

    /**
     * Lista todas las versiones de un documento.
     *
     * @param  Documento  $documento  Documento
     * @return JsonResponse Lista de versiones
     *
     * @response 200 {
     *   "versiones": [
     *     {"id": 1, "numero_version": 2, "descripcion": "Final"}
     *   ]
     * }
     */
    public function listarVersiones(Documento $documento): JsonResponse
    {
        $versiones = $documento->versiones()
            ->with('usuario:id,nombres,apellidos')
            ->orderBy('numero_version', 'desc')
            ->get();

        return $this->successResponse(['versiones' => $versiones], 'Versiones obtenidas exitosamente');
    }

    /**
     * Restaura una versión específica del documento.
     *
     * Reemplaza el contenido actual con el de la versión.
     * Crea una nueva versión de registro de la restauración.
     *
     * @param  Request  $request  Solicitud HTTP
     * @param  Documento  $documento  Documento
     * @param  Version  $version  Versión a restaurar
     * @return JsonResponse Contenido restaurado
     *
     * @response 200 {
     *   "message": "Versión restaurada correctamente",
     *   "contenido": []
     * }
     * @response 400 {
     *   "message": "La versión no pertenece a este documento"
     * }
     * @response 403 {
     *   "message": "No tienes permisos"
     * }
     */
    public function restaurarVersion(Request $request, Documento $documento, Version $version): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return $this->forbiddenResponse('No tienes permisos');
        }

        if ($version->documento_id !== $documento->id) {
            return $this->badRequestResponse('La versión no pertenece a este documento');
        }

        return DB::transaction(function () use ($request, $documento, $version) {
            $contenido = $version->restaurar();
            $documento->contenido->actualizarContenido($contenido, $request->user());

            Version::crearVersion($documento, $contenido, $request->user(), "Restaurado desde versión {$version->numero_version}");

            return $this->successResponse([
                'contenido' => $contenido,
            ], 'Versión restaurada correctamente');
        });
    }

    public function guardarConfiguracionPagina(ConfiguracionPaginaRequest $request, Documento $documento): JsonResponse
    {
        if (! $documento->puedeEditar($request->user())) {
            return $this->forbiddenResponse('No tienes permisos para editar este documento');
        }

        $validated = $request->validated();

        $updateData = [];

        if (isset($validated['tamano_papel'])) {
            $updateData['tamano_papel'] = $validated['tamano_papel'];
        }

        if (isset($validated['orientacion'])) {
            $updateData['orientacion'] = $validated['orientacion'];
        }

        if (array_key_exists('margenes', $validated)) {
            $updateData['margenes'] = $validated['margenes'];
        }

        if (array_key_exists('configuracion_columnas', $validated)) {
            $updateData['configuracion_columnas'] = $validated['configuracion_columnas'];
        }

        if (array_key_exists('configuracion_header', $validated)) {
            $updateData['configuracion_header'] = $validated['configuracion_header'];
        }

        if (array_key_exists('configuracion_footer', $validated)) {
            $updateData['configuracion_footer'] = $validated['configuracion_footer'];
        }

        if (array_key_exists('configuracion_formato', $validated)) {
            $formato = $validated['configuracion_formato'];
            if (is_array($formato) && array_key_exists('estilos', $formato)) {
                $formato['estilos'] = EstilosDocumento::limpiar($formato['estilos']) ?: null;
            }
            $updateData['configuracion_formato'] = $formato ? array_filter($formato, fn ($valor) => $valor !== null) ?: null : null;
        }

        $documento->update($updateData);

        return $this->successResponse([
            'configuracion' => $documento->getConfiguracionPagina(),
        ], 'Configuración guardada correctamente');
    }

    /**
     * Inicializa el cursor de un usuario en el documento.
     *
     * @param  Documento  $documento  Documento
     * @param  User  $user  Usuario
     */
    private function inicializarCursor(Documento $documento, $user): void
    {
        $colors = ['#E53935', '#43A047', '#1E88E5', '#FB8C00', '#8E24AA', '#00ACC1', '#F4511E', '#6D4C41'];
        $colorIndex = $user->id % count($colors);

        $documento->cursores()->create([
            'user_id' => $user->id,
            'nombre_usuario' => $user->nombres,
            'color' => $colors[$colorIndex],
            'posicion' => 0,
        ]);
    }
}
