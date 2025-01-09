<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\ConfigVarias;
use Illuminate\Http\Request;

class ConfigNumRadicadoController extends Controller
{
    public function getConfiguracion()
    {
        return response()->json([
            'formato' => ConfigVarias::getValor('formato_num_radicado', 'YYYYMMDD-#####')
        ]);
    }

    public function updateConfiguracion(Request $request)
    {
        $request->validate([
            'formato' => 'required|string|max:50',
        ]);

        ConfigVarias::setValor('formato_num_radicado', $request->formato);

        return response()->json(['message' => 'Formato de radicado actualizado correctamente']);
    }
}
