<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ConfigListaRequest;
use App\Models\Configuracion\ConfigLista;
use Illuminate\Http\Request;

class ConfigListaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ConfigLista::with('detalles')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConfigListaRequest $request)
    {
        $lista = ConfigLista::create($request->validated());
        return response()->json($lista, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $lista = ConfigLista::with('detalles')->findOrFail($id);
        return response()->json($lista);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ConfigListaRequest  $request, $id)
    {
        $lista = ConfigLista::findOrFail($id);
        $lista->update($request->validated());
        return response()->json($lista);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $lista = ConfigLista::findOrFail($id);
        $lista->delete();
        return response()->json(['message' => 'Lista eliminada correctamente']);
    }
}
