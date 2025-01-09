<?php

namespace App\Http\Controllers\VentanillaUnida;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\VentanillaRadicaReciRequest;
use App\Models\Configuracion\ConfigVarias;
use App\Models\ControlAcceso\UsersCargo;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Models\VentanillaUnica\VentanillaRadicaReciResponsa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VentanillaRadicaReciController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(VentanillaRadicaReci::with([
            'clasificacionDocumental',
            'tercero',
            'medioRecepcion',
            'servidorArchivos'
        ])->get());
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(VentanillaRadicaReciRequest $request)
    {
        // 📌 Validar la solicitud
        $validatedData = $request->validated();

        // 📌 Obtener la dependencia del custodio desde la solicitud
        $cod_dependencia = $this->obtenerDependenciaCustodio($validatedData['responsables'] ?? []);

        // 📌 Generar el número de radicado usando la dependencia del custodio
        $num_radicado = $this->generarNumeroRadicado($cod_dependencia);

        // 📌 Insertar el radicado con el número generado
        $radicado = VentanillaRadicaReci::create(array_merge($validatedData, [
            'num_radicado' => $num_radicado,
        ]));

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
        $formato = ConfigVarias::getValor('formato_num_radicado', 'YYYYMMDD-#####');

        // 📌 Contar la cantidad de '#' en el formato para definir la longitud del consecutivo
        preg_match('/#+/', $formato, $matches);
        $longitudConsecutivo = isset($matches[0]) ? strlen($matches[0]) : 5; // Por defecto 5 dígitos

        // 📌 Obtener datos dinámicos
        $fecha = Carbon::now();
        $yyyy = $fecha->format('Y');
        $mm = $fecha->format('m');
        $dd = $fecha->format('d');

        // 📌 Obtener el último consecutivo del año actual
        $ultimoRadicado = VentanillaRadicaReci::whereYear('created_at', $yyyy)->count() + 1;
        $consecutivo = str_pad($ultimoRadicado, $longitudConsecutivo, '0', STR_PAD_LEFT);

        // 📌 Reemplazar variables en el formato
        return str_replace(
            ['YYYY', 'MM', 'DD', str_repeat('#', $longitudConsecutivo), 'COD_DEPEN'],
            [$yyyy, $mm, $dd, $consecutivo, $cod_dependencia],
            $formato
        );
    }

    private function obtenerDependenciaCustodio($responsables)
    {
        foreach ($responsables as $responsable) {
            if (isset($responsable['custodio']) && $responsable['custodio'] == true) {
                $usuarioCargo = UsersCargo::where('user_id', $responsable['user_id'])->first();
                return $usuarioCargo && $usuarioCargo->dependencia ? $usuarioCargo->dependencia->codigo : 'GEN';
            }
        }
        return 'GEN'; // Si no hay custodio, usa "GEN"
    }
}
