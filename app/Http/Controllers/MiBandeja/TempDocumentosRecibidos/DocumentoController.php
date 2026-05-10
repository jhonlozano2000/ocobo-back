<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\TempReci\DocumentoRequest;
use App\Http\Resources\MiBandeja\TempReci\DocumentoResource;
use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\MiBandeja\TempDocumentosRecibidos\DocumentoUsuario;
use App\Models\MiBandeja\TempDocumentosRecibidos\Version;
use App\Events\MiBandeja\TempReci\ContenidoActualizado;
use App\Events\MiBandeja\TempReci\UsuarioConectado;
use App\Events\MiBandeja\TempReci\UsuarioDesconectado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controlador de documentos colaborativos para Comunicaciones Recibidas.
 *
 * Maneja el CRUD de documentos, sincronización Yjs,
 * gestión de versiones y asignación de usuarios.
 */
class DocumentoController extends Controller
{
    /**
     * Obtiene el listado de documentos colaborativos del usuario autenticado.
     *
     * Retorna todos los documentos donde el usuario tiene acceso (como creador o asignado).
     * Incluye contenido, creador y paginación.
     *
     * @param Request $request Solicitud HTTP
     * @return AnonymousResourceCollection Lista paginada de documentos
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
     *   "current_page": 1,
     *   "total": 50
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $documentos = Documento::whereHas('usuarios', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->orWhere('user_id', $request->user()->id)
            ->with(['contenido', 'creador'])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return DocumentoResource::collection($documentos);
    }

    /**
     * Crea un nuevo documento colaborativo.
     *
     * Inicializa un documento vinculado a un radicado de comunicaciones recibidas.
     * Automáticamente crea el registro de contenido vacío.
     *
     * @param DocumentoRequest $request Solicitud validada
     * @return JsonResponse Documento creado con código 201
     *
     * @bodyParam radica_reci_id integer required ID del radicado. Example: 1
     * @bodyParam titulo string required Título del documento (3-255 caracteres). Example: "Oficio de respuesta"
     * @bodyParam notas string Notas opcionales. Example: "Revision pendiente"
     * @bodyParam es_publico boolean Visibilidad para otros usuarios. Default: false
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "titulo": "Oficio de respuesta",
     *     "estado": "borrador"
     *   }
     * }
     *
     * @response 422 {
     *   "message": "Error de validación",
     *   "errors": {"titulo": ["El título es obligatorio"]}
     * }
     */
    public function store(DocumentoRequest $request): JsonResponse
    {
        $data = $request->validated();

        $documentoData = [
            'titulo' => $data['titulo'],
            'notas' => $data['notas'] ?? null,
            'es_publico' => $data['es_publico'] ?? false,
            'estado' => $data['estado'] ?? 'borrador',
            'user_id' => $request->user()->id,
        ];

        if (!empty($data['radica_reci_id'])) {
            $documentoData['radica_reci_id'] = $data['radica_reci_id'];
        }

        $documento = Documento::create($documentoData);

        Contenido::create([
            'documento_id' => $documento->id,
            'contenido_yjs' => [],
            'hash_contenido' => '',
        ]);

        return (new DocumentoResource($documento->load(['contenido', 'creador'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Muestra un documento específico con toda su información.
     *
     * Retorna el documento, contenido, usuarios asignados y cursores activos.
     * Inicializa el cursor del usuario si no existe.
     *
     * @param Request $request Solicitud HTTP
     * @param Documento $documento Documento a mostrar
     * @return DocumentoResource|JsonResponse Documento con relaciones o error 403
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "titulo": "Oficio de respuesta",
     *     "contenido": {"contenido_yjs": [], "hash": "abc123"},
     *     "cursores": [{"user_id": 1, "color": "#E53935"}]
     *   }
     * }
     *
     * @response 403 {
     *   "message": "No tienes acceso a este documento"
     * }
     */
    public function show(Request $request, Documento $documento): DocumentoResource|JsonResponse
    {
        if (!$documento->tieneAcceso($request->user())) {
            return response()->json(['message' => 'No tienes acceso a este documento'], 403);
        }

        if ($documento->cursores()->count() === 0) {
            $this->inicializarCursor($documento, $request->user());
        }

        UsuarioConectado::dispatch(
            $documento->id,
            $request->user()->id,
            $request->user()->nombres
        );

        return new DocumentoResource($documento->load(['contenido', 'cursores', 'usuarios.usuario']));
    }

    /**
     * Actualiza los metadatos de un documento.
     *
     * Actualiza título, estado, notas o visibilidad.
     * No actualiza el contenido del editor (usar sincronizar).
     *
     * @param DocumentoRequest $request Solicitud validada
     * @param Documento $documento Documento a actualizar
     * @return DocumentoResource|JsonResponse Documento actualizado o error 403
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
     *
     * @response 403 {
     *   "message": "No tienes permisos para editar este documento"
     * }
     */
    public function update(DocumentoRequest $request, Documento $documento): DocumentoResource|JsonResponse
    {
        if (!$documento->puedeEditar($request->user())) {
            return response()->json(['message' => 'No tienes permisos para editar este documento'], 403);
        }

        $documento->update($request->validated());

        return new DocumentoResource($documento->fresh(['contenido', 'creador']));
    }

    /**
     * Elimina un documento y todo su contenido.
     *
     * Solo el creador puede eliminar el documento.
     * Elimina en cascada contenido, versiones, comentarios y cursores.
     *
     * @param Request $request Solicitud HTTP
     * @param Documento $documento Documento a eliminar
     * @return JsonResponse Mensaje de confirmación o error 403
     *
     * @response 200 {
     *   "message": "Documento eliminado correctamente"
     * }
     *
     * @response 403 {
     *   "message": "Solo el creador puede eliminar el documento"
     * }
     */
    public function destroy(Request $request, Documento $documento): JsonResponse
    {
        if ($documento->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo el creador puede eliminar el documento'], 403);
        }

        $documento->delete();

        return response()->json(['message' => 'Documento eliminado correctamente']);
    }

    /**
     * Sincroniza el contenido Yjs del documento.
     *
     * Compara hash SHA256 del contenido enviado vs almacenado.
     * Si es diferente, actualiza y retorna el contenido del servidor.
     * Si es igual, retorna confirmación sin contenido.
     *
     * @param Request $request Solicitud con contenido Yjs
     * @param Documento $documento Documento a sincronizar
     * @return JsonResponse Estado de sincronización
     *
     * @bodyParam contenido array required Contenido Yjs JSON. Example: [{"insert": "texto"}]
     *
     * @response 200 {
     *   "sincronizado": true,
     *   "hash": "sha256..."
     * }
     *
     * @response 200 {
     *   "sincronizado": true,
     *   "hash": "sha256...",
     *   "contenido": [{"insert": "texto servidor"}]
     * }
     *
     * @response 403 {
     *   "message": "No tienes permisos para editar"
     * }
     */
    public function sincronizar(Request $request, Documento $documento): JsonResponse
    {
        if (!$documento->puedeEditar($request->user())) {
            return response()->json(['message' => 'No tienes permisos para editar'], 403);
        }

        $contenido = $request->input('contenido');
        $hashCliente = hash('sha256', json_encode($contenido));

        $contenidoDoc = $documento->contenido;
        
        if (!$contenidoDoc) {
            $contenidoDoc = Contenido::create([
                'documento_id' => $documento->id,
                'contenido_yjs' => $contenido,
                'hash_contenido' => $hashCliente,
                'actualizado_por' => $request->user()->id,
            ]);
        } elseif ($contenidoDoc->hash_contenido === $hashCliente) {
            return response()->json([
                'sincronizado' => true,
                'hash' => $hashCliente,
            ]);
        } else {
            $contenidoDoc->actualizarContenido($contenido, $request->user());
        }

        // Broadcast a otros usuarios (no fallar si no funciona)
        try {
            ContenidoActualizado::dispatch(
                $documento->id,
                $contenidoDoc->contenido_yjs,
                $hashCliente,
                $request->user()->id
            );
        } catch (\Exception $e) {
            \Log::warning('Broadcast falló en sincronizar: ' . $e->getMessage());
        }

        return response()->json([
            'sincronizado' => true,
            'hash' => $hashCliente,
            'contenido' => $contenidoDoc->contenido_yjs,
        ]);
    }

    /**
     * Obtiene el contenido Yjs actual del documento.
     *
     * Retorna el contenido y hash para inicializar el editor local.
     *
     * @param Request $request Solicitud HTTP
     * @param Documento $documento Documento
     * @return JsonResponse Contenido Yjs y hash
     *
     * @response 200 {
     *   "contenido": [],
     *   "hash": "sha256..."
     * }
     *
     * @response 403 {
     *   "message": "No tienes acceso"
     * }
     */
    public function obtenerContenido(Request $request, Documento $documento): JsonResponse
    {
        if (!$documento->tieneAcceso($request->user())) {
            return response()->json(['message' => 'No tienes acceso'], 403);
        }

        $contenido = $documento->contenido;
        
        if (!$contenido) {
            return response()->json([
                'contenido' => [],
                'hash' => null,
            ]);
        }

        return response()->json([
            'contenido' => $contenido->contenido_yjs ?? [],
            'hash' => $contenido->hash_contenido,
        ]);
    }

    /**
     * Crea una instantánea del contenido actual.
     *
     * Guarda una versión del contenido para restaurar después.
     *
     * @param Request $request Solicitud con descripción
     * @param Documento $documento Documento
     * @return JsonResponse Versión creada
     *
     * @bodyParam descripcion string Descripción de la versión. Example: "Antes de modificar"
     *
     * @response 201 {
     *   "message": "Versión creada correctamente",
     *   "version": {"id": 1, "numero_version": 1}
     * }
     *
     * @response 403 {
     *   "message": "No tienes permisos"
     * }
     */
    public function crearVersion(Request $request, Documento $documento): JsonResponse
    {
        if (!$documento->puedeEditar($request->user())) {
            return response()->json(['message' => 'No tienes permisos'], 403);
        }

        $version = Version::crearVersion(
            $documento,
            $documento->contenido->contenido_yjs ?? [],
            $request->user(),
            $request->input('descripcion')
        );

        return response()->json([
            'message' => 'Versión creada correctamente',
            'version' => [
                'id' => $version->id,
                'numero_version' => $version->numero_version,
                'descripcion' => $version->descripcion,
                'created_at' => $version->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Lista todas las versiones de un documento.
     *
     * @param Documento $documento Documento
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
            ->with('usuario:id,name')
            ->orderBy('numero_version', 'desc')
            ->get();

        return response()->json(['versiones' => $versiones]);
    }

    /**
     * Restaura una versión específica del documento.
     *
     * Reemplaza el contenido actual con el de la versión.
     * Crea una nueva versión de registro de la restauración.
     *
     * @param Request $request Solicitud HTTP
     * @param Documento $documento Documento
     * @param Version $version Versión a restaurar
     * @return JsonResponse Contenido restaurado
     *
     * @response 200 {
     *   "message": "Versión restaurada correctamente",
     *   "contenido": []
     * }
     *
     * @response 400 {
     *   "message": "La versión no pertenece a este documento"
     * }
     *
     * @response 403 {
     *   "message": "No tienes permisos"
     * }
     */
    public function restaurarVersion(Request $request, Documento $documento, Version $version): JsonResponse
    {
        if (!$documento->puedeEditar($request->user())) {
            return response()->json(['message' => 'No tienes permisos'], 403);
        }

        if ($version->documento_id !== $documento->id) {
            return response()->json(['message' => 'La versión no pertenece a este documento'], 400);
        }

        $contenido = $version->restaurar();
        $documento->contenido->actualizarContenido($contenido, $request->user());

        Version::crearVersion($documento, $contenido, $request->user(), "Restaurado desde versión {$version->numero_version}");

        return response()->json([
            'message' => 'Versión restaurada correctamente',
            'contenido' => $contenido,
        ]);
    }

    /**
     * Asigna usuarios a un documento con roles.
     *
     * Roles disponibles: firmante, responsable, proyector.
     *
     * @param Request $request Solicitud con usuarios
     * @param Documento $documento Documento
     * @return JsonResponse Confirmación
     *
     * @bodyParam usuarios array required Lista de usuarios
     * @bodyParam usuarios[].user_id integer ID del usuario. Example: 1
     * @bodyParam usuarios[].rol string Rol: firmante|responsable|proyector. Example: "firmante"
     *
     * @response 200 {
     *   "message": "Usuarios asignados correctamente"
     * }
     *
     * @response 403 {
     *   "message": "Solo el creador puede asignar usuarios"
     * }
     */
    public function asignarUsuarios(Request $request, Documento $documento): JsonResponse
    {
        if ($documento->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo el creador puede asignar usuarios'], 403);
        }

        $usuarios = $request->input('usuarios', []);

        foreach ($usuarios as $usuario) {
            DocumentoUsuario::updateOrCreate(
                [
                    'documento_id' => $documento->id,
                    'user_id' => $usuario['user_id'],
                ],
                [
                    'rol' => $usuario['rol'],
                ]
            );
        }

        return response()->json(['message' => 'Usuarios asignados correctamente']);
    }

    public function guardarConfiguracionPagina(Request $request, Documento $documento): JsonResponse
    {
        if (!$documento->puedeEditar($request->user())) {
            return response()->json(['message' => 'No tienes permisos para editar este documento'], 403);
        }

        $validated = $request->validate([
            'tamano_papel' => 'sometimes|string|in:a4,carta,legal,oficio',
            'orientacion' => 'sometimes|string|in:vertical,horizontal',
            'margenes' => 'sometimes|array',
            'margenes.superior' => 'sometimes|numeric|min:0|max:100',
            'margenes.inferior' => 'sometimes|numeric|min:0|max:100',
            'margenes.izquierdo' => 'sometimes|numeric|min:0|max:100',
            'margenes.derecho' => 'sometimes|numeric|min:0|max:100',
            'configuracion_columnas' => 'sometimes|array',
            'configuracion_header' => 'sometimes|array|null',
            'configuracion_footer' => 'sometimes|array|null',
        ]);

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

        $documento->update($updateData);

        return response()->json([
            'message' => 'Configuración guardada correctamente',
            'configuracion' => $documento->getConfiguracionPagina(),
        ]);
    }

    /**
     * Inicializa el cursor de un usuario en el documento.
     *
     * @param Documento $documento Documento
     * @param User $user Usuario
     */
    private function inicializarCursor(Documento $documento, $user): void
    {
        $colors = ['#E53935', '#43A047', '#1E88E5', '#FB8C00', '#8E24AA'];
        $colorIndex = $documento->cursores()->count() % count($colors);

        $documento->cursores()->create([
            'user_id' => $user->id,
            'nombre_usuario' => $user->name,
            'color' => $colors[$colorIndex],
            'posicion' => 0,
        ]);
    }
}
