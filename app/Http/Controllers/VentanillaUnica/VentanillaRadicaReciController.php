<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Ventanilla\VentanillaRadicaReciRequest;
use App\Http\Requests\Ventanilla\ListRadicadosRequest;
use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentanillaRadicaReciController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene un listado de radicaciones recibidas con información detallada.
     *
     * Este método retorna todas las radicaciones recibidas con información
     * relacionada como clasificación documental, terceros, medios de recepción
     * y servidores de archivos.
     *
     * @param ListRadicadosRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el listado de radicaciones
     *
     * @queryParam search string Buscar por número de radicado o asunto. Example: "2024-001"
     * @queryParam fecha_desde string Filtrar desde fecha (YYYY-MM-DD). Example: "2024-01-01"
     * @queryParam fecha_hasta string Filtrar hasta fecha (YYYY-MM-DD). Example: "2024-12-31"
     * @queryParam clasifica_documen_id integer Filtrar por clasificación documental. Example: 1
     * @queryParam tercero_id integer Filtrar por tercero. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 10). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Listado de radicaciones obtenido exitosamente",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "num_radicado": "20240101-00001",
     *         "dias_para_vencer": 5,
     *         "tiene_archivos": true,
     *         "created_at": "2024-01-01 10:00:00",
     *         "fec_venci": "2024-01-15",
     *         "clasificacion_documental": {
     *           "id": 1,
     *           "codigo": "01",
     *           "nombre": "Correspondencia"
     *         },
     *         "tercero": {
     *           "id": 1,
     *           "nombre": "Empresa ABC"
     *         }
     *       }
     *     ],
     *     "total": 100
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener el listado de radicaciones",
     *   "error": "Error message"
     * }
     */
    public function index(ListRadicadosRequest $request)
    {
        try {
            $query = VentanillaRadicaReci::with([
                'clasificacionDocumental',
                'tercero',
                'medioRecepcion',
                'servidorArchivos'
            ])
                ->select([
                    'id',
                    'num_radicado',
                    'created_at',
                    'fec_venci',
                    'archivo_radica',
                    'asunto'
                ]);

            // Aplicar filtros si se proporcionan
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('num_radicado', 'like', "%{$search}%")
                        ->orWhere('asunto', 'like', "%{$search}%");
                });
            }

            if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
                $query->whereBetween('created_at', [$request->fecha_desde, $request->fecha_hasta]);
            }

            if ($request->filled('clasifica_documen_id')) {
                $query->where('clasifica_documen_id', $request->clasifica_documen_id);
            }

            if ($request->filled('tercero_id')) {
                $query->where('tercero_id', $request->tercero_id);
            }

            // Ordenar por fecha de creación (más recientes primero)
            $query->orderBy('created_at', 'desc');

            // Paginar
            $perPage = $request->get('per_page', 10);
            $radicados = $query->paginate($perPage);

            // Transformar los datos para incluir los nuevos campos
            $radicados->getCollection()->transform(function ($radicado) {
                return [
                    'id' => $radicado->id,
                    'num_radicado' => $radicado->num_radicado,
                    'dias_para_vencer' => $radicado->dias_para_vencer,
                    'tiene_archivos' => $radicado->tieneArchivos(),
                    'archivo_info' => $radicado->archivo_info,
                    'created_at' => $radicado->created_at->format('Y-m-d H:i:s'),
                    'fec_venci' => $radicado->fec_venci ? $radicado->fec_venci->format('Y-m-d') : null,
                    'asunto' => $radicado->asunto,
                    'clasificacion_documental' => $radicado->clasificacionDocumental,
                    'tercero' => $radicado->tercero,
                    'medio_recepcion' => $radicado->medioRecepcion,
                    'servidor_archivos' => $radicado->servidorArchivos
                ];
            });

            return $this->successResponse($radicados, 'Listado de radicaciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el listado de radicaciones', $e->getMessage(), 500);
        }
    }

    /**
     * Crea una nueva radicación recibida en el sistema.
     *
     * Este método permite crear una nueva radicación con validación de datos
     * y generación automática del número de radicado.
     *
     * @param VentanillaRadicaReciRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la radicación creada
     *
     * @bodyParam clasifica_documen_id integer required ID de la clasificación documental. Example: 1
     * @bodyParam tercero_id integer required ID del tercero. Example: 1
     * @bodyParam medio_recep_id integer required ID del medio de recepción. Example: 1
     * @bodyParam config_server_id integer ID del servidor de archivos. Example: 1
     * @bodyParam fec_venci string Fecha de vencimiento (YYYY-MM-DD). Example: "2024-01-15"
     * @bodyParam num_folios integer required Número de folios. Example: 5
     * @bodyParam num_anexos integer required Número de anexos. Example: 2
     * @bodyParam descrip_anexos string Descripción de anexos. Example: "Documentos adicionales"
     * @bodyParam asunto string Asunto del documento. Example: "Solicitud de información"
     *
     * @response 201 {
     *   "status": true,
     *   "message": "Radicación creada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "num_radicado": "20240101-00001",
     *     "clasifica_documen_id": 1,
     *     "tercero_id": 1,
     *     "created_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "clasifica_documen_id": ["La clasificación documental es obligatoria."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al crear la radicación",
     *   "error": "Error message"
     * }
     */
    public function store(VentanillaRadicaReciRequest $request)
    {
        try {
            DB::beginTransaction();

            // Validar la solicitud
            $validatedData = $request->validated();

            // Obtener la dependencia del custodio desde la solicitud
            $cod_dependencia = $this->obtenerDependenciaCustodio($validatedData['responsables'] ?? []);

            // Generar el número de radicado usando la dependencia del custodio
            $num_radicado = $this->generarNumeroRadicado($cod_dependencia);

            // Crear el radicado con los datos enviados
            $radicado = new VentanillaRadicaReci($validatedData);
            $radicado->num_radicado = $num_radicado;

            // Guardar el radicado
            $radicado->save();

            DB::commit();

            return $this->successResponse(
                $radicado->load(['clasificacionDocumental', 'tercero', 'medioRecepcion']),
                'Radicación creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al crear la radicación', $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene una radicación específica por su ID.
     *
     * Este método permite obtener los detalles completos de una radicación
     * incluyendo todas sus relaciones.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la radicación
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Radicación encontrada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "num_radicado": "20240101-00001",
     *     "clasificacion_documental": {
     *       "id": 1,
     *       "codigo": "01",
     *       "nombre": "Correspondencia"
     *     },
     *     "tercero": {
     *       "id": 1,
     *       "nombre": "Empresa ABC"
     *     }
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la radicación",
     *   "error": "Error message"
     * }
     */
    public function show($id)
    {
        try {
            $radicado = VentanillaRadicaReci::with([
                'clasificacionDocumental',
                'tercero',
                'medioRecepcion',
                'servidorArchivos'
            ])->find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            return $this->successResponse($radicado, 'Radicación encontrada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la radicación', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza una radicación existente en el sistema.
     *
     * Este método permite modificar los datos de una radicación existente
     * manteniendo el número de radicado original.
     *
     * @param int $id ID de la radicación
     * @param VentanillaRadicaReciRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la radicación actualizada
     *
     * @urlParam id integer required El ID de la radicación. Example: 1
     * @bodyParam clasifica_documen_id integer ID de la clasificación documental. Example: 1
     * @bodyParam tercero_id integer ID del tercero. Example: 1
     * @bodyParam medio_recep_id integer ID del medio de recepción. Example: 1
     * @bodyParam config_server_id integer ID del servidor de archivos. Example: 1
     * @bodyParam fec_venci string Fecha de vencimiento (YYYY-MM-DD). Example: "2024-01-15"
     * @bodyParam num_folios integer Número de folios. Example: 5
     * @bodyParam num_anexos integer Número de anexos. Example: 2
     * @bodyParam descrip_anexos string Descripción de anexos. Example: "Documentos adicionales"
     * @bodyParam asunto string Asunto del documento. Example: "Solicitud de información"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Radicación actualizada exitosamente",
     *   "data": {
     *     "id": 1,
     *     "num_radicado": "20240101-00001",
     *     "updated_at": "2024-01-01T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Error de validación",
     *   "errors": {
     *     "clasifica_documen_id": ["La clasificación documental es obligatoria."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la radicación",
     *   "error": "Error message"
     * }
     */
    public function update($id, VentanillaRadicaReciRequest $request)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $radicado->update($request->validated());

            DB::commit();

            return $this->successResponse(
                $radicado->load(['clasificacionDocumental', 'tercero', 'medioRecepcion']),
                'Radicación actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la radicación', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina una radicación del sistema.
     *
     * Este método permite eliminar una radicación específica del sistema.
     * Se recomienda verificar que no tenga dependencias antes de eliminar.
     *
     * @param int $id ID de la radicación
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la eliminación
     *
     * @urlParam id integer required El ID de la radicación a eliminar. Example: 1
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Radicación eliminada exitosamente"
     * }
     *
     * @response 404 {
     *   "status": false,
     *   "message": "Radicación no encontrada"
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al eliminar la radicación",
     *   "error": "Error message"
     * }
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $radicado = VentanillaRadicaReci::find($id);

            if (!$radicado) {
                return $this->errorResponse('Radicación no encontrada', null, 404);
            }

            $radicado->delete();

            DB::commit();

            return $this->successResponse(null, 'Radicación eliminada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la radicación', $e->getMessage(), 500);
        }
    }

    /**
     * Genera un número de radicado único basado en la configuración del sistema.
     *
     * @param string|null $cod_dependencia Código de la dependencia
     * @return string Número de radicado generado
     */
    private function generarNumeroRadicado($cod_dependencia = null)
    {
        $formato = ConfigVarias::getValor('formato_num_radicado_reci', 'YYYYMMDD-#####');

        // Contar la cantidad de '#' en el formato para definir la longitud del consecutivo
        preg_match('/#+/', $formato, $matches);
        $longitudConsecutivo = isset($matches[0]) ? strlen($matches[0]) : 5;

        // Obtener datos dinámicos
        $fecha = Carbon::now();
        $yyyy = $fecha->format('Y');
        $mm = $fecha->format('m');
        $dd = $fecha->format('d');

        // Obtener el último radicado del año actual y sumarle 1
        $ultimoRadicado = VentanillaRadicaReci::whereYear('created_at', $yyyy)
            ->orderBy('id', 'desc')
            ->value('num_radicado');

        // Extraer el número y sumarle 1
        preg_match('/\d+$/', $ultimoRadicado, $consecutivoAnterior);
        $nuevoConsecutivo = isset($consecutivoAnterior[0]) ? intval($consecutivoAnterior[0]) + 1 : 1;

        $consecutivo = str_pad($nuevoConsecutivo, $longitudConsecutivo, '0', STR_PAD_LEFT);

        // Reemplazar solo las variables que existan en el formato
        $variables = [
            'YYYY' => $yyyy,
            'MM' => $mm,
            'DD' => $dd,
            'COD_DEPEN' => $cod_dependencia,
            str_repeat('#', $longitudConsecutivo) => $consecutivo
        ];

        foreach ($variables as $key => $value) {
            if (strpos($formato, $key) !== false) {
                $formato = str_replace($key, $value, $formato);
            }
        }

        return $formato;
    }

    /**
     * Obtiene la dependencia del custodio desde los responsables.
     *
     * @param array $responsables Array de responsables
     * @return string|null Código de la dependencia
     */
    private function obtenerDependenciaCustodio($responsables)
    {
        foreach ($responsables as $responsable) {
            if (!empty($responsable['custodio']) && $responsable['custodio'] == true) {
                $usuario = User::find($responsable['user_id']);

                if ($usuario && $usuario->cargoActivo()->exists()) {
                    return $usuario->cargoActivo->first()->cod_organico; // Código de la dependencia
                }
            }
        }
        return null;
    }

    /**
     * Lista radicaciones con filtros avanzados para administración.
     *
     * Este método permite a los administradores listar radicaciones
     * con filtros avanzados y requiere autorización específica.
     *
     * @param ListRadicadosRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las radicaciones
     *
     * @queryParam estado integer Filtrar por estado. Example: 1
     * @queryParam fecha_desde string Filtrar desde fecha (YYYY-MM-DD). Example: "2024-01-01"
     * @queryParam fecha_hasta string Filtrar hasta fecha (YYYY-MM-DD). Example: "2024-12-31"
     * @queryParam usuario_responsable integer Filtrar por usuario responsable. Example: 1
     * @queryParam per_page integer Número de elementos por página (por defecto: 10). Example: 20
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Radicaciones obtenidas exitosamente",
     *   "data": {
     *     "current_page": 1,
     *     "data": [...],
     *     "total": 100
     *   }
     * }
     *
     * @response 403 {
     *   "status": false,
     *   "message": "No tiene permisos para ver radicaciones"
     * }
     */
    public function listarRadicados(ListRadicadosRequest $request)
    {
        try {
            $this->authorize('ver-radicados');

            $query = VentanillaRadicaReci::with([
                'clasificacionDocumental',
                'tercero',
                'medioRecepcion'
            ]);

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
                $query->whereBetween('created_at', [$request->fecha_desde, $request->fecha_hasta]);
            }

            if ($request->filled('usuario_responsable')) {
                $query->whereHas('responsables', function ($q) use ($request) {
                    $q->where('users.id', $request->usuario_responsable);
                });
            }

            $perPage = $request->get('per_page', 10);
            $radicados = $query->paginate($perPage);

            return $this->successResponse($radicados, 'Radicaciones obtenidas exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener las radicaciones', $e->getMessage(), 500);
        }
    }
}
