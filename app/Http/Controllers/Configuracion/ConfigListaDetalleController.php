<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ConfigListaDetalleRequest;
use App\Models\Configuracion\ConfigListaDetalle;
use Illuminate\Http\Request;

class ConfigListaDetalleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ConfigListaDetalle::with('lista')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConfigListaDetalleRequest $request)
    {
        $detalle = ConfigListaDetalle::create($request->validated());
        return response()->json($detalle, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $detalle = ConfigListaDetalle::with('lista')->find($id);

        if (!$detalle) {
            return response()->json(['message' => 'Detalle de la lista no encontrado'], 404);
        }

        return response()->json($detalle);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ConfigListaDetalleRequest $request, $id)
    {
        $detalle = ConfigListaDetalle::find($id);

        if (!$detalle) {
            return response()->json(['message' => 'Detalle de la lista no encontrado'], 404);
        }

        $detalle->update($request->validated());

        return response()->json($detalle);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $detalle = ConfigListaDetalle::find($id);

        if (!$detalle) {
            return response()->json(['message' => 'Detalle de la lista no encontrado'], 404);
        }

        $detalle->delete();

        return response()->json(['message' => 'Detalle eliminado correctamente']);
    }
}
