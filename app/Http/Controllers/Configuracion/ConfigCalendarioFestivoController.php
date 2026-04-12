<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Configuracion\ConfigCalendarioFestivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConfigCalendarioFestivoController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        try {
            $festivos = ConfigCalendarioFestivo::orderBy('fecha', 'asc')->get();

            return $this->successResponse($festivos, 'Festivos obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los días festivos', $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fecha' => 'required|date|unique:config_calendario_festivos,fecha',
                'nombre' => 'required|string|max:255',
                'tipo' => 'nullable|string|max:50',
                'anio' => 'nullable|integer|min:2000|max:2100'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Datos inválidos', $validator->errors(), 422);
            }

            $festivo = ConfigCalendarioFestivo::create($request->all());

            return $this->successResponse($festivo, 'Día festivo creado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear el día festivo', $e->getMessage(), 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $festivo = ConfigCalendarioFestivo::find($id);

            if (!$festivo) {
                return $this->errorResponse('Día festivo no encontrado', null, 404);
            }

            $festivo->delete();

            return $this->successResponse(null, 'Día festivo eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el día festivo', $e->getMessage(), 500);
        }
    }

    public function verificarFecha(Request $request)
    {
        try {
            $request->validate(['fecha' => 'required|date']);

            $existe = ConfigCalendarioFestivo::where('fecha', $request->fecha)->exists();

            return $this->successResponse([
                'es_festivo' => $existe,
                'fecha' => $request->fecha
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al verificar la fecha', $e->getMessage(), 500);
        }
    }

    public function festivosPorAnio(int $anio)
    {
        try {
            $festivos = ConfigCalendarioFestivo::where('anio', $anio)
                ->orWhere(function ($query) use ($anio) {
                    $query->whereNull('anio')->whereRaw('YEAR(fecha) = ?', [$anio]);
                })
                ->orderBy('fecha', 'asc')
                ->get();

            return $this->successResponse($festivos, "Festivos del año {$anio} obtenidos exitosamente");
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los festivo del año', $e->getMessage(), 500);
        }
    }
}