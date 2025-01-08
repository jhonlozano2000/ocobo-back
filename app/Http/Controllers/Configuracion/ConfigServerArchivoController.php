<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\ConfigServerArchivoRequest;
use App\Models\Configuracion\ConfigServerArchivo;
use Illuminate\Http\Request;

class ConfigServerArchivoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ConfigServerArchivo::with('proceso')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ConfigServerArchivoRequest $request)
    {
        $server = ConfigServerArchivo::create($request->validated());
        return response()->json($server, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $server = ConfigServerArchivo::with('proceso')->find($id);

        if (!$server) {
            return response()->json(['message' => 'Servidor no encontrado'], 404);
        }

        return response()->json($server);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ConfigServerArchivoRequest $request, $id)
    {
        $server = ConfigServerArchivo::find($id);

        if (!$server) {
            return response()->json(['message' => 'Servidor no encontrado'], 404);
        }

        $server->update($request->validated());

        return response()->json($server);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $server = ConfigServerArchivo::find($id);

        if (!$server) {
            return response()->json(['message' => 'Servidor no encontrado'], 404);
        }

        $server->delete();

        return response()->json(['message' => 'Servidor eliminado correctamente']);
    }
}
