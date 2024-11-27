<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Http\Request;

class ConfigDiviPoliController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ConfigDiviPoli $configDiviPoli)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ConfigDiviPoli $configDiviPoli)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConfigDiviPoli $configDiviPoli)
    {
        //
    }

    public function paises()
    {
        $paises = ConfigDiviPoli::where('tipo', '=', 'Pais')
            ->get();

        if ($paises) {
            return response()->json([
                'status' => true,
                'data' => $paises,
                'message' => 'Listado de paises'
            ], 200);
        }
    }

    public function departamentos($id)
    {
        $departamentos = ConfigDiviPoli::where('parent', '=', $id)
            ->get();

        if ($departamentos) {
            return response()->json([
                'status' => true,
                'data' => $departamentos,
                'message' => 'Listado de departamentos'
            ], 200);
        }
    }

    public function municipios($id)
    {
        $municipios = ConfigDiviPoli::where('parent', '=', $id)
            ->get();

        if ($municipios) {
            return response()->json([
                'status' => true,
                'data' => $municipios,
                'message' => 'Listado de municipios'
            ], 200);
        }
    }
}
