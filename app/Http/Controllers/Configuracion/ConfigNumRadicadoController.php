<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Configuracion\UpdateConfigNumRadicadoRequest;
use App\Models\Configuracion\ConfigVarias;
use Illuminate\Support\Facades\DB;

class ConfigNumRadicadoController extends Controller
{
    use ApiResponseTrait;

    /**
     * Obtiene la configuración actual de numeración de radicados.
     *
     * Este método retorna el formato actual configurado para la numeración
     * de radicados en el sistema. Es útil para mostrar la configuración
     * actual en interfaces de administración.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la configuración
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Configuración de numeración obtenida exitosamente",
     *   "data": {
     *     "formato": "YYYYMMDD-#####",
     *     "descripcion": "Formato de numeración de radicados"
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al obtener la configuración",
     *   "error": "Error message"
     * }
     */
    public function getConfiguracion()
    {
        try {
            $formato = ConfigVarias::getValor('formato_num_radicado', 'YYYYMMDD-#####');

            $configuracion = [
                'formato' => $formato,
                'descripcion' => 'Formato de numeración de radicados'
            ];

            return $this->successResponse($configuracion, 'Configuración de numeración obtenida exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la configuración', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza la configuración de numeración de radicados.
     *
     * Este método permite modificar el formato de numeración de radicados
     * en el sistema. El formato debe seguir un patrón específico que incluye
     * marcadores de posición para fecha y secuencial.
     *
     * @param UpdateConfigNumRadicadoRequest $request La solicitud HTTP validada
     * @return \Illuminate\Http\JsonResponse Respuesta JSON confirmando la actualización
     *
     * @bodyParam formato string required Formato de numeración. Example: "YYYYMMDD-#####"
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Formato de numeración actualizado exitosamente",
     *   "data": {
     *     "formato": "YYYYMMDD-#####",
     *     "descripcion": "Formato de numeración de radicados"
     *   }
     * }
     *
     * @response 422 {
     *   "status": false,
     *   "message": "Datos de validación incorrectos",
     *   "error": {
     *     "formato": ["El formato solo puede contener letras mayúsculas, números, guiones, guiones bajos y símbolos #."]
     *   }
     * }
     *
     * @response 500 {
     *   "status": false,
     *   "message": "Error al actualizar la configuración",
     *   "error": "Error message"
     * }
     */
    public function updateConfiguracion(UpdateConfigNumRadicadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $formato = $request->validated('formato');

            // Actualizar la configuración
            ConfigVarias::setValor('formato_num_radicado', $formato);

            DB::commit();

            $configuracion = [
                'formato' => $formato,
                'descripcion' => 'Formato de numeración de radicados'
            ];

            return $this->successResponse($configuracion, 'Formato de numeración actualizado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la configuración', $e->getMessage(), 500);
        }
    }
}
