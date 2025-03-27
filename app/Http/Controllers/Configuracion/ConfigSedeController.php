<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\ConfigSede;
use Illuminate\Http\Request;

class ConfigSedeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sedes = ConfigSede::all();
        return response()->json($sedes, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $sedes = ConfigSede::create($request->all());
        return response()->json($sedes, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $sede = ConfigSede::find($id);
        if (!$sede) {
            return response('Sede no encontrada', 404);
        }

        return response()->json($sede, 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $sede = ConfigSede::find($id);
        if (!$sede) {
            return response('Sede no encontrada', 404);
        }

        $sede->update($request->all());
        return response()->json($sede, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $sede = ConfigSede::find($id);
        if (!$sede) {
            return response('Sede no encontrada', 404);
        }

        $sede->delete();
        return response()->json('Sede elimina correctament');
    }

    public function estadisticas()
    {
        $sedesTotal = ConfigSede::count();
        return response()->json([
            'total' => $sedesTotal
        ]);
    }
}
