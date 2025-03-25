<?php

namespace App\Http\Controllers\VentanillaUnida;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\VentanillaRadicaReciRequest;
use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VentanillaRadicaReciController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $radicados = VentanillaRadicaReci::with([
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
                'archivo_radica'
            ])
            ->paginate(10);

        // Transformar los datos para incluir los nuevos campos
        $radicados->getCollection()->transform(function ($radicado) {
            return [
                'id' => $radicado->id,
                'num_radicado' => $radicado->num_radicado,
                'dias_para_vencer' => $radicado->fec_venci ? now()->diffInDays($radicado->fec_venci) : null,
                'tiene_archivos' => !empty($radicado->archivo_radica),
                'created_at' => $radicado->created_at->format('Y-m-d H:i:s'),
                'fec_venci' => $radicado->fec_venci ? $radicado->fec_venci->format('Y-m-d') : null,
                'clasificacion_documental' => $radicado->clasificacionDocumental,
                'tercero' => $radicado->tercero,
                'medio_recepcion' => $radicado->medioRecepcion,
                'servidor_archivos' => $radicado->servidorArchivos
            ];
        });

        return response()->json($radicados);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VentanillaRadicaReciRequest $request)
    {
        // Validar la solicitud
        $validatedData = $request->validated();

        // Obtener la dependencia del custodio desde la solicitud
        $cod_dependencia = $this->obtenerDependenciaCustodio($validatedData['responsables'] ?? []);

        // Generar el número de radicado usando la dependencia del custodio
        $num_radicado = $this->generarNumeroRadicado($cod_dependencia);

        // Crear el radicado con los datos enviados
        $radicado = new VentanillaRadicaReci($request->validated());
        $radicado->num_radicado = $num_radicado;

        // Guardar el radicado
        $radicado->save();

        return response()->json($radicado, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $radicado = VentanillaRadicaReci::with([
            'clasificacionDocumental',
            'tercero',
            'medioRecepcion',
            'servidorArchivos'
        ])->find($id);

        if (!$radicado) {
            return response()->json(['message' => 'Radicación no encontrada'], 404);
        }

        return response()->json($radicado);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VentanillaRadicaReciRequest $request, $id)
    {
        $radicado = VentanillaRadicaReci::find($id);

        if (!$radicado) {
            return response()->json(['message' => 'Radicación no encontrada'], 404);
        }

        $radicado->update($request->validated());

        return response()->json($radicado);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $radicado = VentanillaRadicaReci::find($id);

        if (!$radicado) {
            return response()->json(['message' => 'Radicación no encontrada'], 404);
        }

        $radicado->delete();

        return response()->json(['message' => 'Radicación eliminada correctamente']);
    }

    private function generarNumeroRadicado($cod_dependencia)
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
        $variables = ['YYYY' => $yyyy, 'MM' => $mm, 'DD' => $dd, 'COD_DEPEN' => $cod_dependencia, str_repeat('#', $longitudConsecutivo) => $consecutivo];

        foreach ($variables as $key => $value) {
            if (strpos($formato, $key) !== false) {
                $formato = str_replace($key, $value, $formato);
            }
        }

        return $formato;
    }


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
    }

    public function listarRadicados(Request $request)
    {
        $this->authorize('ver-radicados');

        $query = VentanillaRadicaReci::query();

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

        $radicados = $query->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $radicados
        ]);
    }
}
