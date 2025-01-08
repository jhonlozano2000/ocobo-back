<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ConfigDiviPoliRequest;
use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Http\Request;

class ConfigDiviPoliController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $diviPoli = ConfigDiviPoli::with('parent', 'children')->get();
        return response()->json($diviPoli);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConfigDiviPoliRequest $request)
    {
        $diviPoli = ConfigDiviPoli::create($request->validated());
        return response()->json($diviPoli, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $diviPoli = ConfigDiviPoli::with('parent', 'children')->find($id);

        if (!$diviPoli) {
            return response()->json(['message' => 'División política no encontrada'], 404);
        }

        return response()->json($diviPoli);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ConfigDiviPoliRequest $request, $id)
    {
        $diviPoli = ConfigDiviPoli::find($id);

        if (!$diviPoli) {
            return response()->json(['message' => 'División política no encontrada'], 404);
        }

        $diviPoli->update($request->validated());
        return response()->json($diviPoli);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $division = ConfigDiviPoli::find($id);

        if (!$division) {
            return response()->json(['message' => 'División política no encontrada'], 404);
        }

        // Verificar si tiene dependencias (hijos)
        if ($division->children()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar porque tiene divisiones políticas asociadas.'
            ], 409); // Código HTTP 409: Conflicto
        }

        $division->delete();

        return response()->json(['message' => 'División política eliminada correctamente']);
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
